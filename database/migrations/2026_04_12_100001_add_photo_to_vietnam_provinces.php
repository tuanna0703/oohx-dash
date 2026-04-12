<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vietnam_provinces', function (Blueprint $table) {
            $table->string('photo_url')->nullable()->after('region');
        });
    }

    public function down(): void
    {
        Schema::table('vietnam_provinces', function (Blueprint $table) {
            $table->dropColumn('photo_url');
        });
    }
};
