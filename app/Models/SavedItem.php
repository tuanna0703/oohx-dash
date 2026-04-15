<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedItem extends Model
{
    protected $fillable = ['user_id', 'screen_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class);
    }
}
