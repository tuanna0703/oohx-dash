<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Add slug to screens
        Schema::table('screens', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
            $table->index('slug');
        });

        // Add slug to sites
        Schema::table('sites', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
            $table->index('slug');
        });

        // Backfill existing screens
        DB::table('screens')->whereNull('slug')->orderBy('id')->each(function ($screen) {
            $base = Str::slug($screen->name, '-', 'vi') ?: 'screen-' . Str::random(6);
            $base = Str::limit($base, 120, '');
            $slug = $base;
            $count = 1;
            while (DB::table('screens')->where('slug', $slug)->where('id', '!=', $screen->id)->exists()) {
                $count++;
                $slug = $base . '-' . $count;
            }
            DB::table('screens')->where('id', $screen->id)->update(['slug' => $slug]);
        });

        // Backfill existing sites
        DB::table('sites')->whereNull('slug')->orderBy('id')->each(function ($site) {
            $base = Str::slug($site->name, '-', 'vi') ?: 'site-' . Str::random(6);
            $base = Str::limit($base, 120, '');
            $slug = $base;
            $count = 1;
            while (DB::table('sites')->where('slug', $slug)->where('id', '!=', $site->id)->exists()) {
                $count++;
                $slug = $base . '-' . $count;
            }
            DB::table('sites')->where('id', $site->id)->update(['slug' => $slug]);
        });

        // Make unique after backfill
        Schema::table('screens', function (Blueprint $table) {
            $table->unique('slug');
        });
        Schema::table('sites', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('screens', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
        Schema::table('sites', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
