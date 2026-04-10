<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VenueCategory extends Model
{
    protected $table = 'venue_categories';

    public $incrementing = false;

    protected $fillable = [
        'id', 'name', 'slug', 'name_vi', 'description', 'icon', 'thumb', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function venueTypes(): HasMany
    {
        return $this->hasMany(VenueType::class, 'vn_category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get all string_value patterns for this category's venue types.
     */
    public function getStringValues(): array
    {
        return $this->venueTypes()->pluck('string_value')->filter()->all();
    }
}
