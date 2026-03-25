<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('screen_specs', function (Blueprint $table) {
            $table->id();
            $table->ulid('screen_id')->unique();
            $table->unsignedSmallInteger('width_px');
            $table->unsignedSmallInteger('height_px');
            $table->decimal('width_cm', 6, 1)->nullable();
            $table->decimal('height_cm', 6, 1)->nullable();
            $table->enum('facing_direction', ['N','E','S','W','NE','SE','SW','NW','Rotating'])->nullable();
            $table->string('photo_url')->nullable();
            $table->boolean('allow_image')->default(true);
            $table->boolean('allow_video')->default(true);
            $table->boolean('allow_html')->default(false);
            $table->boolean('allow_zip')->default(false);
            $table->boolean('allow_vast')->default(true);
            $table->json('allowed_languages')->nullable();
            $table->timestamps();
            $table->foreign('screen_id')->references('id')->on('screens')->cascadeOnDelete();
        });
    }
    public function down(): void { Schema::dropIfExists('screen_specs'); }
};
