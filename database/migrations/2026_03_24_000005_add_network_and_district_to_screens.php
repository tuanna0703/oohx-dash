<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('screens', function (Blueprint $table) {
            if (! Schema::hasColumn('screens', 'network_code')) {
                $table->string('network_code', 100)->nullable()->after('site_external_id');
            }
            if (! Schema::hasColumn('screens', 'location_district')) {
                $table->string('location_district', 255)->nullable()->after('network_code');
            }
            if (! Schema::hasColumn('screens', 'location_district_code')) {
                $table->string('location_district_code', 100)->nullable()->after('location_district');
            }
        });

        $screenIndexes = collect(\DB::select("SHOW INDEX FROM screens"))->pluck('Key_name');
        Schema::table('screens', function (Blueprint $table) use ($screenIndexes) {
            if (! $screenIndexes->contains('screens_network_code_index')) {
                $table->index('network_code');
            }
            if (! $screenIndexes->contains('screens_location_district_code_index')) {
                $table->index('location_district_code');
            }
        });
    }
    public function down(): void {
        Schema::table('screens', function (Blueprint $table) {
            $table->dropIndex(['network_code']);
            $table->dropIndex(['location_district_code']);
            $table->dropColumn(['network_code', 'location_district', 'location_district_code']);
        });
    }
};
