<?php

namespace App\Models\Oohx;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Read-only projection of `core.screens` trên Data Engine VPS.
 *
 * external_id = uuid của Laravel Screen (đã chốt team). Không phải PK local.
 * Connection `oohx` (pgsql qua SSH tunnel) — user readonly, không INSERT/UPDATE.
 */
class Screen extends Model
{
    protected $connection = 'oohx';
    protected $table      = 'core.screens';
    public    $timestamps = true;

    protected $casts = [
        'lat'               => 'float',
        'lon'               => 'float',
        'source_updated_at' => 'datetime',
        'synced_at'         => 'datetime',
    ];

    public function estimate(): HasOne
    {
        return $this->hasOne(ScreenEstimate::class, 'screen_id');
    }
}
