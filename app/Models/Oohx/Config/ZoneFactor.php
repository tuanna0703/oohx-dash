<?php

namespace App\Models\Oohx\Config;

use Illuminate\Database\Eloquent\Model;

/**
 * config.zone_factors — placement zone → traffic dampening/amplification factor.
 * PK = zone_type (entrance, escalator, food_court, checkout, facade, roadside, ...).
 *
 * CHECK: factor > 0 AND <= 2.
 */
class ZoneFactor extends Model
{
    protected $connection   = 'oohx_control';
    protected $table        = 'config.zone_factors';
    protected $primaryKey   = 'zone_type';
    public    $incrementing = false;
    protected $keyType      = 'string';
    public    $timestamps   = false;

    protected $fillable = ['zone_type', 'factor', 'note', 'updated_by', 'updated_at'];

    protected $casts = [
        'factor'     => 'float',
        'updated_at' => 'datetime',
    ];
}
