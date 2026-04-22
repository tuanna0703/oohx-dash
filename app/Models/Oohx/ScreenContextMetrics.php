<?php

namespace App\Models\Oohx;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * metrics.screen_context_metrics — 1 row per screen, output của enrichment step.
 *
 * Connection `oohx_control` — role này có SELECT trên metrics.* (per PHASE-2A-HANDOFF §4.2.1),
 * trong khi `oohx_readonly` KHÔNG có quyền schema metrics. Read-only từ Laravel (chỉ SELECT).
 *
 * Phase 2.D thêm 3 cột: weather_factor, seasonality_factor, calibration_factor.
 *
 * Dùng bởi:
 *   - OohxEstimateResource — hiển thị factor cùng estimate (Inspector-lite)
 *   - Screen Inspector (future Guide 04) — full breakdown
 */
class ScreenContextMetrics extends Model
{
    protected $connection = 'oohx_control';
    protected $table      = 'metrics.screen_context_metrics';
    protected $primaryKey = 'screen_id';
    public    $incrementing = false;
    public    $timestamps   = true;

    protected $casts = [
        // Road + POI context
        'lane_count'             => 'integer',
        'nearest_road_id'        => 'integer',
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

    /**
     * Phase 3.A Part 2 — data-completeness check (handoff §2.4 Option B).
     *
     * Screen coi là có "complete data" nếu cả 2 tín hiệu dưới đều có:
     *   - `nearest_road_id` resolved (PostGIS spatial join matched)
     *   - `poi_count_300m > 0` (ít nhất 1 POI trong bán kính 300m)
     *
     * City/region độc lập — không hardcode 'HCMC'. Khi DE team ingest Đà Nẵng,
     * Hải Phòng... badge tự detect. Trước khi roads/POIs parity chạy xong, badge
     * sẽ hiện "incomplete" cho city đó.
     */
    public function getHasCompleteDataAttribute(): bool
    {
        return $this->nearest_road_id !== null
            && ($this->poi_count_300m ?? 0) > 0;
    }

    /** Color cho Filament badge: success khi complete, warning khi thiếu. */
    public function getCompletenessBadgeColorAttribute(): string
    {
        return $this->has_complete_data ? 'success' : 'warning';
    }

    public function getCompletenessBadgeLabelAttribute(): string
    {
        return $this->has_complete_data ? 'Complete' : 'Incomplete data';
    }

    /**
     * Chi tiết thiếu gì — dùng tooltip hiển thị UX. Trả empty khi complete.
     *
     * @return list<string>
     */
    public function getMissingDataReasonsAttribute(): array
    {
        $reasons = [];
        if ($this->nearest_road_id === null) {
            $reasons[] = 'No nearest road match (roads.* chưa ingest cho city này)';
        }
        if (($this->poi_count_300m ?? 0) === 0) {
            $reasons[] = 'Không có POI trong bán kính 300m (pois.* chưa ingest)';
        }
        return $reasons;
    }
}
