<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookSubscription extends Model
{
    protected $fillable = [
        'webhook_id',
        'api_client_id',
        'url',
        'events',
        'secret',
        'status',
    ];

    protected $casts = [
        'events' => 'array',
    ];

    // ── Relationships ────────────────────────────────────────

    public function apiClient(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class);
    }

    // ── Scopes ──────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForEvent($query, string $event)
    {
        return $query->whereJsonContains('events', $event);
    }
}
