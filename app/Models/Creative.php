<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Creative extends Model
{
    use HasUlids;

    protected $fillable = [
        'campaign_id', 'organization_id',
        'name', 'type', 'file_path', 'file_size',
        'width_px', 'height_px', 'duration_sec',
        'vast_tag_url', 'status',
        'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'width_px' => 'integer',
        'height_px' => 'integer',
        'duration_sec' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function bookingLines(): BelongsToMany
    {
        return $this->belongsToMany(BookingLine::class, 'booking_line_creatives')
            ->using(BookingLineCreative::class)
            ->withPivot('weight', 'start_date', 'end_date');
    }

    public function getFileUrlAttribute(): ?string
    {
        return $this->file_path ? asset('storage/' . $this->file_path) : null;
    }

    public function getDimensionsAttribute(): string
    {
        if ($this->width_px && $this->height_px) {
            return $this->width_px . 'x' . $this->height_px;
        }
        return '—';
    }
}
