<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add dual pricing model support: CPM + I/O Booking.
 *
 * CPM:  floor_cpm = giá/CPM (VNĐ per 1000 impressions)
 * I/O:  io_rate = giá/màn hình/tuần hoặc tháng, với KPI spots
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── screen_inventory: pricing model + I/O fields ──
        Schema::table('screen_inventory', function (Blueprint $table) {
            $table->enum('pricing_model', ['cpm', 'io'])->default('io')->after('programmatic_enabled');
            $table->decimal('io_rate', 15, 2)->nullable()->after('pricing_model');
            $table->enum('io_rate_unit', ['week', 'month'])->default('month')->after('io_rate');
            $table->unsignedSmallInteger('io_kpi_spots_per_day')->nullable()->after('io_rate_unit');
        });

        // ── cart_items: pricing snapshot ──
        Schema::table('cart_items', function (Blueprint $table) {
            $table->enum('pricing_model', ['cpm', 'io'])->default('io')->after('notes');
            $table->unsignedInteger('booked_cpms')->nullable()->after('pricing_model');
            $table->unsignedInteger('screen_count')->default(1)->after('booked_cpms');
            $table->unsignedInteger('duration_units')->default(1)->after('screen_count');
            $table->enum('duration_unit', ['week', 'month'])->default('month')->after('duration_units');
            $table->decimal('unit_price', 15, 2)->default(0)->after('duration_unit');
        });

        // ── booking_lines: pricing freeze + actuals ──
        Schema::table('booking_lines', function (Blueprint $table) {
            $table->enum('pricing_model', ['cpm', 'io'])->default('io')->after('notes');
            $table->decimal('io_rate_at_booking', 15, 2)->nullable()->after('pricing_model');
            $table->enum('io_rate_unit', ['week', 'month'])->nullable()->after('io_rate_at_booking');
            $table->unsignedSmallInteger('kpi_spots_per_day')->nullable()->after('io_rate_unit');
            $table->unsignedInteger('booked_cpms')->nullable()->after('kpi_spots_per_day');
            $table->unsignedInteger('screen_count')->default(1)->after('booked_cpms');
            $table->unsignedInteger('actual_spots')->default(0)->after('actual_cost');
        });
    }

    public function down(): void
    {
        Schema::table('screen_inventory', function (Blueprint $table) {
            $table->dropColumn(['pricing_model', 'io_rate', 'io_rate_unit', 'io_kpi_spots_per_day']);
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn(['pricing_model', 'booked_cpms', 'screen_count', 'duration_units', 'duration_unit', 'unit_price']);
        });

        Schema::table('booking_lines', function (Blueprint $table) {
            $table->dropColumn(['pricing_model', 'io_rate_at_booking', 'io_rate_unit', 'kpi_spots_per_day', 'booked_cpms', 'screen_count', 'actual_spots']);
        });
    }
};
