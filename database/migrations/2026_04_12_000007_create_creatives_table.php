<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creatives', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('campaign_id');
            $table->ulid('organization_id');
            $table->string('name');
            $table->enum('type', ['image', 'video', 'html5', 'vast_tag'])->default('image');
            $table->string('file_path')->nullable();
            $table->unsignedInteger('file_size')->nullable(); // bytes
            $table->unsignedInteger('width_px')->nullable();
            $table->unsignedInteger('height_px')->nullable();
            $table->unsignedInteger('duration_sec')->nullable();
            $table->string('vast_tag_url')->nullable();
            $table->enum('status', ['pending_review', 'approved', 'rejected'])->default('pending_review');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('campaign_id')->references('id')->on('campaigns')->cascadeOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
        });

        Schema::create('booking_line_creatives', function (Blueprint $table) {
            $table->id();
            $table->ulid('booking_line_id');
            $table->ulid('creative_id');
            $table->unsignedTinyInteger('weight')->default(100); // rotation weight 1-100
            $table->date('start_date')->nullable(); // override
            $table->date('end_date')->nullable();   // override

            $table->foreign('booking_line_id')->references('id')->on('booking_lines')->cascadeOnDelete();
            $table->foreign('creative_id')->references('id')->on('creatives')->cascadeOnDelete();
            $table->unique(['booking_line_id', 'creative_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_line_creatives');
        Schema::dropIfExists('creatives');
    }
};
