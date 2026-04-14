<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            $table->unsignedBigInteger('province_id')->nullable()->after('district');
            $table->unsignedBigInteger('commune_id')->nullable()->after('province_id');

            $table->foreign('province_id')->references('id')->on('vietnam_provinces')->nullOnDelete();
            $table->foreign('commune_id')->references('id')->on('vietnam_communes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            $table->dropForeign(['province_id']);
            $table->dropForeign(['commune_id']);
            $table->dropColumn(['province_id', 'commune_id']);
        });
    }
};
