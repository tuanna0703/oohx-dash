# syntax=docker/dockerfile:1.7
# ──────────────────────────────────────────────────────────────────────────────
# oohx-dash — production image
# Multi-stage: composer (vendor) → node (assets) → php-fpm runtime
# ──────────────────────────────────────────────────────────────────────────────

ARG PHP_VERSION=8.3
ARG NODE_VERSION=20

# ── Stage 1: composer dependencies (no-dev, optimized) ──────────────────────
FROM composer:2 AS vendor

WORKDIR /app

# Cài deps trước rồi copy source — tận dụng layer cache khi composer.json không đổi
COPY composer.json composer.lock ./

# --no-scripts vì chưa có app code; --no-autoloader vì sẽ dump autoload sau khi có source
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction \
        --no-progress

# Copy source rồi dump autoload optimized
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev


# ── Stage 2: build frontend assets (Vite + Tailwind) ────────────────────────
FROM node:${NODE_VERSION}-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci --no-audit --no-fund

# Cần resources/ và public/ + config Vite + composer-installed packages (Filament)
COPY resources ./resources
COPY public ./public
COPY vite.config.js ./
COPY --from=vendor /app/vendor ./vendor

RUN npm run build


# ── Stage 3: runtime — php-fpm-alpine ────────────────────────────────────────
FROM php:${PHP_VERSION}-fpm-alpine AS runtime

# Native deps cho extensions + runtime utilities
RUN apk add --no-cache \
        bash \
        curl \
        git \
        icu-libs \
        libpng \
        libjpeg-turbo \
        libwebp \
        freetype \
        libzip \
        oniguruma \
        postgresql-libs \
        tini \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        icu-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        libwebp-dev \
        freetype-dev \
        libzip-dev \
        oniguruma-dev \
        postgresql-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/* /var/cache/apk/*

# php-fpm + php config
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-app.conf

# Tạo user www-data nếu chưa có (alpine PHP image đã có UID 82, ta align với UID 33 quen thuộc)
RUN deluser --remove-home www-data 2>/dev/null || true \
    && addgroup -g 1000 -S www-data \
    && adduser -u 1000 -D -S -G www-data www-data

WORKDIR /var/www/html

# Copy artifacts từ các stage trước
COPY --chown=www-data:www-data --from=vendor /app/vendor ./vendor
COPY --chown=www-data:www-data --from=vendor /app/composer.json /app/composer.lock ./
COPY --chown=www-data:www-data --from=frontend /app/public/build ./public/build
COPY --chown=www-data:www-data . .

# Storage permissions + đảm bảo các thư mục runtime tồn tại
RUN mkdir -p storage/framework/{cache/data,sessions,views} \
        storage/app/public \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Entrypoint: chạy migrations + cache warmup khi container start
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/entrypoint.sh

USER www-data

EXPOSE 9000

# tini làm PID 1 để forward signals đến php-fpm + reap zombies
ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm", "-F", "-O"]
