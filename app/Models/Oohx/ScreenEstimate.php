<?php

namespace App\Models\Oohx;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Read-only projection của `output.screen_traffic_estimates` trên Data Engine VPS.
 *
 * Primary key là `screen_id` (1-1 với core.screens.id). KHÔNG incrementing —
 * Data Engine tự assign khi ingest screens.
 */
class ScreenEstimate extends Model
{
    protected $connection   = 'oohx';
    protected $table        = 'output.screen_traffic_estimates';
    protected $primaryKey   = 'screen_id';
    public    $incrementing = false;
    public    $timestamps   = false;

    protected $casts = [
        'estimated_daily_passby'        => 'float',
        'estimated_daily_screen_flow'   => 'float',
        'estimated_daily_ots'           => 'float',
        'estimated_daily_impressions'   => 'float',
        'estimated_weekly_impressions'  => 'float',
        'estimated_monthly_impressions' => 'float',
        'estimated_daily_reach'         => 'float',
        'estimated_weekly_reach'        => 'float',
        'estimated_monthly_reach'       => 'float',
        'estimated_frequency'           => 'float',
        'impression_multiplier'         => 'float',
        'estimated_cpm'                 => 'float',
        'confidence_score'              => 'float',
        'last_calculated_at'            => 'datetime',
        'updated_at'                    => 'datetime',
    ];

    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class, 'screen_id');
    }
}
