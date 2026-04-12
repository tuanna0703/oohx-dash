<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = [
        'cart_id', 'screen_id',
        'start_date', 'end_date', 'spot_length',
        'share_of_voice_pct', 'estimated_impressions', 'estimated_cost',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'spot_length' => 'integer',
        'share_of_voice_pct' => 'integer',
        'estimated_impressions' => 'integer',
        'estimated_cost' => 'decimal:2',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class);
    }
}
