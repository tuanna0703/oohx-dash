<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationUser extends Model
{
    protected $fillable = [
        'organization_id', 'user_id', 'role',
    ];

    public const ROLE_ADMIN   = 'admin';
    public const ROLE_PLANNER = 'planner';
    public const ROLE_VIEWER  = 'viewer';

    public const PERMISSIONS = [
        'admin'   => ['manage_team', 'create_campaign', 'submit_booking', 'upload_creative', 'view_campaigns', 'view_reports', 'manage_payments'],
        'planner' => ['create_campaign', 'submit_booking', 'upload_creative', 'view_campaigns', 'view_reports'],
        'viewer'  => ['view_campaigns', 'view_reports'],
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function can(string $permission): bool
    {
        return in_array($permission, self::PERMISSIONS[$this->role] ?? []);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }
}
