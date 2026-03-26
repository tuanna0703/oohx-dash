<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $screenIndexes = collect(\DB::select("SHOW INDEX FROM screens"))->pluck('Key_name');
        Schema::table('screens', function (Blueprint $table) use ($screenIndexes) {
            if (! $screenIndexes->contains('screens_owner_name_idx')) {
                $table->index(['owner_id', 'name'], 'screens_owner_name_idx');
            }
            if (! $screenIndexes->contains('screens_owner_site_idx')) {
                $table->index(['owner_id', 'site_id'], 'screens_owner_site_idx');
            }
            if (! $screenIndexes->contains('screens_owner_deleted_idx')) {
                $table->index(['owner_id', 'deleted_at'], 'screens_owner_deleted_idx');
            }
        });

        $inventoryIndexes = collect(\DB::select("SHOW INDEX FROM screen_inventory"))->pluck('Key_name');
        Schema::table('screen_inventory', function (Blueprint $table) use ($inventoryIndexes) {
            if (! $inventoryIndexes->contains('screen_inventory_network_idx')) {
                $table->index('network_id', 'screen_inventory_network_idx');
            }
            if (! $inventoryIndexes->contains('screen_inventory_venue_type_idx')) {
                $table->index('venue_type', 'screen_inventory_venue_type_idx');
            }
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
