<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('vietnam_provinces')) return;
        Schema::create('vietnam_provinces', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();   // Mã tỉnh/thành (VD: 01, 79)
            $table->string('name', 100);             // Tên ngắn (VD: Hà Nội)
            $table->string('full_name', 150);        // Tên đầy đủ (VD: Thành phố Hà Nội)
            $table->enum('type', ['tinh', 'thanh_pho'])->default('tinh');
            $table->string('region', 60)->nullable(); // Vùng kinh tế
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vietnam_provinces');
    }
};
