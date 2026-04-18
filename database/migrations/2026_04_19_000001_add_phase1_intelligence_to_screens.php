<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — Inventory Intelligence fields on screens.
 *
 * Adds:
 *   - placement_zone     : entrance | checkout | escalator | food_court | facade | lobby | parking | other
 *   - orientation        : landscape | portrait | square (derivable from spec if null)
 *   - daily_footfall     : ước lượng số người qua lại / ngày
 *   - monthly_reach      : ước lượng số người riêng biệt / tháng
 *   - audience_profile   : json {male_pct, female_pct, age_18_24_pct, age_25_34_pct, age_35_44_pct, age_45_plus_pct, source_note}
 *   - time_performance   : json {peak_hour_start, peak_hour_end, best_day, morning_pct, afternoon_pct, evening_pct}
 *   - nearby_context     : json {brands:[], landmarks:[], highlights:""}
 *   - traffic_methodology_note : nguồn / phương pháp ước lượng (minh bạch với agency)
 *
 * Tất cả nullable. Không động data hiện có.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screens', function (Blueprint $table) {
            $table->string('placement_zone', 32)->nullable()->after('description');
            $table->enum('orientation', ['landscape', 'portrait', 'square'])->nullable()->after('placement_zone');
            $table->unsignedInteger('daily_footfall')->nullable()->after('orientation');
            $table->unsignedInteger('monthly_reach')->nullable()->after('daily_footfall');
            $table->json('audience_profile')->nullable()->after('monthly_reach');
            $table->json('time_performance')->nullable()->after('audience_profile');
            $table->json('nearby_context')->nullable()->after('time_performance');
            $table->text('traffic_methodology_note')->nullable()->after('nearby_context');
        });
    }

    public function down(): void
    {
        Schema::table('screens', function (Blueprint $table) {
            $table->dropColumn([
                'placement_zone',
                'orientation',
                'daily_footfall',
                'monthly_reach',
                'audience_profile',
                'time_performance',
                'nearby_context',
                'traffic_methodology_note',
            ]);
        });
    }
};
