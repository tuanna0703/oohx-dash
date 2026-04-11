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
    ];

    protected $casts = [
        'default_floor_cpm' => 'decimal:2',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    /**
     * Legacy: screens linked by networks.code ↔ screens.network_code.
     * Used during Hivestack import to auto-assign screens to networks.
     * NOT the source of truth for inventory — use inventoryScreens() instead.
     *
     * @deprecated Prefer inventoryScreens() for inventory queries.
     */
    public function screens(): HasMany
    {
        return $this->hasMany(Screen::class, 'network_code', 'code');
    }

    /**
     * SSP source of truth: screens linked via screen_inventory.network_id.
     * Use this for all inventory counts, listing, and admin display.
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

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
