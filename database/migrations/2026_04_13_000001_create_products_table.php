<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('owner_id');
            $table->unsignedBigInteger('network_id')->nullable();
            $table->ulid('site_id')->nullable(); // for custom/site-specific products
            $table->string('name');
            $table->string('slug')->unique();

            // Product type & category
            $table->enum('type', ['single', 'package', 'custom'])->default('single');
            $table->string('category', 50); // billboard, led, lcd, posm, standee, escalator_wrap, ceiling_hang, lightbox
            $table->enum('listing_mode', ['per_unit', 'per_package', 'per_site'])->default('per_unit');

            // Quantity constraints (for package type)
            $table->unsignedInteger('min_quantity')->default(1);
            $table->unsignedInteger('max_quantity')->nullable();
            $table->unsignedInteger('total_units')->default(1); // total screens/items available

            // Pricing
            $table->decimal('floor_price', 15, 2)->default(0);
            $table->string('currency', 3)->default('VND');
            $table->enum('price_unit', ['month', 'week', 'day', 'campaign'])->default('month');

            // Content
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->string('cover_photo')->nullable();
            $table->json('photos')->nullable();
            $table->json('specs')->nullable(); // flexible specs per category

            // Location
            $table->string('city')->nullable();
            $table->string('region')->nullable();

            // Package options (for package type)
            $table->json('package_options')->nullable();
            // Example: [{"name":"50 cửa hàng","quantity":50,"price":120000000},{"name":"100 cửa hàng","quantity":100,"price":200000000}]

            // Status & sorting
            $table->enum('status', ['active', 'paused', 'sold_out', 'draft'])->default('draft');
            $table->boolean('featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            // SEO
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('owner_id')->references('id')->on('owners')->cascadeOnDelete();
            $table->foreign('network_id')->references('id')->on('networks')->nullOnDelete();
            $table->foreign('site_id')->references('id')->on('sites')->nullOnDelete();

            $table->index(['owner_id', 'status']);
            $table->index(['category', 'status']);
            $table->index(['type', 'status']);
            $table->index('status');
        });

        Schema::create('product_screens', function (Blueprint $table) {
            $table->id();
            $table->ulid('product_id');
            $table->ulid('screen_id');
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);

            $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            $table->foreign('screen_id')->references('id')->on('screens')->cascadeOnDelete();
            $table->unique(['product_id', 'screen_id']);
        });

        // Add product_id to cart_items and booking_lines
        Schema::table('cart_items', function (Blueprint $table) {
            $table->ulid('product_id')->nullable()->after('screen_id');
            $table->unsignedInteger('quantity')->default(1)->after('spot_length');
            $table->json('selected_screen_ids')->nullable()->after('quantity');
            $table->string('selected_region')->nullable()->after('selected_screen_ids');

            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });

        Schema::table('booking_lines', function (Blueprint $table) {
            $table->ulid('product_id')->nullable()->after('campaign_id');

            $table->foreign('product_id')->references('id')->on('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('booking_lines', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn('product_id');
        });
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn(['product_id', 'quantity', 'selected_screen_ids', 'selected_region']);
        });
        Schema::dropIfExists('product_screens');
        Schema::dropIfExists('products');
    }
};
