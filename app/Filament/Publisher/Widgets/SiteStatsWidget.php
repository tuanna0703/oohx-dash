<?php

namespace App\Filament\Publisher\Widgets;

use App\Models\Screen;
use App\Models\Site;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SiteStatsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;
    protected static ?int $sort = -1;

    // ─────────────────────────────────────────────────────────────────────────

    protected function getOwnerId(): string
    {
        return (string) (auth()->user()?->current_owner_id ?? '');
    }

    // ─────────────────────────────────────────────────────────────────────────

    protected function getStats(): array
    {
        $ownerId = $this->getOwnerId();

        // Single aggregate query cho tất cả site stats
        $s = cache()->remember("site_stats_{$ownerId}", 60, function () use ($ownerId) {
            $sites = \DB::table('sites')
                ->where('owner_id', $ownerId)
                ->whereNull('deleted_at')
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN status = 'paused' THEN 1 ELSE 0 END) as paused,
                    SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed,
                    COUNT(DISTINCT CASE WHEN city   IS NOT NULL AND city   != '' THEN city   END) as cities,
                    COUNT(DISTINCT CASE WHEN region IS NOT NULL AND region != '' THEN region END) as regions
                ")
                ->first();

            $withScreens = \DB::table('screens')
                ->where('owner_id', $ownerId)
                ->whereNull('deleted_at')
                ->whereNotNull('site_id')
                ->distinct('site_id')
                ->count('site_id');

            return [$sites, $withScreens];
        });

        [$row, $withScreens] = $s;

        $total       = (int) ($row->total  ?? 0);
        $active      = (int) ($row->active ?? 0);
        $paused      = (int) ($row->paused ?? 0);
        $closed      = (int) ($row->closed ?? 0);
        $cities      = (int) ($row->cities  ?? 0);
        $regions     = (int) ($row->regions ?? 0);
        $withScreens = (int) $withScreens;
        $noScreens   = max(0, $total - $withScreens);

        // ─────────────────────────────────────────────────────────────────────

        $activePct = $total > 0 ? round($active / $total * 100) : 0;

        return [
            Stat::make('Tổng Sites', number_format($total))
                ->description(
                    $active . ' active'
                    . ($paused  > 0 ? ' · ' . $paused  . ' paused'  : '')
                    . ($closed  > 0 ? ' · ' . $closed  . ' closed'  : '')
                )
                ->descriptionIcon('heroicon-s-map-pin')
                ->icon('heroicon-o-map-pin')
                ->color('primary'),

            Stat::make('Sites có màn hình', number_format($withScreens))
                ->description(
                    $noScreens > 0
                        ? $noScreens . ' sites chưa có màn hình'
                        : 'Tất cả sites đã có màn hình'
                )
                ->descriptionIcon(
                    $noScreens > 0
                        ? 'heroicon-s-exclamation-circle'
                        : 'heroicon-s-check-circle'
                )
                ->icon('heroicon-o-computer-desktop')
                ->color($noScreens > 0 ? 'warning' : 'success'),

            Stat::make('Địa bàn', $cities . ' thành phố')
                ->description($regions . ' khu vực')
                ->descriptionIcon('heroicon-s-building-office-2')
                ->icon('heroicon-o-building-office-2')
                ->color('info'),
        ];
    }
}
