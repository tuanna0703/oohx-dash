<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cart items giờ có thể chứa product mà không cần screen_id cụ thể
 * (ví dụ: mua cả gói package_only).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropForeign(['screen_id']);
            $table->dropUnique(['cart_id', 'screen_id']);
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->ulid('screen_id')->nullable()->change();
            $table->foreign('screen_id')->references('id')->on('screens')->nullOnDelete();
            // Unique: 1 product per cart (thay vì 1 screen per cart)
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
