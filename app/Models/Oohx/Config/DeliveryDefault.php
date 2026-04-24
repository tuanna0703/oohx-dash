<?php

namespace App\Models\Oohx\Config;

use Illuminate\Database\Eloquent\Model;

/**
 * config.delivery_defaults — visibility, direction, SOV, dwell, caps used khi screen
 * không có override riêng trong core.screen_delivery_settings.
 *
 * PK = key (string from KEYS const).
 *
 * CHECK constraint history:
 *   Phase 2.A: value >= 0 AND <= 100 (only factors)
 *   Phase 4.2.1 (migration 014): widened to <= 1,000,000 — accommodate
 *     campaign_reach_base_per_cell, population_density_baseline_per_km2
 *     (numeric constants vs 0..1 factors).
 */
class DeliveryDefault extends Model
{
    protected $connection   = 'oohx_control';
    protected $table        = 'config.delivery_defaults';
    protected $primaryKey   = 'key';
    public    $incrementing = false;
    protected $keyType      = 'string';
    public    $timestamps   = false;

    protected $fillable = ['key', 'value', 'description', 'updated_by', 'updated_at'];

    protected $casts = [
        'value'      => 'float',
        'updated_at' => 'datetime',
    ];

    /**
     * Whitelist key values Data Engine recognise. Tham khảo seed-config-from-code
     * trên DE side. Form có thể restrict create chỉ trong list này.
     *
     * Phase 4.1 + 4.2.1 added 6 keys for campaign reach + population factor.
     */
    public const KEYS = [
        // Phase 2.A original — visibility / direction / SOV / caps
        'visibility_outdoor',
        'visibility_indoor',
        'direction_outdoor_one',
        'direction_outdoor_two',
        'direction_indoor',
        'share_of_voice',
        'dwell_factor',
        'lane_factor_cap',
        'poi_factor_cap',

        // Phase 4.1 — Campaign Planner reach formula
        'campaign_reach_base_per_cell',          // 50-5000, fallback when no density
        'campaign_reach_saturation_cap',         // 1-5, max duration multiplier
        'campaign_reach_capture_rate',           // 0.01-0.5, % population see daily

        // Phase 4.2.1 — population factor (HRSL-driven traffic estimate)
        'population_density_baseline_per_km2',   // 1000-50000, urban baseline
        'population_factor_min',                 // 0.1-1.0, rural floor
        'population_factor_max',                 // 1.0-5.0, dense core cap

        // Phase 4.2.2 — venue footfall multi-provider
        'venue_footfall_enable_foursquare',        // 0/1 toggle
        'venue_footfall_enable_osm',               // 0/1 toggle
        'venue_footfall_enable_google',            // 0/1 (flip khi OOHX đăng ký xong)
        'venue_footfall_priority_foursquare',      // 1-99 chain order
        'venue_footfall_priority_osm',             // 1-99 (default 99 = fallback)
        'venue_footfall_priority_google',          // 1-99
        'venue_footfall_cache_ttl_days',           // 1-365
        'venue_footfall_min_confidence',           // 0-1
        'venue_footfall_radius_m',                 // 50-1000
        'venue_footfall_foursquare_daily_budget',  // calls/day cap
        'venue_footfall_foursquare_cost_per_call_usd', // cost tracking
    ];

    /**
     * Per-key validation hint — used by form helperText + range guard.
     * Returns [min, max, label].
     *
     * @return array{0: float, 1: float, 2: string}
     */
    public static function rangeFor(string $key): array
    {
        return match ($key) {
            // Factors 0..1
            'visibility_outdoor', 'visibility_indoor',
            'direction_outdoor_one', 'direction_outdoor_two', 'direction_indoor',
            'share_of_voice'
                => [0.0, 1.0, '0..1 (factor)'],

            // Factors 0..3
            'dwell_factor', 'lane_factor_cap', 'poi_factor_cap'
                => [0.0, 3.0, '0..3 (factor cap)'],

            // Phase 4.1 reach formula
            'campaign_reach_base_per_cell'   => [50.0,    5000.0,   '50..5000 viewers/cell'],
            'campaign_reach_saturation_cap'  => [1.0,     5.0,      '1..5 (multiplier)'],
            'campaign_reach_capture_rate'    => [0.01,    0.5,      '0.01..0.5 (ratio)'],

            // Phase 4.2.1 population
            'population_density_baseline_per_km2' => [1000.0,  50000.0, '1k..50k /km²'],
            'population_factor_min'               => [0.1,     1.0,     '0.1..1.0 (floor)'],
            'population_factor_max'               => [1.0,     5.0,     '1.0..5.0 (cap)'],

            // Phase 4.2.2 venue footfall toggles (0/1)
            'venue_footfall_enable_foursquare',
            'venue_footfall_enable_osm',
            'venue_footfall_enable_google'
                => [0.0, 1.0, '0=off · 1=on'],

            // Phase 4.2.2 provider priority (1 = try first, 99 = fallback)
            'venue_footfall_priority_foursquare',
            'venue_footfall_priority_osm',
            'venue_footfall_priority_google'
                => [1.0, 99.0, '1..99 (lower = try first)'],

            // Phase 4.2.2 misc
            'venue_footfall_cache_ttl_days'              => [1.0,   365.0,    '1..365 days'],
            'venue_footfall_min_confidence'              => [0.0,   1.0,      '0..1 (reject below)'],
            'venue_footfall_radius_m'                    => [50.0,  1000.0,   '50..1000 meters'],
            'venue_footfall_foursquare_daily_budget'     => [0.0,   100000.0, '0..100k calls/day'],
            'venue_footfall_foursquare_cost_per_call_usd' => [0.0,  1.0,      '$0..$1 / call'],

            default => [0.0, 1000000.0, '0..1,000,000'],
        };
    }
}
