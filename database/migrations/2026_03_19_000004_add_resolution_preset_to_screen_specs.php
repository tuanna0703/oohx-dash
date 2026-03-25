<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('screen_specs', function (Blueprint $table) {
            $table->string('resolution_preset', 20)
                  ->nullable()
                  ->after('height_px')
                  ->comment('Preset key được chọn: 1080x1920, 1920x1080, custom');
        });
    }

    public function down(): void
    {
        Schema::table('screen_specs', function (Blueprint $table) {
            $table->dropColumn('resolution_preset');
        });
    }
};
