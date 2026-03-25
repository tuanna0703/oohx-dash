<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('screen_inventory', function (Blueprint $table) {
            $table->id();
            $table->ulid('screen_id')->unique();
            $table->unsignedBigInteger('network_id')->nullable();
            $table->string('network_name')->nullable();
            $table->string('venue_type')->nullable();
            $table->unsignedSmallInteger('spot_length')->default(15);
            $table->unsignedSmallInteger('max_spot_length')->default(30);
            $table->unsignedSmallInteger('min_spot_length')->default(5);
            $table->unsignedSmallInteger('loop_length')->nullable();
            $table->unsignedInteger('weekly_impressions')->nullable();
            $table->decimal('floor_cpm', 15, 2)->nullable();
            $table->string('floor_cpm_currency', 3)->default('VND');
            $table->decimal('floor_cpm_usd', 10, 4)->nullable();
            $table->json('operating_hours')->nullable();
            $table->string('timezone', 50)->default('Asia/Ho_Chi_Minh');
            $table->boolean('programmatic_enabled')->default(false);
            $table->boolean('pmp_only')->default(false);
            $table->unsignedTinyInteger('share_of_voice_max_pct')->default(100);
            $table->timestamps();
            $table->foreign('screen_id')->references('id')->on('screens')->cascadeOnDelete();
            $table->foreign('network_id')->references('id')->on('networks')->nullOnDelete();
            $table->index(['programmatic_enabled','venue_type']);
        });
    }
    public function down(): void { Schema::dropIfExists('screen_inventory'); }
};
