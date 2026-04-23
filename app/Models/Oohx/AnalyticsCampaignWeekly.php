<?php

namespace App\Models\Oohx;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only projection của `output.mv_campaign_weekly` — Phase 4.2.7.
 *
 * Materialized view refresh daily 04:15 UTC qua DE cron `refresh-analytics`.
 * Connection `oohx` readonly — KHÔNG có REFRESH permission (handoff §6).
 *
 * Use case: dashboard line chart 12 tuần trailing, KPI "this week vs prev week".
 */
class AnalyticsCampaignWeekly extends Model
{
    protected $connection   = 'oohx';
    protected $table        = 'output.mv_campaign_weekly';
    protected $primaryKey   = 'week_start';
    public    $incrementing = false;
    protected $keyType      = 'string';   // date string for binding
    public    $timestamps   = false;

    protected $casts = [
        'week_start'                => 'date',
        'campaigns_count'           => 'integer',
        'total_impressions'         => 'integer',
        'total_reach'               => 'integer',
        'avg_frequency'             => 'float',
        'avg_screens_per_campaign'  => 'float',
        'avg_confidence'            => 'float',
        'avg_cpm_vnd'               => 'integer',
        'unique_users'              => 'integer',
    ];

    // ── Read-only guards ────────────────────────────────────────────────

    public function save(array $options = []): bool
    {
        throw new \RuntimeException('Materialized view is read-only — refresh via DE cron.');
    }

    public function delete(): ?bool
    {
        throw new \RuntimeException('Materialized view is read-only.');
    }
}
