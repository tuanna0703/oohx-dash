<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('vietnam_communes')) return;
        Schema::create('vietnam_communes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();   // Mã xã/phường (VD: 00001)
            $table->string('name', 150);             // Tên ngắn (VD: Phúc Xá)
            $table->string('full_name', 200);        // Tên đầy đủ (VD: Phường Phúc Xá)
            $table->enum('type', ['xa', 'phuong', 'thi_tran'])->default('xa');
            $table->unsignedBigInteger('province_id');
            $table->foreign('province_id')->references('id')->on('vietnam_provinces')->cascadeOnDelete();
            $table->timestamps();

            $table->index('province_id');
            $table->index(['province_id', 'name']);
        });
        if (\DB::getDriverName() === 'mysql') {
            \DB::statement("ALTER TABLE vietnam_communes CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vietnam_communes');
    }
};
