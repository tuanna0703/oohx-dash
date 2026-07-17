<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một lần người mua đánh giá media owner sau khi chạy campaign.
 */
class OwnerReview extends Model
{
    use HasFactory, HasUlids;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_REJECTED  = 'rejected';

    public const STATUS_LABELS = [
        self::STATUS_PENDING   => 'Chờ duyệt',
        self::STATUS_PUBLISHED => 'Đã đăng',
        self::STATUS_REJECTED  => 'Từ chối',
    ];

    protected $fillable = [
        'campaign_id', 'owner_id', 'organization_id', 'user_id',
        'rating', 'comment', 'status', 'moderation_note', 'published_at',
    ];

    protected $casts = [
        'rating'       => 'integer',
        'published_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }
}
