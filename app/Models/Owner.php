<?php

namespace App\Models;

use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Owner extends Model
{
    use HasFactory, HasUlids, HasSlug, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'type', 'onboard_method',
        'revenue_share_pct', 'status', 'billing_info', 'notes',
        'tagline', 'about', 'logo_url', 'cover_url',
        'website', 'email', 'phone', 'address', 'city', 'district',
        'province_id', 'commune_id',
        'founded', 'featured', 'headquarters_lat', 'headquarters_lng',
    ];

    protected $casts = [
        'billing_info'       => 'array',
        'revenue_share_pct'  => 'decimal:2',
        'featured'           => 'boolean',
        'founded'            => 'integer',
        'headquarters_lat'   => 'decimal:7',
        'headquarters_lng'   => 'decimal:7',
    ];

    // ── Relationships ──────────────────────────────────────

    public function province(): BelongsTo
    {
        return $this->belongsTo(VietnamProvince::class, 'province_id');
    }

    public function commune(): BelongsTo
    {
        return $this->belongsTo(VietnamCommune::class, 'commune_id');
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function networks(): HasMany
    {
        return $this->hasMany(Network::class);
    }

    public function screens(): HasMany
    {
        return $this->hasMany(Screen::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(OwnerUser::class);
    }

    public function impressionLogs(): HasMany
    {
        return $this->hasMany(ImpressionLog::class);
    }

    // ── Scopes ─────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // ── Helpers ────────────────────────────────────────────
    // Note: Use withCount(['screens as screen_count' => ...]) at query time
    // instead of computed accessors to avoid N+1 queries.
}
