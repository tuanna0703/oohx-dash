<?php

namespace App\Filament\Shared\Widgets;

use App\Models\Screen;
use App\Models\Site;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Stats widget for the ViewSite detail page.
 * Receives $record (Site) from the page via getHeaderWidgetsData().
 */
class SiteDetailStats extends BaseWidget
{
    public Site $record;

    protected function getStats(): array
    {
        $siteId = $this->record->id;

        $total    = Screen::where('site_id', $siteId)->count();
        $active   = Screen::where('site_id', $siteId)->where('active', true)->count();
        $online   = Screen::where('site_id', $siteId)->where('status', 'online')->count();
        $offline  = Screen::where('site_id', $siteId)->where('status', 'offline')->count();

        $onlineColor  = $online  > 0 ? 'success' : 'gray';
        $offlineColor = $offline > 0 ? 'danger'  : 'gray';

        return [
            Stat::make('Total Screens', number_format($total))
                ->description($active . ' active · ' . ($total - $active) . ' inactive')
                ->descriptionIcon('heroicon-s-computer-desktop')
                ->icon('heroicon-o-computer-desktop')
                ->color('primary'),

            Stat::make('Active', number_format($active))
                ->description($total > 0 ? round($active / $total * 100) . '% of screens enabled' : 'No screens')
                ->descriptionIcon('heroicon-s-check-circle')
                ->icon('heroicon-o-check-circle')
                ->color($active > 0 ? 'success' : 'gray'),

            Stat::make('Online', number_format($online))
                ->description('Device connected')
                ->descriptionIcon('heroicon-s-signal')
                ->icon('heroicon-o-signal')
                ->color($onlineColor),

            Stat::make('Offline', number_format($offline))
                ->description('Device disconnected')
                ->descriptionIcon('heroicon-s-signal-slash')
                ->icon('heroicon-o-signal-slash')
                ->color($offlineColor),
        ];
    }
}
