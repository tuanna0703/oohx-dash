<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignActivity extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'campaign_id', 'user_id',
        'action', 'description', 'metadata',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function log(Campaign $campaign, string $action, string $description, ?int $userId = null, ?array $metadata = null): self
    {
        return self::create([
            'campaign_id' => $campaign->id,
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
