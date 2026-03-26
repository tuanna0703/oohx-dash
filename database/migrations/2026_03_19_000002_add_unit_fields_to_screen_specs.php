<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('screen_specs', function (Blueprint $table) {
            if (! Schema::hasColumn('screen_specs', 'width_unit')) {
                $table->string('width_unit', 5)->default('cm')->after('width_cm');
            }
            if (! Schema::hasColumn('screen_specs', 'height_unit')) {
                $table->string('height_unit', 5)->default('cm')->after('height_cm');
            }
        });
    }

    public function down(): void
    {
        Schema::table('screen_specs', function (Blueprint $table) {
            $table->dropColumn(['width_unit', 'height_unit']);
        });
    }
};
