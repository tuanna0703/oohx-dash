<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('screen_imports', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->ulid('owner_id');
            $t->foreignId('uploaded_by')->constrained('users');

            $t->string('original_filename');
            $t->string('file_path');

            // uploaded → mapping → previewed → importing → done | failed | cancelled
            $t->string('status')->default('uploaded')->index();
            $t->string('upsert_mode')->default('skip'); // skip | update

            $t->integer('total_rows')->nullable();
            $t->integer('processed_count')->default(0);
            $t->integer('success_count')->default(0);
            $t->integer('failed_count')->default(0);

            // Spreadsheet analysis
            $t->json('headers')->nullable();
            $t->json('sample_rows')->nullable();

            // AI mapping (original proposal) vs user_mapping (after edits)
            $t->json('ai_mapping')->nullable();
            $t->json('user_mapping')->nullable();

            // Phase 2 — conversation trail of natural-language refinements
            $t->json('ai_comment_history')->nullable();

            // Dry-run preview + errors
            $t->json('preview_data')->nullable();
            $t->json('validation_errors')->nullable();
            $t->text('error_summary')->nullable();

            // Phase 3 — generated error report file
            $t->string('error_report_path')->nullable();

            $t->timestamp('started_at')->nullable();
            $t->timestamp('finished_at')->nullable();
            $t->timestamps();

            $t->foreign('owner_id')->references('id')->on('owners')->cascadeOnDelete();
            $t->index(['owner_id', 'status']);
            $t->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('screen_imports');
    }
};
