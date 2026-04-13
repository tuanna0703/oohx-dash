<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cart items giờ có thể chứa product mà không cần screen_id cụ thể
 * (ví dụ: mua cả gói package_only).
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL requires: drop FK first → then drop unique index (FK depends on the index)
        DB::statement('ALTER TABLE cart_items DROP FOREIGN KEY cart_items_screen_id_foreign');
        DB::statement('ALTER TABLE cart_items DROP INDEX cart_items_cart_id_screen_id_unique');

        Schema::table('cart_items', function (Blueprint $table) {
            $table->ulid('screen_id')->nullable()->change();
            $table->foreign('screen_id')->references('id')->on('screens')->nullOnDelete();
            $table->unique(['cart_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign(['screen_id']);
            $table->dropUnique(['cart_id', 'product_id']);
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->ulid('screen_id')->nullable(false)->change();
            $table->foreign('screen_id')->references('id')->on('screens')->cascadeOnDelete();
            $table->unique(['cart_id', 'screen_id']);
        });
    }
};
