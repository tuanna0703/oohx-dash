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
        // Drop FK + unique defensively (may have been partially applied in prior failed run)
        $this->dropForeignIfExists('cart_items', 'cart_items_screen_id_foreign');
        $this->dropIndexIfExists('cart_items', 'cart_items_cart_id_screen_id_unique');

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

    private function dropForeignIfExists(string $table, string $fkName): void
    {
        $exists = DB::selectOne(
            "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$table, $fkName]
        );

        if ($exists) {
            DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY {$fkName}");
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        $exists = DB::selectOne(
            "SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND INDEX_NAME = ?",
            [$table, $indexName]
        );

        if ($exists) {
            DB::statement("ALTER TABLE {$table} DROP INDEX {$indexName}");
        }
    }
};
