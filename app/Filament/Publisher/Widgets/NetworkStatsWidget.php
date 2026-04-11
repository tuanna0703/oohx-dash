<?php

namespace App\Filament\Publisher\Widgets;

use App\Models\Network;
use App\Models\ScreenInventory;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NetworkStatsWidget extends BaseWidget
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

        // ── Networks ─────────────────────────────────────────────────────────
        $total  = Network::where('owner_id', $ownerId)->count();
        $active = Network::where('owner_id', $ownerId)->where('status', 'active')->count();
        $paused = $total - $active;

        $networkIds = Network::where('owner_id', $ownerId)->pluck('id');

        // Networks có ít nhất 1 screen (qua screen_inventory)
        $networksWithScreens = $networkIds->isNotEmpty()
            ? ScreenInventory::whereIn('network_id', $networkIds)
                ->distinct('network_id')
                ->count('network_id')
            : 0;

        // Tổng screens đã được gán vào network
        $totalScreensInNetworks = $networkIds->isNotEmpty()
            ? ScreenInventory::whereIn('network_id', $networkIds)->count()
            : 0;

        // Trung bình floor CPM (tất cả screens có floor_cpm trong các networks này)
        $avgFloorCpm = $networkIds->isNotEmpty()
            ? ScreenInventory::whereIn('network_id', $networkIds)
                ->whereNotNull('floor_cpm')
                ->where('floor_cpm', '>', 0)
                ->avg('floor_cpm')
            : null;

        // ─────────────────────────────────────────────────────────────────────

        return [
            Stat::make('Tổng Networks', number_format($total))
                ->description(
                    $active . ' active'
                    . ($paused > 0 ? ' · ' . $paused . ' paused' : '')
                )
                ->descriptionIcon('heroicon-s-squares-2x2')
                ->icon('heroicon-o-squares-2x2')
                ->color('primary'),

            Stat::make('Networks có màn hình', number_format($networksWithScreens))
                ->description($totalScreensInNetworks . ' màn hình đã gán network')
                ->descriptionIcon('heroicon-s-computer-desktop')
                ->icon('heroicon-o-computer-desktop')
                ->color('info'),

            Stat::make('Avg Floor CPM', $avgFloorCpm
                ? number_format((float) $avgFloorCpm, 0, '.', ',') . ' VND'
                : '—'
            )
                ->description('Trung bình floor CPM tất cả screens')
                ->descriptionIcon('heroicon-s-banknotes')
                ->icon('heroicon-o-banknotes')
                ->color('warning'),
        ];
    }
}
