<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cập nhật cột role trong owner_users:
     * OLD: enum('admin','manager','viewer','finance')
     * NEW: enum('owner','manager','scheduler','read_only','reporting_only','sales_manager')
     *
     * Map cũ → mới:
     *   admin   → owner
     *   manager → manager
     *   viewer  → read_only
     *   finance → reporting_only
     */
    public function up(): void
    {
        // MySQL không ALTER ENUM trực tiếp khi có data → đổi sang varchar tạm, migrate data, đổi lại
        Schema::table('owner_users', function (Blueprint $table) {
            $table->string('role_tmp', 30)->default('read_only')->after('role');
        });

        // Map data cũ sang role mới
        DB::table('owner_users')->update([
            'role_tmp' => DB::raw("CASE role
                WHEN 'admin'   THEN 'owner'
                WHEN 'manager' THEN 'manager'
                WHEN 'viewer'  THEN 'read_only'
                WHEN 'finance' THEN 'reporting_only'
                ELSE 'read_only'
            END"),
        ]);

        Schema::table('owner_users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('owner_users', function (Blueprint $table) {
            $table->enum('role', [
                'owner',
                'manager',
                'scheduler',
                'read_only',
                'reporting_only',
                'sales_manager',
            ])->default('read_only')->after('role_tmp');
        });

        DB::table('owner_users')->update(['role' => DB::raw('role_tmp')]);

        Schema::table('owner_users', function (Blueprint $table) {
            $table->dropColumn('role_tmp');
        });
    }

    public function down(): void
    {
        Schema::table('owner_users', function (Blueprint $table) {
            $table->string('role_tmp', 30)->default('viewer')->after('role');
        });

        DB::table('owner_users')->update([
            'role_tmp' => DB::raw("CASE role
                WHEN 'owner'           THEN 'admin'
                WHEN 'manager'         THEN 'manager'
                WHEN 'scheduler'       THEN 'manager'
                WHEN 'read_only'       THEN 'viewer'
                WHEN 'reporting_only'  THEN 'finance'
                WHEN 'sales_manager'   THEN 'manager'
                ELSE 'viewer'
            END"),
        ]);

        Schema::table('owner_users', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('owner_users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'manager', 'viewer', 'finance'])
                ->default('viewer')->after('role_tmp');
        });

        DB::table('owner_users')->update(['role' => DB::raw('role_tmp')]);

        Schema::table('owner_users', function (Blueprint $table) {
            $table->dropColumn('role_tmp');
        });
    }
};
