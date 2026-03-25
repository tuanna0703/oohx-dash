<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnerUser extends Model
{
    protected $table = 'owner_users';

    protected $fillable = [
        'owner_id', 'user_id', 'role', 'allowed_network_ids',
    ];

    protected $casts = [
        'allowed_network_ids' => 'array',
    ];

    // ── Role definitions ──────────────────────────────────────────────────────

    /**
     * 6 roles theo thứ tự quyền giảm dần.
     * Label hiển thị trong UI (khớp với ảnh thiết kế).
     */
    public const ROLES = [
        'owner'          => 'Owner',
        'manager'        => 'Manager',
        'scheduler'      => 'Scheduler',
        'read_only'      => 'Read only',
        'reporting_only' => 'Reporting only',
        'sales_manager'  => 'Sales manager',
    ];

    /**
     * Quyền theo từng role.
     * key = action, value = array roles được phép.
     */
    public const PERMISSIONS = [
        // Quản lý users trong owner
        'manage_users'     => ['owner'],
        // Sửa thông tin owner (name, billing...)
        'edit_owner'       => ['owner', 'manager'],
        // Thêm/sửa/xoá site & screen
        'manage_inventory' => ['owner', 'manager', 'scheduler'],
        // Bật/tắt programmatic, sửa floor CPM
        'manage_pricing'   => ['owner', 'manager'],
        // Xem screens, sites (read)
        'view_inventory'   => ['owner', 'manager', 'scheduler', 'read_only', 'sales_manager'],
        // Import bulk xlsx
        'import_inventory' => ['owner', 'manager', 'scheduler'],
        // Xem revenue & impression reports
        'view_reports'     => ['owner', 'manager', 'reporting_only', 'sales_manager'],
        // Export report CSV/Excel
        'export_reports'   => ['owner', 'manager', 'reporting_only'],
        // Xem sales dashboard (CPM, deals)
        'view_sales'       => ['owner', 'manager', 'sales_manager'],
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Permission helpers ────────────────────────────────────────────────────

    /** Kiểm tra role có quyền thực hiện action không */
    public function can(string $action): bool
    {
        $allowed = self::PERMISSIONS[$action] ?? [];
        return in_array($this->role, $allowed);
    }

    /** Có quyền quản lý network cụ thể không */
    public function canAccessNetwork(string|int $networkId): bool
    {
        if (in_array($this->role, ['owner', 'manager'])) return true;
        if (is_null($this->allowed_network_ids)) return true;
        return in_array($networkId, $this->allowed_network_ids);
    }

    /** Role label để hiển thị UI */
    public function getRoleLabelAttribute(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }

    /** Static helper: lấy danh sách options cho Select */
    public static function roleOptions(): array
    {
        return self::ROLES;
    }

    /** Role hiện tại có phải owner không */
    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }
}
