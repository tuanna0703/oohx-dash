<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'current_owner_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function ownerUsers(): HasMany
    {
        return $this->hasMany(OwnerUser::class);
    }

    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(Owner::class, 'owner_users')
            ->withPivot(['role', 'allowed_network_ids'])
            ->withTimestamps();
    }

    // SAU
    public function currentOwner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Owner::class, 'current_owner_id');
    }

    public function switchOwner(string $ownerId): bool
    {
        $hasAccess = $this->owners()->where('owners.id', $ownerId)->exists()
            || $this->hasRole('super_admin');
        if (! $hasAccess) return false;
        $this->update(['current_owner_id' => $ownerId]);
        return true;
    }

    public function getRoleInOwner(string $ownerId): ?string
    {
        return $this->ownerUsers()
            ->where('owner_id', $ownerId)
            ->value('role');
    }

    public function canAccessPanel(\Filament\Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return $this->hasRole('super_admin');
        }

        if ($panel->getId() === 'publisher') {
            return $this->owners()
                ->wherePivot('role', '!=', null)
                ->where('owners.status', 'active')
                ->exists();
        }

        return false;
    }
}
