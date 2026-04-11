<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screen_specs', function (Blueprint $table) {
            $table->json('photos')->nullable()->after('photo_url');
        });

        // Migrate existing photo_url to photos array
        DB::table('screen_specs')
            ->whereNotNull('photo_url')
            ->where('photo_url', '!=', '')
            ->update([
                'photos' => DB::raw("JSON_ARRAY(photo_url)"),
            ]);
    }

    public function down(): void
    {
        Schema::table('screen_specs', function (Blueprint $table) {
            $table->dropColumn('photos');
        });
    }
};
