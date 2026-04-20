<?php

namespace App\Models\Oohx;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * metrics.screen_context_metrics — 1 row per screen, output của enrichment step.
 *
 * Read-only trên connection `oohx` (oohx_readonly user có SELECT, không có write).
 * Phase 2.D thêm 3 cột: weather_factor, seasonality_factor, calibration_factor.
 *
 * Dùng bởi:
 *   - OohxEstimateResource — hiển thị factor cùng estimate (Inspector-lite)
 *   - Screen Inspector (future Guide 04) — full breakdown
 */
class ScreenContextMetrics extends Model
{
    protected $connection = 'oohx';
    protected $table      = 'metrics.screen_context_metrics';
    protected $primaryKey = 'screen_id';
    public    $incrementing = false;
    public    $timestamps   = true;

    protected $casts = [
        // Road + POI context
        'lane_count'             => 'integer',
        'poi_count_100m'         => 'integer',
        'poi_count_300m'         => 'integer',
        'poi_count_500m'         => 'integer',
        'venue_footfall'         => 'float',

        // Resolved factors (0..1 hoặc 0..2)
        'zone_factor'            => 'float',
        'visibility_factor'      => 'float',
        'direction_factor'       => 'float',

        // Scores normalized 0..1
        'road_score'             => 'float',
        'poi_score'              => 'float',
        'venue_score'            => 'float',
        'population_score'       => 'float',

        // Phase 2.D contextual factors (NULL khi chưa có data)
        'weather_factor'         => 'float',
        'seasonality_factor'     => 'float',
        'calibration_factor'     => 'float',

        'context_tags'           => 'array',
        'created_at'             => 'datetime',
        'updated_at'             => 'datetime',
    ];

    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class, 'screen_id');
    }

    // ── Accessors for UI ─────────────────────────────────────────────

    /**
     * Weather factor tier → color for badge display.
     * 0.70-0.85 → danger (rain/hot) · 0.86-0.95 → warning · 0.96+ → success · NULL → gray
     */
    public function getWeatherFactorColorAttribute(): string
    {
        $f = $this->weather_factor;
        if ($f === null) return 'gray';
        if ($f < 0.86) return 'danger';
        if ($f < 0.96) return 'warning';
        return 'success';
    }

    /**
     * Seasonality tier: >1.05 = high season, <0.95 = low (Tet), else neutral.
     */
    public function getSeasonalityFactorColorAttribute(): string
    {
        $f = $this->seasonality_factor;
        if ($f === null) return 'gray';
        if ($f > 1.05) return 'success';
        if ($f < 0.95) return 'danger';
        return 'warning';
    }
}
