<?php

namespace App\Models;

use App\Traits\HasOwnerScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
