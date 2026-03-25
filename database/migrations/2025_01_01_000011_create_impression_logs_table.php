<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('impression_logs', function (Blueprint $table) {
            $table->ulid('id');
            $table->ulid('screen_id');
            $table->ulid('owner_id');
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->unsignedBigInteger('creative_id')->nullable();
            $table->timestamp('played_at')->useCurrent();
            $table->unsignedSmallInteger('duration_sec');
            $table->decimal('multiplier_applied', 5, 2)->default(1.00);
            $table->decimal('imp_count', 10, 2);
            $table->enum('deal_type', ['direct','rtb','pmp'])->default('direct');
            $table->decimal('cpm_charged', 10, 4)->nullable();
            $table->decimal('revenue_gross', 10, 4)->nullable();
            $table->decimal('revenue_owner', 10, 4)->nullable();
            $table->string('proof_url')->nullable();
            $table->enum('source', ['adtrue_player','vast_ping','manual','api'])->default('adtrue_player');
            $table->timestamp('created_at')->useCurrent();

            // Composite PK — bắt buộc khi dùng PARTITION BY RANGE(played_at)
            $table->primary(['id', 'played_at']);

            $table->index(['screen_id', 'played_at']);
            $table->index(['owner_id', 'played_at']);
            $table->index(['campaign_id', 'played_at']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("
            ALTER TABLE impression_logs
            PARTITION BY RANGE (UNIX_TIMESTAMP(played_at)) (
                PARTITION p2025_q1 VALUES LESS THAN (UNIX_TIMESTAMP('2025-04-01 00:00:00')),
                PARTITION p2025_q2 VALUES LESS THAN (UNIX_TIMESTAMP('2025-07-01 00:00:00')),
                PARTITION p2025_q3 VALUES LESS THAN (UNIX_TIMESTAMP('2025-10-01 00:00:00')),
                PARTITION p2025_q4 VALUES LESS THAN (UNIX_TIMESTAMP('2026-01-01 00:00:00')),
                PARTITION p2026_q1 VALUES LESS THAN (UNIX_TIMESTAMP('2026-04-01 00:00:00')),
                PARTITION p_future  VALUES LESS THAN MAXVALUE
            )
        ");
        }
    }
    public function down(): void { Schema::dropIfExists('impression_logs'); }
};
