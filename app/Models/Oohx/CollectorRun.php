<?php

namespace App\Models\Oohx;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * collectors.collector_runs — queue cho external data collectors (Phase 2.C).
 *
 * Lifecycle: pending → running → done | failed | cancelled
 *
 * Role oohx_control có INSERT + UPDATE. Fields Python worker own:
 *   - status transitions (pending → running → done/failed)
 *   - started_at, finished_at
 *   - rows_ingested, bytes_fetched, stats (JSONB)
 *   - error_message
 *
 * Laravel chỉ được ghi:
 *   - INSERT pending row (trigger)
 *   - UPDATE status='cancelled' khi còn pending
 */
class CollectorRun extends Model
{
    protected $connection = 'oohx_control';
    protected $table      = 'collectors.collector_runs';
    public    $timestamps = false;

    protected $fillable = [
        'collector_name', 'city', 'bbox',
        'params', 'status', 'priority', 'retry_count',
        'rows_ingested', 'bytes_fetched', 'stats', 'error_message',
        'requested_by', 'requested_at', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'params'       => 'array',
        'stats'        => 'array',
        'requested_at' => 'datetime',
        'started_at'   => 'datetime',
        'finished_at'  => 'datetime',
    ];

    // ── Scopes ─────────────────────────────────────────────────────────

    public function scopePending(Builder $q): Builder    { return $q->where('status', 'pending'); }
    public function scopeRunning(Builder $q): Builder    { return $q->where('status', 'running'); }
    public function scopeDone(Builder $q): Builder       { return $q->where('status', 'done'); }
    public function scopeFailed(Builder $q): Builder     { return $q->where('status', 'failed'); }
    public function scopeCancelled(Builder $q): Builder  { return $q->where('status', 'cancelled'); }
    public function scopeActive(Builder $q): Builder     { return $q->whereIn('status', ['pending', 'running']); }

    public function scopeForCollector(Builder $q, string $name): Builder
    {
        return $q->where('collector_name', $name);
    }

    // ── Accessors ──────────────────────────────────────────────────────

    /**
     * Duration seconds — null nếu chưa started.
     */
    public function getDurationSecondsAttribute(): ?int
    {
        if (! $this->started_at) return null;
        $end = $this->finished_at ?? now();
        return (int) $this->started_at->diffInSeconds($end);
    }

    /**
     * True nếu được phép cancel (chỉ pending; Phase 2.C không support cooperative cancel).
     */
    public function getIsCancellableAttribute(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * True nếu đang active (pending/running) — dùng để toggle auto-refresh UI.
     */
    public function getIsActiveAttribute(): bool
    {
        return in_array($this->status, ['pending', 'running'], true);
    }

    /**
     * Bytes fetched formatted human-readable (KB / MB).
     */
    public function getBytesFetchedHumanAttribute(): string
    {
        $b = (int) ($this->bytes_fetched ?? 0);
        if ($b === 0) return '—';
        if ($b < 1024) return "{$b} B";
        if ($b < 1024 * 1024) return round($b / 1024, 1) . ' KB';
        return round($b / (1024 * 1024), 2) . ' MB';
    }
}
