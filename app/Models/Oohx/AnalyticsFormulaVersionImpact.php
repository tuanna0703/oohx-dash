<?php

namespace App\Models\Oohx;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only projection của `output.mv_formula_version_impact` — Phase 4.2.7.
 *
 * Per-version impact: how many screens use it, total/avg estimate output.
 * Use case: formula admin page show before/after activate impact, alert
 * "old version còn N screens chưa recompute".
 */
class AnalyticsFormulaVersionImpact extends Model
{
    protected $connection   = 'oohx';
    protected $table        = 'output.mv_formula_version_impact';
    protected $primaryKey   = 'formula_version_id';
    public    $incrementing = false;
    protected $keyType      = 'integer';
    public    $timestamps   = false;

    protected $casts = [
        'formula_version_id'         => 'integer',
        'is_active'                  => 'boolean',
        'activated_at'               => 'datetime',
        'screens_with_this_version'  => 'integer',
        'avg_daily_impressions'      => 'float',
        'total_daily_impressions'    => 'integer',
        'avg_confidence'             => 'float',
        'last_estimate_at'           => 'datetime',
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
