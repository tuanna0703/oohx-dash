<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cập nhật listing_mode: per_unit/per_package/per_site → package_only/individual_only/both
 * Thêm individual_price + package_discount_pct cho partial purchase.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Đổi enum listing_mode
        DB::statement("ALTER TABLE products MODIFY COLUMN listing_mode ENUM('package_only','individual_only','both') NOT NULL DEFAULT 'individual_only'");

        // 2. Migrate data cũ
        DB::table('products')->where('listing_mode', 'per_unit')->update(['listing_mode' => 'individual_only']);
        DB::table('products')->where('listing_mode', 'per_package')->update(['listing_mode' => 'package_only']);
        DB::table('products')->where('listing_mode', 'per_site')->update(['listing_mode' => 'both']);

        // 3. Thêm cột mới
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('individual_price', 15, 2)->nullable()->after('floor_price')
                ->comment('Giá lẻ/screen/đơn vị thời gian. Null = dùng floor_price');
            $table->unsignedTinyInteger('package_discount_pct')->default(0)->after('individual_price')
                ->comment('% giảm khi mua cả gói so với mua lẻ');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['individual_price', 'package_discount_pct']);
        });

        DB::statement("ALTER TABLE products MODIFY COLUMN listing_mode ENUM('per_unit','per_package','per_site') NOT NULL DEFAULT 'per_unit'");
    }
};
