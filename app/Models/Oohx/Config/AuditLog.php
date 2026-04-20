<?php

namespace App\Models\Oohx\Config;

use Illuminate\Database\Eloquent\Model;

/**
 * config.audit_log — append-only audit của mọi config change.
 *
 * Permission DB: oohx_control có SELECT + INSERT, KHÔNG có UPDATE/DELETE.
 * Mọi UPDATE/DELETE attempt sẽ throw permission error → đảm bảo immutability.
 *
 * Convention `actor`:
 *   - Laravel: email user (vd "admin@oohx.net") hoặc "web:<id>" nếu không có email
 *   - Python CLI: "cli:<unix_user>" (vd "cli:oohx")
 *
 * Convention `action`:
 *   - update_base_city_traffic / update_road_class / update_zone / update_delivery_default
 *   - publish_version / activate_version
 */
class AuditLog extends Model
{
    protected $connection = 'oohx_control';
    protected $table      = 'config.audit_log';
    public    $timestamps = false;

    protected $fillable = ['actor', 'action', 'target', 'old_value', 'new_value', 'note', 'created_at'];

    protected $casts = [
        'old_value'  => 'array',
        'new_value'  => 'array',
        'created_at' => 'datetime',
    ];
}
