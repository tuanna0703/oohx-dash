<?php

namespace App\Models\Oohx\Config;

use Illuminate\Database\Eloquent\Model;

/**
 * config.road_class_multipliers — OSM road class → traffic multiplier.
 * PK = road_class (string: highway, primary, secondary, tertiary, residential, service, ...).
 *
 * CHECK: multiplier > 0 AND <= 5.
 */
class RoadClassMultiplier extends Model
{
    protected $connection   = 'oohx_control';
    protected $table        = 'config.road_class_multipliers';
    protected $primaryKey   = 'road_class';
    public    $incrementing = false;
    protected $keyType      = 'string';
    public    $timestamps   = false;

    protected $fillable = ['road_class', 'multiplier', 'note', 'updated_by', 'updated_at'];

    protected $casts = [
        'multiplier' => 'float',
        'updated_at' => 'datetime',
    ];
}
