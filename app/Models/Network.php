<?php

namespace App\Models;

use App\Traits\HasOwnerScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Network extends Model
{
    use HasFactory, HasOwnerScope, SoftDeletes;

    protected $fillable = [
        'owner_id', 'code', 'name', 'description',
        'logo', 'banner',
        'default_floor_cpm', 'default_floor_cpm_currency', 'status',
        'vn_category_id',
    ];

    protected $casts = [
        'default_floor_cpm' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Network $network) {
            if (! $network->isForceDeleting()) {
                // Cascade: Network → Sites → Screens (each triggers its own cascade)
                $network->sites()->each(fn (Site $s) => $s->delete());
            }
        });
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function vnCategory(): BelongsTo
    {
        return $this->belongsTo(VenueCategory::class, 'vn_category_id');
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function screens(): HasManyThrough
    {
        return $this->hasManyThrough(Screen::class, Site::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
