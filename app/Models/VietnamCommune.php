<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VietnamCommune extends Model
{
    protected $fillable = [
        'code', 'name', 'full_name', 'type', 'province_id',
    ];

    public function province(): BelongsTo
    {
        return $this->belongsTo(VietnamProvince::class, 'province_id');
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class, 'commune_id');
    }

    /** Options cho province: [id => full_name] */
    public static function optionsForProvince(?int $provinceId): array
    {
        if (! $provinceId) return [];

        return static::where('province_id', $provinceId)
            ->orderByRaw("type = 'phuong' DESC, type = 'thi_tran' DESC")
            ->orderBy('name')
            ->pluck('full_name', 'id')
            ->toArray();
    }
}
