<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Bảng 8 vùng kinh tế-xã hội
        Schema::create('vietnam_regions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();   // VD: TAY_BAC, DBSH
            $table->string('name', 100);             // VD: Tây Bắc
            $table->string('full_name', 150);        // VD: Vùng Tây Bắc
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });
        if (\DB::getDriverName() === 'mysql') {
            \DB::statement("ALTER TABLE vietnam_regions CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }

        // Thêm region_id vào vietnam_provinces
        Schema::table('vietnam_provinces', function (Blueprint $table) {
            $table->unsignedBigInteger('region_id')->nullable()->after('region');
            $table->foreign('region_id')->references('id')->on('vietnam_regions')->nullOnDelete();
            $table->index('region_id');
        });
    }

    public function down(): void
    {
        Schema::table('vietnam_provinces', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->dropColumn('region_id');
        });
        Schema::dropIfExists('vietnam_regions');
    }
};
