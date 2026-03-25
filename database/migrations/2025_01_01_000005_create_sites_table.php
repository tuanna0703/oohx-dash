<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sites', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('owner_id');
            $table->string('external_id', 75);
            $table->string('hivestack_site_id')->nullable();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lon', 10, 7)->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('country', 2)->default('VN');
            $table->enum('status', ['active','paused','closed'])->default('active');
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('owner_id')->references('id')->on('owners')->cascadeOnDelete();
            $table->unique(['owner_id','external_id']);
            $table->index(['owner_id','status']);
            $table->index(['lat','lon']);
        });
    }
    public function down(): void { Schema::dropIfExists('sites'); }
};
