<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VietnamRegion extends Model
{
    protected $fillable = ['code', 'name', 'full_name', 'sort'];

    public function provinces(): HasMany
    {
        return $this->hasMany(VietnamProvince::class, 'region_id');
    }

    public static function toSelectOptions(): array
    {
        return static::orderBy('sort')->pluck('name', 'id')->toArray();
    }
}
