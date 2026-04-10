<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_categories', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('name');            // "Retail", "Mall", "Transit"...
            $table->string('slug')->unique();  // "retail", "mall", "transit"...
            $table->string('name_vi');         // "Bán lẻ", "Trung tâm thương mại"...
            $table->string('description')->nullable();
            $table->string('icon')->nullable(); // Material icon name
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('venue_types', function (Blueprint $table) {
            $table->unsignedTinyInteger('vn_category_id')->nullable()->after('is_active');
            $table->foreign('vn_category_id')->references('id')->on('venue_categories')->nullOnDelete();
            $table->index('vn_category_id');
        });
    }

    public function down(): void
    {
        Schema::table('venue_types', function (Blueprint $table) {
            $table->dropForeign(['vn_category_id']);
            $table->dropColumn('vn_category_id');
        });
        Schema::dropIfExists('venue_categories');
    }
};
