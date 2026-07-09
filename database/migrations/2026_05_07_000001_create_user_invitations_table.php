<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_invitations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('email', 255);
            $table->string('tenant_type', 20); // 'owner' | 'organization'
            $table->string('tenant_id', 26);   // ulid của Owner / Organization
            $table->string('role', 32);
            $table->json('allowed_network_ids')->nullable();
            $table->string('token', 64)->unique();
            $table->foreignId('invited_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['email', 'tenant_type', 'tenant_id'], 'invitations_email_tenant_idx');
            $table->index(['tenant_type', 'tenant_id'], 'invitations_tenant_idx');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_invitations');
    }
};
