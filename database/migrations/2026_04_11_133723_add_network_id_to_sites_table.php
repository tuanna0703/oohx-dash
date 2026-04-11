<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add network_id column to sites
        Schema::table('sites', function (Blueprint $table) {
            $table->unsignedBigInteger('network_id')->nullable()->after('owner_id');
            $table->foreign('network_id')->references('id')->on('networks')->nullOnDelete();
            $table->index('network_id');
        });

        // 2. Data migration: infer network_id from screen_inventory of each site's screens
        DB::statement("
            UPDATE sites s
            JOIN (
                SELECT sc.site_id, si.network_id
                FROM screens sc
                JOIN screen_inventory si ON si.screen_id = sc.id
                WHERE si.network_id IS NOT NULL
                  AND sc.deleted_at IS NULL
                GROUP BY sc.site_id, si.network_id
            ) sub ON sub.site_id = s.id
            SET s.network_id = sub.network_id
            WHERE s.network_id IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropForeign(['network_id']);
            $table->dropColumn('network_id');
        });
    }
};
