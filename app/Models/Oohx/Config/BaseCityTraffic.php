<?php

namespace App\Models\Oohx\Config;

use Illuminate\Database\Eloquent\Model;

/**
 * config.base_city_traffic — baseline daily passby per city.
 * PK = city (string, e.g. "Hanoi", "HCMC"). Used by Data Engine outdoor formula:
 *   passby = baseline_passby × road × lane × intersection × poi × population
 *
 * CHECK constraint phía DB: baseline_passby >= 0 AND <= 1_000_000.
 */
class BaseCityTraffic extends Model
{
    protected $connection   = 'oohx_control';
    protected $table        = 'config.base_city_traffic';
    protected $primaryKey   = 'city';
    public    $incrementing = false;
    protected $keyType      = 'string';
    public    $timestamps   = false;

    protected $fillable = ['city', 'baseline_passby', 'note', 'updated_by', 'updated_at'];

    protected $casts = [
        'baseline_passby' => 'float',
        'updated_at'      => 'datetime',
    ];
}
