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
        'default_floor_cpm', 'default_floor_cpm_currency', 'status',
    ];

    protected $casts = [
        'default_floor_cpm' => 'decimal:2',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function screens(): HasMany
    {
        return $this->hasMany(Screen::class, 'network_code', 'code');
    }

    /**
     * Screens linked via screen_inventory.network_id — source of truth for SSP inventory.
     * Used by admin RelationManager on the Network detail page.
     */
    public function inventoryScreens(): HasManyThrough
    {
        return $this->hasManyThrough(
            Screen::class,
            ScreenInventory::class,
            'network_id',  // FK on screen_inventory → networks.id
            'id',          // FK on screens referenced by screen_inventory.screen_id
            'id',          // PK on networks
            'screen_id',   // FK on screen_inventory → screens.id
        );
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
