<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class BookingLineCreative extends Pivot
{
    protected $table = 'booking_line_creatives';

    public $incrementing = true;

    protected $casts = [
        'weight'     => 'integer',
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function bookingLine(): BelongsTo
    {
        return $this->belongsTo(BookingLine::class);
    }

    public function creative(): BelongsTo
    {
        return $this->belongsTo(Creative::class);
    }
}
