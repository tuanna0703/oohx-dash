<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lời mời tham gia một tenant (Owner publisher hoặc Organization buyer).
 * Token dùng để xác thực acceptance flow qua email.
 */
class UserInvitation extends Model
{
    use HasUlids;

    public const TENANT_OWNER        = 'owner';
    public const TENANT_ORGANIZATION = 'organization';

    protected $fillable = [
        'email', 'tenant_type', 'tenant_id', 'role',
        'allowed_network_ids', 'token',
        'invited_by_user_id', 'expires_at', 'accepted_at',
    ];

    protected $casts = [
        'allowed_network_ids' => 'array',
        'expires_at'          => 'datetime',
        'accepted_at'         => 'datetime',
    ];

    protected $hidden = ['token'];

    // ── Relationships ────────────────────────────────────────────────────────

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    /** Polymorphic resolve tới Owner | Organization. */
    public function tenant(): ?Model
    {
        return match ($this->tenant_type) {
            self::TENANT_OWNER        => Owner::find($this->tenant_id),
            self::TENANT_ORGANIZATION => Organization::find($this->tenant_id),
            default                   => null,
        };
    }

    // ── Scopes & helpers ─────────────────────────────────────────────────────

    /** Invitations chưa accept và chưa hết hạn — sẵn sàng dùng. */
    public function scopeValid(Builder $q): Builder
    {
        return $q->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->whereNull('accepted_at');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isValid(): bool
    {
        return ! $this->isAccepted() && ! $this->isExpired();
    }
}
