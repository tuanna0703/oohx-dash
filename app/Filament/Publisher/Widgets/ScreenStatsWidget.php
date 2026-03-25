<?php

namespace App\Filament\Publisher\Widgets;

use App\Models\Network;
use App\Models\Screen;
use App\Models\Site;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ScreenStatsWidget extends BaseWidget
{
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

        // Single aggregate query for all screen stats
        $cutoff = now()->subMinutes(5)->toDateTimeString();
        $screenStats = cache()->remember("screen_stats_{$ownerId}", 60, function () use ($ownerId, $cutoff) {
            return \DB::table('screens')
                ->where('owner_id', $ownerId)
                ->whereNull('deleted_at')
                ->selectRaw('
                    COUNT(*) as total,
                    SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN last_heartbeat_at >= ? THEN 1 ELSE 0 END) as online,
                    COUNT(DISTINCT site_id) as sites_with_screens
                ', [$cutoff])
                ->first();
        });

        $total            = (int) ($screenStats->total ?? 0);
        $active           = (int) ($screenStats->active ?? 0);
        $online           = (int) ($screenStats->online ?? 0);
        $inactive         = $total - $active;
        $sitesWithScreens = (int) ($screenStats->sites_with_screens ?? 0);

        // Separate lightweight count queries (cached)
        [$totalSites, $totalNetworks] = cache()->remember("site_network_counts_{$ownerId}", 60, function () use ($ownerId) {
            return [
                \DB::table('sites')->where('owner_id', $ownerId)->whereNull('deleted_at')->count(),
                \DB::table('networks')->where('owner_id', $ownerId)->whereNull('deleted_at')->count(),
            ];
        });

        // ─────────────────────────────────────────────────────────────────────

        $activePct = $total > 0 ? round($active / $total * 100) : 0;

        return [
            Stat::make('Tổng màn hình', number_format($total))
                ->description(
                    $active . ' active' .
                    ($online > 0 ? ' · ' . $online . ' online' : '')
                )
                ->descriptionIcon('heroicon-s-computer-desktop')
                ->icon('heroicon-o-computer-desktop')
                ->color('primary'),

            Stat::make('Active / Inactive', $active . ' / ' . $inactive)
                ->description($activePct . '% đang hoạt động')
                ->descriptionIcon($activePct >= 80
                    ? 'heroicon-s-arrow-trending-up'
                    : 'heroicon-s-arrow-trending-down')
                ->icon('heroicon-o-check-circle')
                ->color($activePct >= 80 ? 'success' : 'warning'),

            Stat::make('Sites', number_format($totalSites))
                ->description($sitesWithScreens . ' sites có màn hình')
                ->descriptionIcon('heroicon-s-map-pin')
                ->icon('heroicon-o-map-pin')
                ->color('info'),

            Stat::make('Networks', number_format($totalNetworks))
                ->description('Mạng lưới quảng cáo')
                ->descriptionIcon('heroicon-s-squares-2x2')
                ->icon('heroicon-o-squares-2x2')
                ->color('warning'),
        ];
    }
}
