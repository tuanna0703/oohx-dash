<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('screen_inventory', function (Blueprint $table) {
            $table->boolean('ad_server_enabled')
                  ->default(true)
                  ->after('pmp_only')
                  ->comment('Cho phép phục vụ direct/ad server campaigns');
            $table->boolean('deals_enabled')
                  ->default(true)
                  ->after('ad_server_enabled')
                  ->comment('Cho phép phục vụ PMP deals');
        });
    }

    public function down(): void
    {
        Schema::table('screen_inventory', function (Blueprint $table) {
            $table->dropColumn(['ad_server_enabled', 'deals_enabled']);
        });
    }
};
