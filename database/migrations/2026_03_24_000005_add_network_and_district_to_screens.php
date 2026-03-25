<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('screens', function (Blueprint $table) {
            $table->string('network_code', 100)->nullable()->after('site_external_id');
            $table->string('location_district', 255)->nullable()->after('network_code');
            $table->string('location_district_code', 100)->nullable()->after('location_district');
            $table->index('network_code');
            $table->index('location_district_code');
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
