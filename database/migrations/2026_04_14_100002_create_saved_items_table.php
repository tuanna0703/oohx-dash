<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->ulid('screen_id');
            $table->timestamps();

            $table->foreign('screen_id')->references('id')->on('screens')->cascadeOnDelete();
            $table->unique(['user_id', 'screen_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_items');
    }
};
