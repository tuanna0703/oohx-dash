<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('organization_id');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('code')->unique(); // CPN-YYYYMM-XXXX
            $table->string('name');
            $table->string('brand_name')->nullable();
            $table->string('category')->nullable();
            $table->json('objectives')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_budget', 15, 2)->nullable();
            $table->string('currency', 3)->default('VND');
            $table->unsignedInteger('total_screens')->default(0);
            $table->unsignedInteger('total_impressions_estimated')->default(0);
            $table->enum('status', [
                'draft', 'pending_approval', 'approved', 'rejected',
                'active', 'paused', 'completed', 'cancelled',
            ])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->index(['organization_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
