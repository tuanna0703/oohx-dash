<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('type', ['agency', 'client', 'brand'])->default('client');
            $table->string('tax_id')->nullable();
            $table->text('billing_address')->nullable();
            $table->string('billing_email')->nullable();
            $table->string('billing_phone', 30)->nullable();
            $table->string('logo_url')->nullable();
            $table->string('website')->nullable();
            $table->enum('status', ['active', 'suspended'])->default('active');
            $table->decimal('credit_limit', 15, 2)->nullable();
            $table->unsignedInteger('payment_terms_days')->default(30);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
