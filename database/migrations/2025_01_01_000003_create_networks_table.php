<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('networks', function (Blueprint $table) {
            $table->id();
            $table->ulid('owner_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('default_floor_cpm', 15, 2)->nullable();
            $table->string('default_floor_cpm_currency', 3)->default('VND');
            $table->enum('status', ['active','paused'])->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('owner_id')->references('id')->on('owners')->cascadeOnDelete();
            $table->index(['owner_id','status']);
        });
    }
    public function down(): void { Schema::dropIfExists('networks'); }
};
