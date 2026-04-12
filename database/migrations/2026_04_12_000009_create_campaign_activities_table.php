<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_activities', function (Blueprint $table) {
            $table->id();
            $table->ulid('campaign_id');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action'); // created, submitted, approved, rejected, etc.
            $table->string('description');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');

            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
            $table->index(['campaign_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_activities');
    }
};
