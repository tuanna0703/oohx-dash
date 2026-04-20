<?php

namespace App\Services\Oohx;

use App\Models\Oohx\Config\AuditLog;
use App\Models\Oohx\RecomputeJob;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Orchestration cho Data Engine recompute queue.
 *
 * Write surface:
 *   - INSERT core.recompute_jobs (enqueue variants)
 *   - UPDATE core.recompute_jobs.status → cancelled/pending (cancel, retry)
 *   - INSERT config.audit_log (trace ops actions)
 *
 * Không bao giờ:
 *   - Set status → done/failed/processing (chỉ worker Python được)
 *   - Modify payload.progress (worker own field này)
 *
 * Phase 2.B additions:
 *   - enqueueBulkAction(): action-based dispatch (recompute_all/stale/by_city)
 *   - cancel() semantic: processing+bulk chỉ flip status (cooperative)
 *   - getProgress(): poll endpoint cho UI progress widget
 */
class JobOrchestrator
{
    private const CONNECTION = 'oohx_control';

    /** Valid bulk actions theo Data Engine backend (PHASE-2B-HANDOFF §1.2). */
    public const BULK_ACTIONS = ['recompute_all', 'recompute_stale', 'recompute_by_city'];

    // ── Enqueue ────────────────────────────────────────────────────────

    public function enqueueScreen(int $screenId, int $priority = 100, array $payload = []): RecomputeJob
    {
        return $this->audit('enqueue_screen', "screen_id={$screenId}", function () use ($screenId, $priority, $payload) {
            return RecomputeJob::create([
                'job_type'     => 'screen',
                'screen_id'    => $screenId,
                'payload'      => $this->tagActor($payload),
                'priority'     => $priority,
                'status'       => 'pending',
                'retry_count'  => 0,
                'requested_at' => now(),
            ]);
        });
    }

    public function enqueueCity(string $city, int $priority = 200, array $payload = []): RecomputeJob
    {
        return $this->audit('enqueue_city', "city={$city}", function () use ($city, $priority, $payload) {
            return RecomputeJob::create([
                'job_type'     => 'city',
                'city'         => $city,
                'payload'      => $this->tagActor($payload),
                'priority'     => $priority,
                'status'       => 'pending',
                'retry_count'  => 0,
                'requested_at' => now(),
            ]);
        });
    }

    /**
     * Enqueue bulk với explicit list of screen IDs (legacy — từ guide 02).
     * Khuyến nghị dùng enqueueBulkAction() nếu target là "tất cả" hoặc "stale".
     *
     * @throws \InvalidArgumentException nếu rỗng hoặc > 5000 (hard cap để tránh payload phình)
     */
    public function enqueueBulk(array $screenIds, int $priority = 150, array $extraPayload = []): RecomputeJob
    {
        if (empty($screenIds)) {
            throw new \InvalidArgumentException('screen_ids cannot be empty');
        }
        if (count($screenIds) > 5000) {
            throw new \InvalidArgumentException(
                'screen_ids > 5000 — use enqueueBulkAction("recompute_all") thay vì carry list lớn qua payload'
            );
        }

        return $this->audit('enqueue_bulk', 'ids=' . count($screenIds), function () use ($screenIds, $priority, $extraPayload) {
            return RecomputeJob::create([
                'job_type'     => 'bulk',
                'payload'      => $this->tagActor(array_merge($extraPayload, [
                    'screen_ids' => array_values($screenIds),
                ])),
                'priority'     => $priority,
                'status'       => 'pending',
                'retry_count'  => 0,
                'requested_at' => now(),
            ]);
        });
    }

    /**
     * Phase 2.B: enqueue bulk job với action dispatch.
     * Data Engine worker tự xác định screens theo action, không cần Laravel preload list.
     *
     * @param  string  $action   'recompute_all' | 'recompute_stale' | 'recompute_by_city'
     * @param  ?string $city     bắt buộc nếu action='recompute_by_city'
     */
    public function enqueueBulkAction(string $action, ?string $city = null, int $priority = 150): RecomputeJob
    {
        if (! in_array($action, self::BULK_ACTIONS, true)) {
            throw new \InvalidArgumentException(
                "Invalid action '{$action}'. Valid: " . implode(', ', self::BULK_ACTIONS)
            );
        }
        if ($action === 'recompute_by_city' && ! $city) {
            throw new \InvalidArgumentException("city required for action=recompute_by_city");
        }

        $target = $action . ($city ? ":{$city}" : '');

        return $this->audit('enqueue_bulk_action', $target, function () use ($action, $city, $priority) {
            return RecomputeJob::create([
                'job_type'     => 'bulk',
                'payload'      => $this->tagActor([
                    'action' => $action,
                    'city'   => $city,
                ]),
                'priority'     => $priority,
                'status'       => 'pending',
                'retry_count'  => 0,
                'requested_at' => now(),
            ]);
        });
    }

    // ── Mutate existing jobs ──────────────────────────────────────────

    /**
     * Reset failed/cancelled job to pending để worker pick lại.
     * retry_count = 0 (fresh attempt). started_at/finished_at/error cleared.
     */
    public function retry(int $jobId): RecomputeJob
    {
        $job = RecomputeJob::findOrFail($jobId);

        if (! in_array($job->status, ['failed', 'cancelled'], true)) {
            throw new \InvalidArgumentException("Cannot retry job in status {$job->status}");
        }

        return $this->audit('retry_job', "id={$jobId}", function () use ($job) {
            $job->update([
                'status'        => 'pending',
                'retry_count'   => 0,
                'error_message' => null,
                'started_at'    => null,
                'finished_at'   => null,
                'cancelled_at'  => null,
                'payload'       => $this->tagActor($job->payload ?? [], 'retried'),
            ]);
            return $job->fresh();
        });
    }

    /**
     * Cancel job — semantic theo Phase 2.B (PHASE-2B-HANDOFF §1.4):
     *   - pending: hard cancel (set status + cancelled_at + finished_at ngay)
     *   - processing + bulk: cooperative cancel (chỉ flip status, worker tự detect)
     *   - processing + screen/city: NOT cancellable (runs to completion)
     *   - terminal: error
     */
    public function cancel(int $jobId): RecomputeJob
    {
        $job = RecomputeJob::findOrFail($jobId);

        if (! $job->is_cancellable) {
            throw new \InvalidArgumentException(
                "Cannot cancel job #{$jobId} (status={$job->status}, type={$job->job_type}). " .
                "Only pending jobs or processing bulk jobs can be cancelled."
            );
        }

        return $this->audit('cancel_job', "id={$jobId}", function () use ($job) {
            $updates = [
                'status'  => 'cancelled',
                'payload' => $this->tagActor($job->payload ?? [], 'cancelled'),
            ];
            // pending: set timestamps ngay
            // processing+bulk: worker sẽ tự set cancelled_at + finished_at khi dừng
            if ($job->status === 'pending') {
                $updates['cancelled_at'] = now();
                $updates['finished_at']  = now();
            }
            $job->update($updates);
            return $job->fresh();
        });
    }

    // ── Read (for UI) ─────────────────────────────────────────────────

    /**
     * Progress payload của 1 job — dùng cho poll endpoint / auto-refresh widget.
     * Returns null nếu job không tồn tại.
     */
    public function getProgress(int $jobId): ?array
    {
        $job = RecomputeJob::find($jobId);
        if (! $job) return null;

        return [
            'status'       => $job->status,
            'progress'     => $job->progress_data,
            'percent'      => $job->progress_percent,
            'started_at'   => $job->started_at?->toIso8601String(),
            'finished_at'  => $job->finished_at?->toIso8601String(),
            'cancelled_at' => $job->cancelled_at?->toIso8601String(),
            'error'        => $job->error_message,
        ];
    }

    /**
     * Counts by status — dùng cho header badges + navigation badge.
     * Silent fail nếu tunnel down → returns empty.
     */
    public function countsByStatus(): array
    {
        try {
            return DB::connection(self::CONNECTION)
                ->table('core.recompute_jobs')
                ->selectRaw('status, COUNT(*) as c')
                ->groupBy('status')
                ->pluck('c', 'status')
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /**
     * Tag payload với actor + timestamp cho audit trail (dạng nested dict).
     * Key 'action' default 'enqueued' (override khi retry/cancel).
     */
    private function tagActor(array $payload, string $event = 'enqueued'): array
    {
        $payload['_actor'][$event] = [
            'by' => $this->resolveActor(),
            'at' => now()->toIso8601String(),
        ];
        return $payload;
    }

    private function resolveActor(): string
    {
        $u = Auth::user();
        if (! $u) return 'system';
        return $u->email ?? "web:{$u->id}";
    }

    /**
     * Execute callback in transaction, write audit log entry after success.
     * Re-throw exception từ callback để caller xử lý (không swallow).
     */
    private function audit(string $action, string $target, \Closure $callback)
    {
        $actor = $this->resolveActor();

        return DB::connection(self::CONNECTION)->transaction(function () use ($action, $target, $callback, $actor) {
            $result = $callback();

            AuditLog::create([
                'actor'      => $actor,
                'action'     => $action,
                'target'     => $target,
                'new_value'  => $result instanceof RecomputeJob
                    ? ['job_id' => $result->id, 'status' => $result->status, 'job_type' => $result->job_type]
                    : null,
                'created_at' => now(),
            ]);

            return $result;
        });
    }
}
