<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VietnamProvince extends Model
{
    protected $fillable = [
        'code', 'name', 'full_name', 'type', 'region', 'region_id',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(VietnamRegion::class, 'region_id');
    }

    public function communes(): HasMany
    {
        return $this->hasMany(VietnamCommune::class, 'province_id');
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class, 'province_id');
    }

    /** Label hiển thị: full_name (code) */
    public function getDisplayNameAttribute(): string
    {
        return "{$this->full_name}";
    }

    /** Options dùng trong Select: [id => full_name] */
    public static function toSelectOptions(): array
    {
        return static::orderByRaw("type = 'thanh_pho' DESC")->orderBy('name')
            ->pluck('full_name', 'id')
            ->toArray();
    }
}
