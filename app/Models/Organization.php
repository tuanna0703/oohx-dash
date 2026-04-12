<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'type', 'tax_id',
        'billing_address', 'billing_email', 'billing_phone',
        'logo_url', 'website', 'status',
        'credit_limit', 'payment_terms_days',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'payment_terms_days' => 'integer',
    ];

    // ── Relationships ──

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function organizationUsers(): HasMany
    {
        return $this->hasMany(OrganizationUser::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function creatives(): HasMany
    {
        return $this->hasMany(Creative::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // ── Scopes ──

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
