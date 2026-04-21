<?php

namespace App\Services\Oohx;

/**
 * Helper parse preview result JSON (PHASE-3A-HANDOFF §4 schema) thành shape
 * tiện cho Blade render.
 *
 * Stateless static methods — không dependency DB/service, chỉ transform data.
 *
 * Warning tiers theo p99 delta_pct (handoff §5.4):
 *   < 10% → 'low' (success green)
 *   10-30% → 'medium' (warning yellow)
 *   30-50% → 'high' (orange)
 *   > 50% → 'critical' (danger red)
 */
class PreviewResultFormatter
{
    public const WARNING_THRESHOLDS = [
        'low'      => ['max' => 0.10, 'color' => 'success', 'label' => 'Low impact',  'message' => 'Safe to activate.'],
        'medium'   => ['max' => 0.30, 'color' => 'warning', 'label' => 'Medium',      'message' => 'Review top changes before activating.'],
        'high'     => ['max' => 0.50, 'color' => 'warning', 'label' => 'High',        'message' => 'Check top_deltas carefully — review với team trước khi activate.'],
        'critical' => ['max' => null, 'color' => 'danger',  'label' => 'Critical',    'message' => 'Confirm với product team trước khi activate.'],
    ];

    /**
     * Determine warning tier cho 1 metric (delta_pct_p99 as abs value).
     *
     * @return array{tier:string, color:string, label:string, message:string}
     */
    public static function warningTier(?float $deltaPctP99): array
    {
        if ($deltaPctP99 === null) {
            return ['tier' => 'unknown', 'color' => 'gray', 'label' => '—', 'message' => 'No data'];
        }
        $abs = abs($deltaPctP99);
        foreach (self::WARNING_THRESHOLDS as $tier => $meta) {
            if ($meta['max'] === null || $abs < $meta['max']) {
                return ['tier' => $tier] + $meta;
            }
        }
        return ['tier' => 'critical'] + self::WARNING_THRESHOLDS['critical'];
    }

    /**
     * Aggregate worst p99 across all 3 metrics (daily_impressions, daily_ots, confidence_score)
     * → dùng làm overall warning badge.
     */
    public static function overallWarning(array $result): array
    {
        $worst = 0.0;
        foreach (['estimated_daily_impressions', 'estimated_daily_ots', 'confidence_score'] as $key) {
            $p99 = $result['metrics'][$key]['delta_pct_p99'] ?? null;
            if ($p99 === null) continue;
            $worst = max($worst, abs($p99));
        }
        return self::warningTier($worst);
    }

    /**
     * Format delta_pct decimal (0.08) → human string "+8%" / "-12%".
     */
    public static function pct(?float $decimal, int $precision = 1): string
    {
        if ($decimal === null) return '—';
        $sign = $decimal >= 0 ? '+' : '';
        return $sign . number_format($decimal * 100, $precision) . '%';
    }

    /**
     * Format large number "15,234,567" (JSON thường là float).
     */
    public static function num(float|int|null $n): string
    {
        if ($n === null) return '—';
        return number_format((float) $n, 0, '.', ',');
    }

    /**
     * Label đẹp cho metric key.
     */
    public static function metricLabel(string $key): string
    {
        return match ($key) {
            'estimated_daily_impressions' => 'Daily Impressions',
            'estimated_daily_ots'         => 'Daily OTS',
            'confidence_score'            => 'Confidence Score',
            default                       => ucfirst(str_replace('_', ' ', $key)),
        };
    }

    /**
     * Bar width % (for histogram bar chart, 0..100).
     * Map delta_pct decimal (-1 to 1+) → width 0..100 with 50 = neutral (0%).
     */
    public static function barWidth(?float $deltaPct, float $scale = 1.0): int
    {
        if ($deltaPct === null) return 0;
        $pct = max(-1, min(1, $deltaPct / $scale)); // clamp to [-1, 1]
        return (int) round(abs($pct) * 100);
    }
}
