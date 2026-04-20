<?php

namespace App\Models\Oohx;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * core.recompute_jobs — queue cho Data Engine recompute worker.
 *
 * Semantics (xem PHASE-2B-HANDOFF):
 *   - job_type ∈ {screen, city, bulk}
 *   - status ∈ {pending, processing, done, failed, cancelled}
 *   - bulk jobs hỗ trợ payload.action ∈ {recompute_all, recompute_stale, recompute_by_city}
 *   - payload.progress được worker flush mỗi 25 screens
 *   - Cooperative cancel: set status='cancelled' → worker detect trong ≤25 screens
 *
 * Connection oohx_control (write). Relationship screen() qua connection oohx (read-only).
 */
class RecomputeJob extends Model
{
    protected $connection = 'oohx_control';
    protected $table      = 'core.recompute_jobs';
    public    $timestamps = false;

    protected $fillable = [
        'job_type', 'screen_id', 'city', 'payload',
        'priority', 'status', 'retry_count', 'error_message',
        'requested_at', 'started_at', 'finished_at',
        'cancelled_at', 'notified_at',
    ];

    protected $casts = [
        'payload'      => 'array',
        'requested_at' => 'datetime',
        'started_at'   => 'datetime',
        'finished_at'  => 'datetime',
        'cancelled_at' => 'datetime',
        'notified_at'  => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────

    /**
     * Eloquent tự dùng connection `oohx` của Screen model cho subquery —
     * không JOIN raw qua 2 connection.
     */
    public function screen(): BelongsTo
    {
        return $this->belongsTo(Screen::class, 'screen_id');
    }

    // ── Scopes ─────────────────────────────────────────────────────────

    public function scopePending(Builder $q): Builder    { return $q->where('status', 'pending'); }
    public function scopeProcessing(Builder $q): Builder { return $q->where('status', 'processing'); }
    public function scopeFailed(Builder $q): Builder     { return $q->where('status', 'failed'); }
    public function scopeDone(Builder $q): Builder       { return $q->where('status', 'done'); }
    public function scopeCancelled(Builder $q): Builder  { return $q->where('status', 'cancelled'); }
    public function scopeBulk(Builder $q): Builder       { return $q->where('job_type', 'bulk'); }
    public function scopeActive(Builder $q): Builder     { return $q->whereIn('status', ['pending', 'processing']); }

    // ── Accessors (for UI) ────────────────────────────────────────────

    /**
     * Duration seconds từ started_at đến finished_at (hoặc now nếu vẫn running).
     */
    public function getDurationSecondsAttribute(): ?int
    {
        if (! $this->started_at) return null;
        $end = $this->finished_at ?? now();
        return (int) $this->started_at->diffInSeconds($end);
    }

    /**
     * Action string từ payload.action (chỉ có nghĩa cho bulk jobs).
     */
    public function getActionAttribute(): ?string
    {
        return $this->payload['action'] ?? null;
    }

    /**
     * Progress dict từ payload.progress (worker flush mỗi 25 screens).
     * Returns null nếu chưa có progress.
     */
    public function getProgressDataAttribute(): ?array
    {
        return $this->payload['progress'] ?? null;
    }

    /**
     * Percent complete (0-100) dựa vào progress. Null nếu không có progress.
     */
    public function getProgressPercentAttribute(): ?int
    {
        $p = $this->progress_data;
        if (! $p || ! ($p['total'] ?? 0)) return null;
        $done = (int) ($p['done'] ?? 0) + (int) ($p['failed'] ?? 0);
        return (int) round($done / $p['total'] * 100);
    }

    /**
     * Target label for table display:
     *   screen → screen name (or id)
     *   city   → city
     *   bulk   → action name (or "bulk N screens")
     */
    public function getTargetLabelAttribute(): string
    {
        return match ($this->job_type) {
            'screen' => $this->screen?->external_id
                ? "{$this->screen->external_id}"
                : ($this->screen_id ? "screen #{$this->screen_id}" : '—'),
            'city'   => $this->city ?? '—',
            'bulk'   => $this->action ?? (isset($this->payload['screen_ids'])
                          ? 'bulk · ' . count($this->payload['screen_ids']) . ' screens'
                          : 'bulk'),
            default  => '—',
        };
    }

    /**
     * True nếu user có quyền cancel job này theo semantic Phase 2.B:
     *   - pending: always cancellable
     *   - processing + bulk: cooperative cancel
     *   - processing + screen/city: NOT cancellable (run to completion)
     *   - terminal states: NOT cancellable
     */
    public function getIsCancellableAttribute(): bool
    {
        if ($this->status === 'pending') return true;
        if ($this->status === 'processing' && $this->job_type === 'bulk') return true;
        return false;
    }

    /**
     * True nếu job đang chạy và UI nên auto-refresh để cập nhật progress.
     */
    public function getIsActiveAttribute(): bool
    {
        return in_array($this->status, ['pending', 'processing'], true);
    }
}
