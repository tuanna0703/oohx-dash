<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Add vn_category_id to networks table.
 * Each network belongs to a global venue category (Mall, Retail, Roadside, etc.)
 * This enables global filtering: "show all Shopping Mall networks across all owners".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('networks', function (Blueprint $table) {
            $table->unsignedTinyInteger('vn_category_id')->nullable()->after('status');
            $table->foreign('vn_category_id')->references('id')->on('venue_categories')->nullOnDelete();
        });

        // Backfill: set network's vn_category_id from the most common category of its screens
        DB::statement("
            UPDATE networks n
            SET n.vn_category_id = (
                SELECT si.vn_category_id
                FROM sites s
                JOIN screens sc ON sc.site_id = s.id AND sc.deleted_at IS NULL
                JOIN screen_inventory si ON si.screen_id = sc.id AND si.vn_category_id IS NOT NULL
                WHERE s.network_id = n.id AND s.deleted_at IS NULL
                GROUP BY si.vn_category_id
                ORDER BY COUNT(*) DESC
                LIMIT 1
            )
            WHERE n.deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('networks', function (Blueprint $table) {
            $table->dropForeign(['vn_category_id']);
            $table->dropColumn('vn_category_id');
        });
    }
};
