<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\OohxHealth;
use App\Services\Oohx\HealthDigestService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Dashboard widget — 1 stat row: DE health summary.
 *
 * Phase 3.A Part 2 polish: cho sales/PM có glance view state DE mà không
 * phải navigate vào Health Monitor page.
 *
 * Click stat → jump vào /admin/oohx-health.
 *
 * canView() — chỉ hiện cho super_admin (khớp canAccess của OohxHealth page).
 */
class OohxHealthSummaryWidget extends BaseWidget
{
    protected static ?int $sort = 10;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        $u = auth()->user();
        return $u && (method_exists($u, 'hasRole') ? $u->hasRole('super_admin') : false);
    }

    protected function getStats(): array
    {
        $service = app(HealthDigestService::class);
        $result  = $service->latest();
        $badge   = $service->overallBadge($result);

        $ageLabel = '—';
        $descColor = 'gray';
        if ($result) {
            $age = $result['age_minutes'];
            $ageLabel = $age < 60
                ? "{$age} min ago"
                : round($age / 60, 1) . 'h ago';

            if ($age > 720)      $descColor = 'danger';
            elseif ($age > 120)  $descColor = 'warning';
            else                 $descColor = 'success';
        }

        $summary = $result['digest']['summary'] ?? ['ok' => 0, 'warn' => 0, 'critical' => 0];

        return [
            Stat::make('DE Health', $badge['label'])
                ->description("Digest {$ageLabel}")
                ->descriptionColor($descColor)
                ->descriptionIcon('heroicon-m-clock')
                ->color($badge['color'])
                ->icon($badge['icon'])
                ->url(OohxHealth::getUrl()),

            Stat::make('OK checks', (string) ($summary['ok'] ?? 0))
                ->color('success')
                ->icon('heroicon-m-check-circle')
                ->url(OohxHealth::getUrl()),

            Stat::make('Warnings', (string) ($summary['warn'] ?? 0))
                ->color(($summary['warn'] ?? 0) > 0 ? 'warning' : 'gray')
                ->icon('heroicon-m-exclamation-triangle')
                ->url(OohxHealth::getUrl()),

            Stat::make('Critical', (string) ($summary['critical'] ?? 0))
                ->color(($summary['critical'] ?? 0) > 0 ? 'danger' : 'gray')
                ->icon('heroicon-m-x-circle')
                ->url(OohxHealth::getUrl()),
        ];
    }
}
