<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('venue_types', function (Blueprint $table) {
            $table->id();
            $table->string('category');
            $table->string('subcategory')->nullable();
            $table->string('venue_type')->unique();
            $table->text('description')->nullable();
            $table->boolean('hivestack_supported')->default(true);
            $table->timestamps();
            $table->index('category');
        });
    }
    public function down(): void { Schema::dropIfExists('venue_types'); }
};
