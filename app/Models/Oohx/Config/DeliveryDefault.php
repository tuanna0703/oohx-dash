<?php

namespace App\Models\Oohx\Config;

use Illuminate\Database\Eloquent\Model;

/**
 * config.delivery_defaults — visibility, direction, SOV, dwell, caps used khi screen
 * không có override riêng trong core.screen_delivery_settings.
 *
 * PK = key (string from KEYS const). CHECK: value >= 0 AND <= 100.
 */
class DeliveryDefault extends Model
{
    protected $connection   = 'oohx_control';
    protected $table        = 'config.delivery_defaults';
    protected $primaryKey   = 'key';
    public    $incrementing = false;
    protected $keyType      = 'string';
    public    $timestamps   = false;

    protected $fillable = ['key', 'value', 'description', 'updated_by', 'updated_at'];

    protected $casts = [
        'value'      => 'float',
        'updated_at' => 'datetime',
    ];

    /**
     * Whitelist key values Data Engine recognise. Tham khảo seed-config-from-code
     * trên DE side. Form có thể restrict create chỉ trong list này.
     */
    public const KEYS = [
        'visibility_outdoor',
        'visibility_indoor',
        'direction_outdoor_one',
        'direction_outdoor_two',
        'direction_indoor',
        'share_of_voice',
        'dwell_factor',
        'lane_factor_cap',
        'poi_factor_cap',
    ];
}
