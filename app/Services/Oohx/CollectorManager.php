<?php

namespace App\Services\Oohx;

use App\Models\Oohx\Config\AuditLog;
use App\Models\Oohx\CollectorRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Orchestration cho Data Engine collectors (Phase 2.C).
 *
 * Write surface:
 *   - INSERT collectors.collector_runs (trigger)
 *   - UPDATE collector_runs.status → cancelled (pending only)
 *   - INSERT config.audit_log (trace ops actions)
 *
 * Read:
 *   - latestByCollector(): latest run per (collector_name, city) — cho Overview Page
 *   - cityOptionsForCollector(): built-in + cities có active screens
 *   - computeStaleness(): age vs cadence → green/yellow/red/gray
 *   - countsByStatus(): header counters
 *   - overdueCount(): số (collector, city) quá cadence × 2 (cho nav badge)
 *
 * Không bao giờ:
 *   - UPDATE status → done/failed/running (worker Python own)
 *   - UPDATE stats, rows_ingested, bytes_fetched (worker own)
 */
class CollectorManager
{
    private const CONNECTION = 'oohx_control';

    // ── Trigger ────────────────────────────────────────────────────────

    /**
     * Enqueue collector run. Python cron (mỗi 15 phút) sẽ drain queue.
     *
     * @param  string      $collectorName  'overpass_poi' | 'open_meteo_weather' | ... (config key)
     * @param  string|null $city           null nếu collector không cần city
     * @param  array       $params         collector-specific params (bbox, forecast_hours, ...)
     * @param  int         $priority       smaller = higher
     *
     * @throws \InvalidArgumentException nếu collector không tồn tại trong config
     *                                   hoặc city required mà không provide
     */
    public function trigger(
        string $collectorName,
        ?string $city = null,
        array $params = [],
        int $priority = 100,
    ): CollectorRun {
        $meta = $this->collectorMeta($collectorName);

        if ($meta['supports_city'] && ! $city) {
            throw new \InvalidArgumentException(
                "City required for collector '{$collectorName}'"
            );
        }

        $actor = $this->resolveActor();
        $target = $city ? "{$collectorName}:{$city}" : $collectorName;

        return DB::connection(self::CONNECTION)->transaction(function () use (
            $collectorName, $city, $params, $priority, $actor, $target
        ) {
            $run = CollectorRun::create([
                'collector_name' => $collectorName,
                'city'           => $city,
                'params'         => $params,
                'priority'       => $priority,
                'status'         => 'pending',
                'retry_count'    => 0,
                'rows_ingested'  => 0,
                'bytes_fetched'  => 0,
                'stats'          => [],
                'requested_by'   => $actor,
                'requested_at'   => now(),
            ]);

            AuditLog::create([
                'actor'      => $actor,
                'action'     => 'trigger_collector',
                'target'     => $target,
                'new_value'  => ['run_id' => $run->id, 'params' => $params],
                'created_at' => now(),
            ]);

            return $run;
        });
    }

    /**
     * Cancel pending run. Không support cooperative cancel (handoff §2.2) —
     * running runs phải chạy tới khi worker finish.
     */
    public function cancel(int $runId): CollectorRun
    {
        $run = CollectorRun::findOrFail($runId);

        if (! $run->is_cancellable) {
            throw new \InvalidArgumentException(
                "Cannot cancel run #{$runId} in status '{$run->status}'. " .
                "Only pending runs cancellable (running runs execute to completion)."
            );
        }

        $actor = $this->resolveActor();

        return DB::connection(self::CONNECTION)->transaction(function () use ($run, $actor) {
            $run->update([
                'status'      => 'cancelled',
                'finished_at' => now(),
            ]);

            AuditLog::create([
                'actor'      => $actor,
                'action'     => 'cancel_collector_run',
                'target'     => "run_id={$run->id}",
                'new_value'  => ['collector' => $run->collector_name, 'city' => $run->city],
                'created_at' => now(),
            ]);

            return $run->fresh();
        });
    }

    // ── Read (Overview Page + Resource) ───────────────────────────────

    /**
     * Latest done run cho từng (collector_name, city) — dùng render Overview cards.
     * Returns nested dict: [collector_name => [city => CollectorRun]].
     *
     * Include cả pending/running nếu chưa có done → ops thấy "đang chạy".
     */
    public function latestByCollectorAndCity(): array
    {
        try {
            // Lấy latest 1 row per (collector_name, city) dùng DISTINCT ON (PostgreSQL-specific)
            $rows = DB::connection(self::CONNECTION)->select("
                SELECT DISTINCT ON (collector_name, city) *
                FROM collectors.collector_runs
                ORDER BY collector_name, city, requested_at DESC NULLS LAST
            ");

            $result = [];
            foreach ($rows as $row) {
                $model = (new CollectorRun)->forceFill((array) $row);
                $model->exists = true;
                $result[$row->collector_name][$row->city] = $model;
            }
            return $result;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Staleness indicator theo (collector_name, city).
     * Returns ['level' => 'green'|'yellow'|'red'|'gray', 'label' => string, 'age_hours' => float|null].
     *
     * Rules (handoff §3.1):
     *   age < cadence          → green (fresh)
     *   cadence ≤ age < 2×     → yellow (due soon)
     *   age ≥ 2×cadence        → red (overdue)
     *   never run              → gray
     *   last run failed        → red (treat như overdue để ops attention)
     */
    public function computeStaleness(string $collectorName, ?CollectorRun $latestRun): array
    {
        if (! $latestRun) {
            return ['level' => 'gray', 'label' => 'never run', 'age_hours' => null];
        }

        if ($latestRun->status === 'failed') {
            return ['level' => 'red', 'label' => 'last failed', 'age_hours' => null];
        }

        $meta = $this->collectorMeta($collectorName);
        $cadence = (float) ($meta['cadence_hours'] ?? 24);

        // Khi pending/running, show "in progress" color
        if (in_array($latestRun->status, ['pending', 'running'], true)) {
            return ['level' => 'blue', 'label' => $latestRun->status, 'age_hours' => null];
        }

        if (! $latestRun->finished_at) {
            return ['level' => 'gray', 'label' => 'unknown', 'age_hours' => null];
        }

        $ageHours = Carbon::parse($latestRun->finished_at)->diffInHours(now(), absolute: true);

        if ($ageHours < $cadence)       return ['level' => 'green',  'label' => 'fresh',     'age_hours' => $ageHours];
        if ($ageHours < $cadence * 2)   return ['level' => 'yellow', 'label' => 'due soon',  'age_hours' => $ageHours];
        return ['level' => 'red', 'label' => 'overdue', 'age_hours' => $ageHours];
    }

    /**
     * City options cho trigger dropdown.
     * Built-in cities từ config + cities có ≥ 1 active screen trong core.screens
     * (để cover các city đã sync nhưng không trong built-in list).
     *
     * Returns assoc [city => label]; label có thể thêm "(builtin)" / "(from screens)" badge.
     */
    public function cityOptionsForCollector(string $collectorName): array
    {
        $builtin = config('oohx_collectors.builtin_cities', []);

        $screenCities = $this->citiesFromScreens();

        $options = [];
        foreach ($builtin as $c) {
            $options[$c] = $c; // prefer plain label
        }
        foreach ($screenCities as $c) {
            if (! isset($options[$c])) {
                $options[$c] = "{$c} (from screens)";
            }
        }

        ksort($options);
        return $options;
    }

    /**
     * Kiểm tra city có ≥ 1 active screen không (validation trước trigger).
     */
    public function cityHasScreens(string $city): bool
    {
        try {
            $n = DB::connection(self::CONNECTION)
                ->table('core.screens')
                ->where('city', $city)
                ->where('status', 'active')
                ->count();
            return $n > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Counts by status cho header counters.
     */
    public function countsByStatus(): array
    {
        try {
            return DB::connection(self::CONNECTION)
                ->table('collectors.collector_runs')
                ->selectRaw('status, COUNT(*) as c')
                ->groupBy('status')
                ->pluck('c', 'status')
                ->toArray();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Overdue count — dùng cho navigation badge trên Overview Page.
     * Overdue = staleness level 'red' (bao gồm failed lastest).
     */
    public function overdueCount(): int
    {
        $latest = $this->latestByCollectorAndCity();
        if (empty($latest)) return 0;

        $count = 0;
        foreach ($latest as $collectorName => $byCity) {
            foreach ($byCity as $run) {
                $st = $this->computeStaleness($collectorName, $run);
                if ($st['level'] === 'red') $count++;
            }
        }
        return $count;
    }

    // ── Helpers ────────────────────────────────────────────────────────

    /**
     * @throws \InvalidArgumentException nếu collector không trong config
     */
    public function collectorMeta(string $name): array
    {
        $all = config('oohx_collectors', []);
        if (! isset($all[$name]) || ! is_array($all[$name])) {
            throw new \InvalidArgumentException("Unknown collector '{$name}'");
        }
        return $all[$name];
    }

    /**
     * Danh sách collectors từ config (loại bỏ 'builtin_cities' meta key).
     */
    public function listCollectors(): array
    {
        $cfg = config('oohx_collectors', []);
        unset($cfg['builtin_cities']);
        return $cfg;
    }

    private function citiesFromScreens(): array
    {
        try {
            $rows = DB::connection(self::CONNECTION)->select("
                SELECT DISTINCT city FROM core.screens
                WHERE city IS NOT NULL AND city != '' AND status = 'active'
                ORDER BY city
            ");
            return collect($rows)->pluck('city')->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function resolveActor(): string
    {
        $u = Auth::user();
        if (! $u) return 'system';
        return $u->email ?? "web:{$u->id}";
    }
}
