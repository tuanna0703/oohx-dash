<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $cols = Schema::getColumnListing('sites');
            if (! in_array('province_id', $cols)) {
                $table->unsignedBigInteger('province_id')->nullable()->after('country');
                $table->foreign('province_id')->references('id')->on('vietnam_provinces')->nullOnDelete();
                $table->index('province_id');
            }
            if (! in_array('commune_id', $cols)) {
                $table->unsignedBigInteger('commune_id')->nullable()->after('province_id');
                $table->foreign('commune_id')->references('id')->on('vietnam_communes')->nullOnDelete();
                $table->index('commune_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropForeign(['province_id']);
            $table->dropForeign(['commune_id']);
            $table->dropColumn(['province_id', 'commune_id']);
        });
    }
};
