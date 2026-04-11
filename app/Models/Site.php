<?php

namespace App\Models;

use App\Traits\HasOwnerScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Site extends Model
{
    use HasFactory, HasUlids, HasOwnerScope, SoftDeletes;

    protected $fillable = [
        'owner_id', 'external_id',
        'name', 'description', 'banner', 'lat', 'lon',
        'address', 'city', 'region', 'country', 'status',
        'province_id', 'commune_id',
    ];

    protected $casts = [
        'lat' => 'decimal:7',
        'lon' => 'decimal:7',
    ];

    // ── Relationships ──────────────────────────────────────

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(VietnamProvince::class, 'province_id');
    }

    public function commune(): BelongsTo
    {
        return $this->belongsTo(VietnamCommune::class, 'commune_id');
    }

    public function screens(): HasMany
    {
        return $this->hasMany(Screen::class);
    }

    // ── Scopes ─────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
