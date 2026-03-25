<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_types', function (Blueprint $table) {
            // OpenOOH standard fields
            $table->unsignedInteger('enumeration_id')->nullable()->unique()->after('id');
            $table->string('string_value')->nullable()->unique()->after('enumeration_id');

            // Hierarchy support
            $table->unsignedBigInteger('parent_id')->nullable()->after('string_value');
            $table->unsignedTinyInteger('depth')->default(0)->after('parent_id');

            // Visibility control
            $table->boolean('is_active')->default(true)->after('hivestack_supported');

            $table->index('parent_id');
            $table->index(['is_active', 'depth']);
        });
    }

    public function down(): void
    {
        Schema::table('venue_types', function (Blueprint $table) {
            $table->dropColumn([
                'enumeration_id', 'string_value',
                'parent_id', 'depth', 'is_active',
            ]);
        });
    }
};
