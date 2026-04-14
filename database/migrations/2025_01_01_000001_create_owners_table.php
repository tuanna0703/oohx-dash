<?php
// ============================================================
// OOHX — Database Migrations (run in order)
// Copy mỗi class vào file riêng trong database/migrations/
// ============================================================

// FILE: 2025_01_01_000001_create_owners_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('owners', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('type', ['retailer','media_owner','self'])->default('media_owner');
            $table->enum('onboard_method', ['cms','api','vast','hardware'])->default('cms');
            $table->decimal('revenue_share_pct', 5, 2)->default(70.00);
            $table->enum('status', ['pending','active','suspended'])->default('pending');
            $table->json('billing_info')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
    public function down(): void { Schema::dropIfExists('owners'); }
};
