<?php

namespace App\Services\Oohx;

use App\Models\Oohx\AnalyticsCampaignWeekly;
use App\Models\Oohx\AnalyticsCityPerformance;
use App\Models\Oohx\AnalyticsFormulaVersionImpact;
use App\Models\Oohx\AnalyticsScreenUtilization;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Helpers cho OohxAnalytics dashboard — Phase 4.2.7.
 *
 * Cache 5 phút mỗi query để tránh re-hit DE qua tunnel khi user reload page.
 * MV refresh daily (04:15 UTC) nên cache 5 phút stale acceptable.
 *
 * Graceful fallback: nếu MVs chưa exist (migration 013 chưa apply) → silent
 * trả empty collection thay vì crash. UI hiện banner "Analytics not yet available".
 */
class AnalyticsService
{
    private const CACHE_TTL = 300; // 5 minutes

    /**
     * 12 tuần trailing trend, sort cũ → mới (dễ chart).
     */
    public function weeklyTrend(int $weeks = 12): Collection
    {
        return $this->safeCachedQuery("analytics:weekly:{$weeks}", function () use ($weeks) {
            return AnalyticsCampaignWeekly::query()
                ->orderByDesc('week_start')
                ->limit($weeks)
                ->get()
                ->reverse()
                ->values();
        });
    }

    /**
     * Tuần hiện tại + tuần trước → diff cho KPI cards.
     *
     * @return array{current: ?AnalyticsCampaignWeekly, previous: ?AnalyticsCampaignWeekly, diff: array}
     */
    public function weekOverWeek(): array
    {
        $rows = $this->weeklyTrend(2);
        if ($rows->count() < 1) {
            return ['current' => null, 'previous' => null, 'diff' => []];
        }
        $current  = $rows->last();
        $previous = $rows->count() > 1 ? $rows->first() : null;

        $diff = [];
        if ($previous) {
            foreach (['campaigns_count', 'total_impressions', 'total_reach'] as $key) {
                $cur = (int) ($current->$key ?? 0);
                $prv = (int) ($previous->$key ?? 0);
                $diff[$key] = [
                    'absolute' => $cur - $prv,
                    'pct'      => $prv > 0 ? round((($cur - $prv) / $prv) * 100, 1) : null,
                ];
            }
        }

        return ['current' => $current, 'previous' => $previous, 'diff' => $diff];
    }

    /**
     * Top N cities by total daily impressions.
     */
    public function topCities(int $limit = 10): Collection
    {
        return $this->safeCachedQuery("analytics:cities:{$limit}", function () use ($limit) {
            return AnalyticsCityPerformance::query()
                ->orderByDesc('total_daily_impressions')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Top N most-booked screens (90d window).
     */
    public function topUtilizedScreens(int $limit = 20): Collection
    {
        return $this->safeCachedQuery("analytics:utilization:top:{$limit}", function () use ($limit) {
            return AnalyticsScreenUtilization::query()
                ->where('campaign_count_90d', '>', 0)
                ->orderByDesc('allocated_impressions_90d')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Đếm screens có row trong utilization MV (= booked ≥ 1 lần trong 90d).
     * Để compare với total active screens cho "% utilization" KPI.
     */
    public function utilizationCounts(): array
    {
        return $this->safeCachedQuery("analytics:utilization:counts", function () {
            $booked   = AnalyticsScreenUtilization::query()->where('campaign_count_90d', '>', 0)->count();
            $totalMV  = AnalyticsScreenUtilization::query()->count();
            return ['booked' => $booked, 'total_with_data' => $totalMV];
        }, default: ['booked' => 0, 'total_with_data' => 0]);
    }

    /**
     * Formula versions với impact metrics, active first.
     */
    public function formulaVersionImpact(int $limit = 10): Collection
    {
        return $this->safeCachedQuery("analytics:formula:{$limit}", function () use ($limit) {
            return AnalyticsFormulaVersionImpact::query()
                ->orderByDesc('is_active')
                ->orderByDesc('activated_at')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Staleness của analytics data — lấy max(computed_at) từ city_performance
     * (mọi MV refresh cùng cron 04:15 UTC nên 1 timestamp đủ representative).
     *
     * @return array{computed_at: ?Carbon, age_minutes: ?int, color: string, label: string}
     */
    public function staleness(): array
    {
        $computedAt = $this->safeCachedQuery('analytics:staleness', function () {
            return AnalyticsCityPerformance::query()->max('computed_at');
        });

        if (! $computedAt) {
            return [
                'computed_at' => null,
                'age_minutes' => null,
                'color'       => 'gray',
                'label'       => 'Analytics chưa có data',
            ];
        }

        $at = $computedAt instanceof Carbon ? $computedAt : Carbon::parse($computedAt);
        $age = (int) $at->diffInMinutes(now());

        // Daily refresh — fresh < 26h, stale 26-48h, dead > 48h
        $color = match (true) {
            $age < 60 * 26 => 'success',
            $age < 60 * 48 => 'warning',
            default        => 'danger',
        };

        $label = $age < 60
            ? "{$age} min ago"
            : ($age < 1440
                ? round($age / 60, 1) . 'h ago'
                : round($age / 1440, 1) . 'd ago');

        return ['computed_at' => $at, 'age_minutes' => $age, 'color' => $color, 'label' => $label];
    }

    /**
     * Detect xem MVs đã được DE migrate chưa.
     * Nếu false → UI hiện empty state "Analytics not yet deployed".
     */
    public function isAvailable(): bool
    {
        return Cache::remember('analytics:available', self::CACHE_TTL, function () {
            try {
                AnalyticsCityPerformance::query()->limit(1)->exists();
                return true;
            } catch (\Throwable) {
                return false;
            }
        });
    }

    public function flushCache(): void
    {
        foreach ([
            'analytics:weekly:12', 'analytics:weekly:2',
            'analytics:cities:10',
            'analytics:utilization:top:20', 'analytics:utilization:counts',
            'analytics:formula:10',
            'analytics:staleness', 'analytics:available',
        ] as $k) {
            Cache::forget($k);
        }
    }

    /**
     * Wrap query trong cache + try/catch — log error nhưng return default thay vì crash.
     */
    private function safeCachedQuery(string $key, \Closure $fn, mixed $default = null): mixed
    {
        return Cache::remember($key, self::CACHE_TTL, function () use ($fn, $default) {
            try {
                return $fn();
            } catch (\Throwable $e) {
                \Log::warning('AnalyticsService query failed', ['error' => $e->getMessage()]);
                return $default ?? collect();
            }
        });
    }
}
