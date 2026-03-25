<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('screen_impression_multipliers', function (Blueprint $table) {
            $table->id();
            $table->ulid('screen_id');
            $table->unsignedTinyInteger('day_of_week');
            $table->unsignedTinyInteger('hour_of_day');
            $table->decimal('multiplier', 5, 2)->default(1.00);
            $table->timestamps();
            $table->foreign('screen_id')->references('id')->on('screens')->cascadeOnDelete();
            $table->unique(['screen_id','day_of_week','hour_of_day'], 'sim_screen_day_hour_unique');
        });
    }
    public function down(): void { Schema::dropIfExists('screen_impression_multipliers'); }
};
