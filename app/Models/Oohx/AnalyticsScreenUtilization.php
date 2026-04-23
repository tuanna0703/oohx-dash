<?php

namespace App\Models\Oohx;

use Illuminate\Database\Eloquent\Model;

/**
 * Read-only projection của `output.mv_screen_utilization` — Phase 4.2.7.
 *
 * Screen booking activity 90 ngày trailing. Screen không có row = unused 90d.
 * Laravel LEFT JOIN với local screens để hiển thị cả unused.
 *
 * Use case: top booked screens, unused inventory filter, sales lead premium pricing.
 */
class AnalyticsScreenUtilization extends Model
{
    protected $connection   = 'oohx';
    protected $table        = 'output.mv_screen_utilization';
    protected $primaryKey   = 'screen_id';
    public    $incrementing = false;
    protected $keyType      = 'integer';
    public    $timestamps   = false;

    protected $casts = [
        'screen_id'                  => 'integer',
        'campaign_count_90d'         => 'integer',
        'allocated_impressions_90d'  => 'integer',
        'last_used_at'               => 'datetime',
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
