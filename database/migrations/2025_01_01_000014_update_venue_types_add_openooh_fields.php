<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venue_types', function (Blueprint $table) {
            if (! Schema::hasColumn('venue_types', 'enumeration_id')) {
                $table->unsignedInteger('enumeration_id')->nullable()->unique()->after('id');
            }
            if (! Schema::hasColumn('venue_types', 'string_value')) {
                $table->string('string_value')->nullable()->unique()->after('enumeration_id');
            }
            if (! Schema::hasColumn('venue_types', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->nullable()->after('string_value');
                $table->index('parent_id');
            }
            if (! Schema::hasColumn('venue_types', 'depth')) {
                $table->unsignedTinyInteger('depth')->default(0)->after('parent_id');
            }
            if (! Schema::hasColumn('venue_types', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('hivestack_supported');
                $table->index(['is_active', 'depth']);
            }
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
