<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cart items giờ có thể chứa product mà không cần screen_id cụ thể.
 * Tắt FK checks để MySQL cho phép drop index mà không bị block.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        // Drop FK on screen_id (nếu còn)
        if ($this->foreignExists('cart_items', 'cart_items_screen_id_foreign')) {
            DB::statement('ALTER TABLE cart_items DROP FOREIGN KEY cart_items_screen_id_foreign');
        }

        // Drop unique index (nếu còn)
        if ($this->indexExists('cart_items', 'cart_items_cart_id_screen_id_unique')) {
            DB::statement('ALTER TABLE cart_items DROP INDEX cart_items_cart_id_screen_id_unique');
        }

        // Modify screen_id → nullable
        DB::statement('ALTER TABLE cart_items MODIFY COLUMN screen_id CHAR(26) NULL');

        // Re-add FK on screen_id (ON DELETE SET NULL)
        if (! $this->foreignExists('cart_items', 'cart_items_screen_id_foreign')) {
            DB::statement('ALTER TABLE cart_items ADD CONSTRAINT cart_items_screen_id_foreign FOREIGN KEY (screen_id) REFERENCES screens(id) ON DELETE SET NULL');
        }

        // Add unique on [cart_id, product_id] (nếu chưa có)
        if (! $this->indexExists('cart_items', 'cart_items_cart_id_product_id_unique')) {
            DB::statement('ALTER TABLE cart_items ADD UNIQUE INDEX cart_items_cart_id_product_id_unique (cart_id, product_id)');
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        if ($this->foreignExists('cart_items', 'cart_items_screen_id_foreign')) {
            DB::statement('ALTER TABLE cart_items DROP FOREIGN KEY cart_items_screen_id_foreign');
        }
        if ($this->indexExists('cart_items', 'cart_items_cart_id_product_id_unique')) {
            DB::statement('ALTER TABLE cart_items DROP INDEX cart_items_cart_id_product_id_unique');
        }

        DB::statement('ALTER TABLE cart_items MODIFY COLUMN screen_id CHAR(26) NOT NULL');
        DB::statement('ALTER TABLE cart_items ADD CONSTRAINT cart_items_screen_id_foreign FOREIGN KEY (screen_id) REFERENCES screens(id) ON DELETE CASCADE');

        if (! $this->indexExists('cart_items', 'cart_items_cart_id_screen_id_unique')) {
            DB::statement('ALTER TABLE cart_items ADD UNIQUE INDEX cart_items_cart_id_screen_id_unique (cart_id, screen_id)');
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }

    private function foreignExists(string $table, string $fkName): bool
    {
        return (bool) DB::selectOne(
            "SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$table, $fkName]
        );
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return (bool) DB::selectOne(
            "SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?",
            [$table, $indexName]
        );
    }
};
