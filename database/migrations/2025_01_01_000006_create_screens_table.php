<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('screens', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('site_id');
            $table->ulid('owner_id');
            $table->string('external_id', 75);
            $table->uuid('uuid')->unique();
            $table->string('unit_id')->nullable();
            $table->string('name', 199);
            $table->text('description')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('site_external_id')->nullable();
            $table->boolean('active')->default(true);
            $table->enum('status', ['online','offline','maintenance'])->default('offline');
            $table->enum('player_type', ['adtrue_android','adtrue_webview','third_party','vast_only'])->default('adtrue_android');
            $table->string('player_version')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->string('device_token')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('site_id')->references('id')->on('sites')->cascadeOnDelete();
            $table->foreign('owner_id')->references('id')->on('owners');
            $table->unique(['owner_id','external_id']);
            $table->index(['owner_id','active','status']);
            $table->index('last_heartbeat_at');
        });
    }
    public function down(): void { Schema::dropIfExists('screens'); }
};
