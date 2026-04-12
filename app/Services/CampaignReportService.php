<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\ImpressionLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CampaignReportService
{
    /**
     * Get campaign overview stats.
     */
    public function getOverview(Campaign $campaign): array
    {
        $lines = $campaign->bookingLines()->whereIn('status', ['active', 'completed'])->get();

        $totalEstimatedImpressions = $lines->sum('estimated_impressions');
        $totalActualImpressions = $lines->sum('actual_impressions');
        $totalEstimatedCost = $lines->sum('estimated_cost');
        $totalActualCost = $lines->sum('actual_cost');
        $totalPaid = $campaign->payments()->where('status', 'completed')->sum('amount');

        $deliveryRate = $totalEstimatedImpressions > 0
            ? round($totalActualImpressions / $totalEstimatedImpressions * 100, 1)
            : 0;

        $daysTotal = max(1, $campaign->start_date->diffInDays($campaign->end_date) + 1);
        $daysElapsed = $campaign->start_date->isPast()
            ? min($daysTotal, $campaign->start_date->diffInDays(now()) + 1)
            : 0;
        $daysRemaining = max(0, $daysTotal - $daysElapsed);

        return [
            'total_screens'              => $lines->count(),
            'total_estimated_impressions'=> $totalEstimatedImpressions,
            'total_actual_impressions'   => $totalActualImpressions,
            'delivery_rate'              => $deliveryRate,
            'total_estimated_cost'       => (float) $totalEstimatedCost,
            'total_actual_cost'          => (float) $totalActualCost,
            'total_paid'                 => (float) $totalPaid,
            'days_total'                 => $daysTotal,
            'days_elapsed'               => $daysElapsed,
            'days_remaining'             => $daysRemaining,
            'progress_pct'               => round($daysElapsed / $daysTotal * 100, 0),
        ];
    }

    /**
     * Get daily impressions for chart (last N days or campaign period).
     */
    public function getDailyImpressions(Campaign $campaign, ?int $days = null): array
    {
        $start = $campaign->start_date;
        $end = $days ? now() : $campaign->end_date;
        if ($days) {
            $start = now()->subDays($days - 1);
        }

        // Query impression_logs grouped by date
        $screenIds = $campaign->bookingLines()
            ->whereIn('status', ['active', 'completed'])
            ->pluck('screen_id');

        $data = ImpressionLog::whereIn('screen_id', $screenIds)
            ->where('campaign_id', $campaign->id)
            ->whereBetween('played_at', [$start->startOfDay(), $end->endOfDay()])
            ->selectRaw('DATE(played_at) as date, SUM(imp_count) as impressions, SUM(revenue_gross) as revenue')
            ->groupByRaw('DATE(played_at)')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        // Fill missing dates with zero
        $labels = [];
        $impressions = [];
        $revenue = [];
        $period = $start->copy();

        while ($period->lte($end) && $period->lte(now())) {
            $key = $period->format('Y-m-d');
            $labels[] = $period->format('d/m');
            $impressions[] = (int) ($data[$key]->impressions ?? 0);
            $revenue[] = (float) ($data[$key]->revenue ?? 0);
            $period->addDay();
        }

        return [
            'labels'      => $labels,
            'impressions' => $impressions,
            'revenue'     => $revenue,
        ];
    }

    /**
     * Get per-screen breakdown for table.
     */
    public function getScreenBreakdown(Campaign $campaign): array
    {
        return $campaign->bookingLines()
            ->with(['screen.spec', 'screen.owner', 'screen.site'])
            ->whereIn('status', ['active', 'completed', 'approved'])
            ->get()
            ->map(fn ($line) => [
                'screen_name'           => $line->screen->name,
                'owner_name'            => $line->screen->owner?->name ?? '—',
                'city'                  => $line->screen->site?->city ?? '—',
                'dates'                 => $line->start_date->format('d/m') . ' → ' . $line->end_date->format('d/m'),
                'estimated_impressions' => $line->estimated_impressions,
                'actual_impressions'    => $line->actual_impressions,
                'delivery_rate'         => $line->estimated_impressions > 0
                    ? round($line->actual_impressions / $line->estimated_impressions * 100, 1)
                    : 0,
                'estimated_cost'        => (float) $line->estimated_cost,
                'actual_cost'           => (float) $line->actual_cost,
                'status'                => $line->status,
            ])
            ->toArray();
    }
}
