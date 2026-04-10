<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VenueType extends Model
{
    protected $table = 'venue_types';

    protected $fillable = [
        'enumeration_id', 'string_value',
        'category', 'subcategory', 'venue_type',
        'description', 'depth', 'parent_id',
        'hivestack_supported', 'is_active',
        'vn_category_id',
    ];

    protected $casts = [
        'hivestack_supported' => 'boolean',
        'is_active'           => 'boolean',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function parent(): BelongsTo
    {
        return $this->belongsTo(VenueType::class, 'parent_id');
    }

    public function vnCategory(): BelongsTo
    {
        return $this->belongsTo(\App\Models\VenueCategory::class, 'vn_category_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(VenueType::class, 'parent_id');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeByCategory($query, string $cat)
    {
        return $query->where('category', $cat);
    }

    public function scopeHivestackSupported($query)
    {
        return $query->where('hivestack_supported', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeLeaves($query)
    {
        return $query->whereDoesntHave('children');
    }

    public function scopeForSelect($query)
    {
        return $query->where('is_active', true)
            ->whereDoesntHave('children')   // chỉ leaf nodes
            ->orderBy('category')
            ->orderBy('subcategory')
            ->orderBy('venue_type');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Trả về options grouped theo category cho Filament Select */
    public static function groupedOptions(): array
    {
        return static::forSelect()
            ->get()
            ->groupBy('category')
            ->map(fn($items) => $items->pluck('venue_type', 'string_value'))
            ->toArray();
    }

    /** Options flat dùng cho dropdown đơn giản */
    public static function flatOptions(): array
    {
        return static::forSelect()
            ->pluck('venue_type', 'string_value')
            ->toArray();
    }

    public function getIndentedNameAttribute(): string
    {
        return str_repeat('— ', $this->depth) . $this->venue_type;
    }
}
