<?php

namespace App\Services\Oohx;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Đọc health digest JSON từ DE VPS (Phase 3.A Part 2 Option B).
 *
 * Digest được DE cron (08:00 UTC daily) ghi lên `/home/oohx/logs/health-digest-YYYYMMDD.json`.
 * Laravel-side: `oohx:fetch-health` command scp file về `storage/app/oohx-health/` mỗi 30 phút.
 *
 * Service này stateless, chỉ parse + cache 60s để giảm disk IO từ Filament polling.
 *
 * Schema reference: handoff §3.3 (semver — add field OK, rename/remove = breaking).
 */
class HealthDigestService
{
    private const STORAGE_DIR = 'oohx-health';
    private const CACHE_TTL   = 60; // seconds
    private const CACHE_KEY   = 'oohx:health:latest';

    /**
     * Lấy digest mới nhất (file có filename lớn nhất trong storage).
     * Fallback qua ngày hôm qua nếu hôm nay chưa có.
     *
     * @return array{digest: array, path: string, age_minutes: int}|null
     */
    public function latest(): ?array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $path = $this->latestPath();
            if (! $path) return null;

            $raw = $this->readFile($path);
            if (! $raw) return null;

            $ageMin = $this->computeAgeMinutes($raw);

            return [
                'digest'      => $raw,
                'path'        => $path,
                'age_minutes' => $ageMin,
            ];
        });
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Resolve path của digest file mới nhất trong storage dir.
     * Prefer hôm nay, fallback hôm qua (UTC — match DE cron timezone).
     */
    public function latestPath(): ?string
    {
        $disk = Storage::disk('local');

        // Try today first, then walk back up to 7 days
        $today = now('UTC');
        for ($i = 0; $i < 7; $i++) {
            $date = $today->copy()->subDays($i)->format('Ymd');
            $candidate = self::STORAGE_DIR . "/health-digest-{$date}.json";
            if ($disk->exists($candidate)) {
                return $disk->path($candidate);
            }
        }

        // Broader scan — find any matching file
        $files = $disk->files(self::STORAGE_DIR);
        $matches = array_values(array_filter(
            $files,
            fn ($f) => preg_match('#/health-digest-\d{8}\.json$#', $f) || preg_match('#^' . self::STORAGE_DIR . '/health-digest-\d{8}\.json$#', $f),
        ));
        if (! $matches) return null;

        rsort($matches); // newest first by filename
        return $disk->path($matches[0]);
    }

    private function readFile(string $path): ?array
    {
        if (! is_readable($path)) return null;
        $content = @file_get_contents($path);
        if (! $content) return null;

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Tính phút từ `checked_at` (ISO 8601 UTC) tới bây giờ.
     * Missing/malformed → return PHP_INT_MAX (treat as stale).
     */
    private function computeAgeMinutes(array $digest): int
    {
        $at = $digest['checked_at'] ?? null;
        if (! $at) return PHP_INT_MAX;
        try {
            return (int) Carbon::parse($at)->diffInMinutes(now());
        } catch (\Throwable) {
            return PHP_INT_MAX;
        }
    }

    // ── UI helpers ──────────────────────────────────────────────────────

    public const STALE_WARN_MINUTES     = 120;  // 2h
    public const STALE_CRITICAL_MINUTES = 720;  // 12h

    /**
     * Map digest["status"] + age → overall UI status + color + label.
     *
     * @return array{status: string, color: string, label: string, icon: string}
     */
    public function overallBadge(?array $result): array
    {
        if (! $result) {
            return [
                'status' => 'unknown',
                'color'  => 'gray',
                'label'  => 'No data',
                'icon'   => 'heroicon-o-question-mark-circle',
            ];
        }

        $age = $result['age_minutes'] ?? PHP_INT_MAX;
        if ($age > self::STALE_CRITICAL_MINUTES) {
            return [
                'status' => 'critical',
                'color'  => 'danger',
                'label'  => 'Stale > 12h',
                'icon'   => 'heroicon-o-exclamation-triangle',
            ];
        }

        $digestStatus = $result['digest']['status'] ?? 'unknown';
        return match ($digestStatus) {
            'ok' => [
                'status' => 'ok',
                'color'  => 'success',
                'label'  => 'OK',
                'icon'   => 'heroicon-o-check-circle',
            ],
            'warn' => [
                'status' => 'warn',
                'color'  => 'warning',
                'label'  => 'Warning',
                'icon'   => 'heroicon-o-exclamation-triangle',
            ],
            'critical' => [
                'status' => 'critical',
                'color'  => 'danger',
                'label'  => 'Critical',
                'icon'   => 'heroicon-o-exclamation-triangle',
            ],
            default => [
                'status' => 'unknown',
                'color'  => 'gray',
                'label'  => 'Unknown',
                'icon'   => 'heroicon-o-question-mark-circle',
            ],
        };
    }

    /**
     * Map per-check status → color.
     */
    public function checkColor(?string $status): string
    {
        return match ($status) {
            'ok'       => 'success',
            'warn'     => 'warning',
            'critical' => 'danger',
            default    => 'gray',
        };
    }

    /**
     * Human-friendly label cho check key.
     */
    public function checkLabel(string $key): string
    {
        return match ($key) {
            'db_connection'           => 'Database connection',
            'collector_queue_backlog' => 'Collector queue backlog',
            'collector_stale'         => 'Collector staleness',
            'job_queue_backlog'       => 'Job queue backlog',
            'job_failure_rate_24h'    => 'Job failure rate (24h)',
            'formula_coverage'        => 'Formula coverage',
            'enrichment_stale'        => 'Enrichment freshness',
            'weather_freshness'       => 'Weather freshness',
            default                   => ucfirst(str_replace('_', ' ', $key)),
        };
    }

    public function checkIcon(string $key): string
    {
        return match ($key) {
            'db_connection'           => 'heroicon-o-circle-stack',
            'collector_queue_backlog' => 'heroicon-o-queue-list',
            'collector_stale'         => 'heroicon-o-clock',
            'job_queue_backlog'       => 'heroicon-o-queue-list',
            'job_failure_rate_24h'    => 'heroicon-o-bug-ant',
            'formula_coverage'        => 'heroicon-o-variable',
            'enrichment_stale'        => 'heroicon-o-sparkles',
            'weather_freshness'       => 'heroicon-o-cloud',
            default                   => 'heroicon-o-cube',
        };
    }

    /**
     * Format "value" display tuỳ check type.
     */
    public function formatCheckValue(string $key, array $check): string
    {
        $v = $check['value'] ?? null;

        return match ($key) {
            'db_connection'           => $v ? 'Connected' : 'Down',
            'collector_queue_backlog' => ($v ?? 0) . ' pending',
            'job_queue_backlog'       => ($v ?? 0) . ' pending',
            'job_failure_rate_24h'    => is_numeric($v) ? round($v * 100, 1) . '%' : '—',
            'formula_coverage'        => is_numeric($v) ? round($v * 100, 1) . '%' : '—',
            'collector_stale'         => is_array($v) && count($v) > 0
                ? count($v) . ' collector(s) stale'
                : 'All fresh',
            'enrichment_stale'        => ($v ?? 0) . ' stale screen(s)',
            'weather_freshness'       => is_array($v)
                ? implode(' · ', array_map(
                    fn ($city, $data) => "{$city} " . round($data['hours_since'] ?? 0, 1) . 'h',
                    array_keys($v),
                    $v,
                ))
                : '—',
            default                   => is_scalar($v) ? (string) $v : json_encode($v),
        };
    }
}
