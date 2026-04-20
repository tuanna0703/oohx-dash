<?php

namespace App\Models\Oohx\Config;

use Illuminate\Database\Eloquent\Model;

/**
 * config.formula_versions — snapshot toàn bộ config.* tại 1 thời điểm.
 *
 * Workflow:
 *   1. Edit coefficients trong config.* tables
 *   2. Publish version → snapshot → INSERT row mới (is_active=false)
 *   3. Activate → set is_active=true (partial unique index đảm bảo max 1 active)
 *   4. Python loader đọc active version, dùng snapshot làm source of truth
 *   5. Rollback = activate version cũ
 *
 * `snapshot` = JSONB với 4 nested groups: base_city_traffic, road_class_multipliers,
 * zone_factors, delivery_defaults (key → value).
 */
class FormulaVersion extends Model
{
    protected $connection = 'oohx_control';
    protected $table      = 'config.formula_versions';
    public    $timestamps = false;

    protected $fillable = [
        'tag', 'description', 'snapshot',
        'is_active', 'activated_at', 'created_by', 'created_at',
    ];

    protected $casts = [
        'snapshot'     => 'array',
        'is_active'    => 'boolean',
        'activated_at' => 'datetime',
        'created_at'   => 'datetime',
    ];
}
