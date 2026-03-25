<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // screens: compound index for sorting by name per owner
        Schema::table('screens', function (Blueprint $table) {
            $table->index(['owner_id', 'name'], 'screens_owner_name_idx');
            $table->index(['owner_id', 'site_id'], 'screens_owner_site_idx');
            $table->index(['owner_id', 'deleted_at'], 'screens_owner_deleted_idx');
        });

        // screen_inventory: index for filter queries using whereIn
        Schema::table('screen_inventory', function (Blueprint $table) {
            $table->index('network_id', 'screen_inventory_network_idx');
            $table->index('venue_type', 'screen_inventory_venue_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('screens', function (Blueprint $table) {
            $table->dropIndex('screens_owner_name_idx');
            $table->dropIndex('screens_owner_site_idx');
            $table->dropIndex('screens_owner_deleted_idx');
        });

        Schema::table('screen_inventory', function (Blueprint $table) {
            $table->dropIndex('screen_inventory_network_idx');
            $table->dropIndex('screen_inventory_venue_type_idx');
        });
    }
};
