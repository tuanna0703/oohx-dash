#!/bin/bash
# ─────────────────────────────────────────────────────────────────────────────
# oohx-dash entrypoint — chạy 1 lần khi container start, trước khi exec CMD.
#
# Skip migration/cache nếu CONTAINER_ROLE != "app" (queue/scheduler container
# share image nhưng không cần warm cache — app container đã làm xong).
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail

cd /var/www/html

# Container role (set qua env trong docker-compose). Default: app
ROLE="${CONTAINER_ROLE:-app}"

log() { echo "[entrypoint:${ROLE}] $*"; }

# ── Common cho mọi role ──────────────────────────────────────────────────────

# Nếu APP_KEY chưa set, fail nhanh
if [ -z "${APP_KEY:-}" ]; then
    log "ERROR: APP_KEY chưa được set trong env. Chạy 'php artisan key:generate --show' để tạo."
    exit 1
fi

# Sync public/ sang shared volume cho Caddy đọc static assets.
# Chỉ app container làm; queue/scheduler share volume nhưng không cần ghi.
if [ "$ROLE" = "app" ] && [ -n "${SYNC_PUBLIC_DIR:-}" ]; then
    log "Syncing public/ to shared volume ${SYNC_PUBLIC_DIR}..."
    mkdir -p "$SYNC_PUBLIC_DIR"
    # cp -rT: copy contents (không tạo subdir trùng), giữ permissions
    cp -rT /var/www/html/public "$SYNC_PUBLIC_DIR" 2>/dev/null || \
        log "WARN: sync public/ failed, Caddy có thể không serve static assets"
fi

# storage:link — idempotent (Laravel không lỗi nếu link đã tồn tại với --force)
if [ ! -L public/storage ]; then
    log "Creating storage symlink..."
    php artisan storage:link --force --quiet || true
fi

# ── Chỉ app container chạy migrations + warm cache ───────────────────────────
if [ "$ROLE" = "app" ]; then
    if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
        log "Running migrations..."
        php artisan migrate --force --no-interaction
    else
        log "Skipping migrations (RUN_MIGRATIONS=false)"
    fi

    log "Caching config / routes / views / events..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan event:cache

    if php artisan list 2>/dev/null | grep -q "filament:optimize"; then
        log "Optimizing Filament..."
        php artisan filament:optimize || true
    fi

    log "App ready."
fi

# ── Queue / scheduler chỉ cần config:cache (đọc env qua ConfigCacheServiceProvider) ──
if [ "$ROLE" = "queue" ] || [ "$ROLE" = "scheduler" ]; then
    log "Caching config..."
    php artisan config:cache
fi

# Exec CMD (php-fpm, queue:work, schedule:work, ...) — replace shell với pid 1 inheritance
exec "$@"
