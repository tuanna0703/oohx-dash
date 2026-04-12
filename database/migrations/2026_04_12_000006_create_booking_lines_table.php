<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('campaign_id');
            $table->ulid('screen_id');
            $table->ulid('owner_id'); // denormalized for fast owner queries
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('spot_length')->default(15);
            $table->unsignedTinyInteger('share_of_voice_pct')->default(100);
            $table->decimal('floor_cpm_at_booking', 15, 2)->default(0);
            $table->decimal('negotiated_cpm', 15, 2)->nullable();
            $table->unsignedInteger('estimated_impressions')->default(0);
            $table->decimal('estimated_cost', 15, 2)->default(0);
            $table->unsignedInteger('actual_impressions')->default(0);
            $table->decimal('actual_cost', 15, 2)->default(0);
            $table->enum('status', [
                'pending', 'approved', 'rejected',
                'active', 'paused', 'completed', 'cancelled',
            ])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejected_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
            $table->foreign('screen_id')->references('id')->on('screens')->cascadeOnDelete();
            $table->foreign('owner_id')->references('id')->on('owners')->cascadeOnDelete();
            $table->index(['campaign_id', 'status']);
            $table->index(['owner_id', 'status']);
            $table->index(['screen_id', 'start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_lines');
    }
};
