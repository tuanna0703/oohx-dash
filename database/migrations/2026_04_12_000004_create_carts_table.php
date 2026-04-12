<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->ulid('organization_id')->nullable();
            $table->string('name')->nullable();
            $table->enum('status', ['active', 'converted', 'expired'])->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
            $table->index(['user_id', 'status']);
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->ulid('cart_id');
            $table->ulid('screen_id');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('spot_length')->default(15);
            $table->unsignedTinyInteger('share_of_voice_pct')->default(100);
            $table->unsignedInteger('estimated_impressions')->default(0);
            $table->decimal('estimated_cost', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('cart_id')->references('id')->on('carts')->cascadeOnDelete();
            $table->foreign('screen_id')->references('id')->on('screens')->cascadeOnDelete();
            $table->unique(['cart_id', 'screen_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
