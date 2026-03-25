<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('screen_external_ids', function (Blueprint $table) {
            $table->id();
            $table->ulid('screen_id');
            $table->enum('platform', ['hivestack','vistar','broadsign','oohmedia','place_exchange','viooh']);
            $table->string('external_id', 100);
            $table->uuid('external_uuid')->nullable();
            $table->enum('sync_status', ['pending','synced','error'])->default('pending');
            $table->text('sync_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->foreign('screen_id')->references('id')->on('screens')->cascadeOnDelete();
            $table->unique(['screen_id','platform']);
            $table->index(['platform','external_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('screen_external_ids'); }
};
