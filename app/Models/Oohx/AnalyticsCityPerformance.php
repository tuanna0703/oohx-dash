<?php

namespace App\Models\Oohx;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only projection của `output.mv_city_performance` — Phase 4.2.7.
 *
 * Per-city rollup: total screens, breakdown indoor/outdoor, average estimate quality,
 * total daily impressions capacity, average population density (Phase 4.2.1).
 *
 * Use case: city dropdown badges, inventory health page.
 */
class AnalyticsCityPerformance extends Model
{
    protected $connection   = 'oohx';
    protected $table        = 'output.mv_city_performance';
    protected $primaryKey   = 'city';
    public    $incrementing = false;
    protected $keyType      = 'string';
    public    $timestamps   = false;

    protected $casts = [
        'screen_count'           => 'integer',
        'outdoor_count'          => 'integer',
        'indoor_count'           => 'integer',
        'screens_with_estimate'  => 'integer',
        'avg_daily_impressions'  => 'float',
        'total_daily_impressions' => 'integer',
        'avg_confidence'         => 'float',
        'avg_population_density' => 'float',
        'computed_at'            => 'datetime',
    ];

    public function save(array $options = []): bool
    {
        throw new \RuntimeException('Materialized view is read-only — refresh via DE cron.');
    }

    public function delete(): ?bool
    {
        throw new \RuntimeException('Materialized view is read-only.');
    }
}
