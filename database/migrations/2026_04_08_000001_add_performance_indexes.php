<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // screens — active column used in 44+ WHERE clauses
        Schema::table('screens', function (Blueprint $table) {
            $table->index('active', 'idx_screens_active');
            $table->index(['active', 'site_id'], 'idx_screens_active_site');
            $table->index(['owner_id', 'active', 'updated_at'], 'idx_screens_owner_active_updated');
        });

        // screen_inventory — venue_type filter + floor_cpm sort/aggregate
        Schema::table('screen_inventory', function (Blueprint $table) {
            $table->index(['screen_id', 'venue_type'], 'idx_sinv_screen_venue');
            $table->index(['screen_id', 'floor_cpm'], 'idx_sinv_screen_floor');
            $table->index('floor_cpm', 'idx_sinv_floor_cpm');
        });

        // screen_specs — whereHas photo_url check
        Schema::table('screen_specs', function (Blueprint $table) {
            $table->index(['screen_id', 'photo_url'], 'idx_specs_screen_photo');
        });

        // sites — city used in GROUP BY, WHERE, JOIN (15+ queries)
        Schema::table('sites', function (Blueprint $table) {
            $table->index('city', 'idx_sites_city');
        });

        // owners — status filter in every query
        Schema::table('owners', function (Blueprint $table) {
            $table->index('status', 'idx_owners_status');
            $table->index(['status', 'featured'], 'idx_owners_status_featured');
        });

        // venue_types — LEFT JOIN predicate
        Schema::table('venue_types', function (Blueprint $table) {
            $table->index('string_value', 'idx_vt_string_value');
        });
    }

    public function down(): void
    {
        Schema::table('screens', function (Blueprint $table) {
            $table->dropIndex('idx_screens_active');
            $table->dropIndex('idx_screens_active_site');
            $table->dropIndex('idx_screens_owner_active_updated');
        });

        Schema::table('screen_inventory', function (Blueprint $table) {
            $table->dropIndex('idx_sinv_screen_venue');
            $table->dropIndex('idx_sinv_screen_floor');
            $table->dropIndex('idx_sinv_floor_cpm');
        });

        Schema::table('screen_specs', function (Blueprint $table) {
            $table->dropIndex('idx_specs_screen_photo');
        });

        Schema::table('sites', function (Blueprint $table) {
            $table->dropIndex('idx_sites_city');
        });

        Schema::table('owners', function (Blueprint $table) {
            $table->dropIndex('idx_owners_status');
            $table->dropIndex('idx_owners_status_featured');
        });

        Schema::table('venue_types', function (Blueprint $table) {
            $table->dropIndex('idx_vt_string_value');
        });
    }
};
