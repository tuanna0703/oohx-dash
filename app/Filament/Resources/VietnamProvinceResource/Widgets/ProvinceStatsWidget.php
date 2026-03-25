<?php

namespace App\Filament\Resources\VietnamProvinceResource\Widgets;

use App\Models\VietnamCommune;
use App\Models\VietnamProvince;
use App\Models\VietnamRegion;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ProvinceStatsWidget extends BaseWidget
{
    protected static ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $s = DB::table('vietnam_provinces')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN type = 'thanh_pho' THEN 1 ELSE 0 END) as city,
                SUM(CASE WHEN type = 'tinh'      THEN 1 ELSE 0 END) as tinh
            ")
            ->first();

        $regions       = VietnamRegion::count();
        $communeTotal  = VietnamCommune::count();
        $communeStats  = DB::table('vietnam_communes')
            ->selectRaw("
                SUM(CASE WHEN type = 'phuong'   THEN 1 ELSE 0 END) as phuong,
                SUM(CASE WHEN type = 'xa'       THEN 1 ELSE 0 END) as xa,
                SUM(CASE WHEN type = 'thi_tran' THEN 1 ELSE 0 END) as thi_tran
            ")
            ->first();

        $sitesLinked = DB::table('sites')
            ->whereNotNull('province_id')
            ->whereNull('deleted_at')
            ->count();

        return [
            Stat::make('Tổng tỉnh / thành phố', number_format((int) $s->total))
                ->description(number_format((int) $s->city) . ' thành phố TT TW  •  ' . number_format((int) $s->tinh) . ' tỉnh')
                ->descriptionIcon('heroicon-m-map')
                ->color('primary'),

            Stat::make('Vùng kinh tế', number_format($regions))
                ->description('8 vùng kinh tế – xã hội')
                ->descriptionIcon('heroicon-m-globe-alt')
                ->color('info'),

            Stat::make('Phường / Xã / Thị trấn', number_format($communeTotal))
                ->description(
                    ($communeStats
                        ? number_format((int) $communeStats->phuong)   . ' phường  •  '
                        . number_format((int) $communeStats->xa)        . ' xã  •  '
                        . number_format((int) $communeStats->thi_tran)  . ' thị trấn'
                        : 'Chưa có dữ liệu phường/xã')
                )
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color($communeTotal > 0 ? 'success' : 'gray'),

            Stat::make('Sites đã gắn tỉnh', number_format($sitesLinked))
                ->description('Sites có liên kết tỉnh/thành phố')
                ->descriptionIcon('heroicon-m-map-pin')
                ->color($sitesLinked > 0 ? 'warning' : 'gray'),
        ];
    }
}
