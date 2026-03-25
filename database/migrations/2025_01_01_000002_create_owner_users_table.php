<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('owner_users', function (Blueprint $table) {
            $table->id();
            $table->ulid('owner_id');
            $table->unsignedBigInteger('user_id');
            $table->enum('role', ['admin','manager','viewer','finance'])->default('viewer');
            $table->json('allowed_network_ids')->nullable();
            $table->timestamps();
            $table->foreign('owner_id')->references('id')->on('owners')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['owner_id','user_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('owner_users'); }
};
