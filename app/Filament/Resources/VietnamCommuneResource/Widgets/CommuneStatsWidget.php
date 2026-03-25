<?php

namespace App\Filament\Resources\VietnamCommuneResource\Widgets;

use App\Models\VietnamCommune;
use App\Models\VietnamProvince;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class CommuneStatsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $s = DB::table('vietnam_communes')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN type = 'phuong'   THEN 1 ELSE 0 END) as phuong,
                SUM(CASE WHEN type = 'xa'       THEN 1 ELSE 0 END) as xa,
                SUM(CASE WHEN type = 'thi_tran' THEN 1 ELSE 0 END) as thi_tran,
                COUNT(DISTINCT province_id) as provinces_covered
            ")
            ->first();

        $totalProvinces = VietnamProvince::count();

        $topProvince = DB::table('vietnam_communes as c')
            ->join('vietnam_provinces as p', 'p.id', '=', 'c.province_id')
            ->selectRaw('p.name, COUNT(c.id) as cnt')
            ->groupBy('p.id', 'p.name')
            ->orderByDesc('cnt')
            ->first();

        $sitesLinked = DB::table('sites')
            ->whereNotNull('commune_id')
            ->whereNull('deleted_at')
            ->count();

        $covered   = (int) ($s->provinces_covered ?? 0);
        $remaining = max(0, $totalProvinces - $covered);

        return [
            Stat::make('Tổng phường / xã / thị trấn', number_format((int) ($s->total ?? 0)))
                ->description('Sau cải cách hành chính 01/07/2025')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color((int) ($s->total ?? 0) > 0 ? 'success' : 'gray'),

            Stat::make('Phường', number_format((int) ($s->phuong ?? 0)))
                ->description(
                    number_format((int) ($s->xa ?? 0)) . ' xã  •  '
                    . number_format((int) ($s->thi_tran ?? 0)) . ' thị trấn'
                )
                ->descriptionIcon('heroicon-m-home-modern')
                ->color('primary'),

            Stat::make('Tỉnh đã có dữ liệu', $covered . ' / ' . $totalProvinces)
                ->description($remaining > 0 ? "{$remaining} tỉnh chưa có phường/xã" : 'Đầy đủ tất cả tỉnh/thành')
                ->descriptionIcon('heroicon-m-map')
                ->color($remaining === 0 && $totalProvinces > 0 ? 'success' : 'warning'),

            Stat::make('Sites đã gắn phường/xã', number_format($sitesLinked))
                ->description(
                    $topProvince
                        ? 'Nhiều nhất: ' . $topProvince->name . ' (' . number_format($topProvince->cnt) . ')'
                        : 'Chưa có site nào liên kết'
                )
                ->descriptionIcon('heroicon-m-map-pin')
                ->color($sitesLinked > 0 ? 'info' : 'gray'),
        ];
    }
}
