<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cart items giờ có thể chứa product mà không cần screen_id cụ thể.
 *
 * MySQL dùng composite unique index (cart_id, screen_id) làm index cho cả 2 FK:
 * - cart_items_cart_id_foreign
 * - cart_items_screen_id_foreign
 * Phải drop CẢ HAI FK + index trong 1 ALTER TABLE, rồi re-add.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tìm danh sách FK thực tế đang tồn tại
        $fks = $this->getExistingFKs('cart_items');
        $hasUniqueIndex = $this->indexExists('cart_items', 'cart_items_cart_id_screen_id_unique');

        // Build 1 câu ALTER TABLE duy nhất: drop all FKs + drop index
        $drops = [];
        foreach ($fks as $fk) {
            $drops[] = "DROP FOREIGN KEY {$fk}";
        }
        if ($hasUniqueIndex) {
            $drops[] = 'DROP INDEX cart_items_cart_id_screen_id_unique';
        }

        if (! empty($drops)) {
            DB::statement('ALTER TABLE cart_items ' . implode(', ', $drops));
        }

        // Modify + re-add FKs + new unique — 1 statement
        $adds = [
            'MODIFY COLUMN screen_id CHAR(26) NULL',
            'ADD INDEX cart_items_cart_id_index (cart_id)',
            'ADD CONSTRAINT cart_items_cart_id_foreign FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE',
            'ADD CONSTRAINT cart_items_screen_id_foreign FOREIGN KEY (screen_id) REFERENCES screens(id) ON DELETE SET NULL',
        ];

        if (! $this->indexExists('cart_items', 'cart_items_cart_id_product_id_unique')) {
            $adds[] = 'ADD UNIQUE INDEX cart_items_cart_id_product_id_unique (cart_id, product_id)';
        }

        DB::statement('ALTER TABLE cart_items ' . implode(', ', $adds));
    }

    public function down(): void
    {
        $fks = $this->getExistingFKs('cart_items');
        $drops = [];
        foreach ($fks as $fk) {
            $drops[] = "DROP FOREIGN KEY {$fk}";
        }
        if ($this->indexExists('cart_items', 'cart_items_cart_id_product_id_unique')) {
            $drops[] = 'DROP INDEX cart_items_cart_id_product_id_unique';
        }
        if ($this->indexExists('cart_items', 'cart_items_cart_id_index')) {
            $drops[] = 'DROP INDEX cart_items_cart_id_index';
        }

        if (! empty($drops)) {
            DB::statement('ALTER TABLE cart_items ' . implode(', ', $drops));
        }

        DB::statement('ALTER TABLE cart_items '
            . 'MODIFY COLUMN screen_id CHAR(26) NOT NULL, '
            . 'ADD CONSTRAINT cart_items_cart_id_foreign FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE, '
            . 'ADD CONSTRAINT cart_items_screen_id_foreign FOREIGN KEY (screen_id) REFERENCES screens(id) ON DELETE CASCADE, '
            . 'ADD UNIQUE INDEX cart_items_cart_id_screen_id_unique (cart_id, screen_id)');
    }

    private function getExistingFKs(string $table): array
    {
        return collect(DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$table]
        ))->pluck('CONSTRAINT_NAME')->toArray();
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
