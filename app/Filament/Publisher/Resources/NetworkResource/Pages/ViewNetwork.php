<?php

namespace App\Filament\Publisher\Resources\NetworkResource\Pages;

use App\Filament\Publisher\Resources\NetworkResource;
use App\Models\Screen;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewNetwork extends ViewRecord
{
    protected static string $resource = NetworkResource::class;
    protected static string $view     = 'filament.publisher.pages.network-detail';

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()->label('Edit Network'),
        ];
    }

    // Truyền dữ liệu sang blade view
    protected function getViewData(): array
    {
        $network = $this->record;

        // Lấy screens thông qua screen_inventory
        $screens = Screen::whereHas('inventory', fn($q) => $q->where('network_id', $network->id))
            ->with(['site', 'inventory', 'spec'])
            ->get();

        // Tính trung tâm bản đồ từ các screen có tọa độ
        $coords = $screens->filter(fn($s) => $s->lat && $s->lon);
        $mapCenter = $coords->isNotEmpty()
            ? [
                'lat' => $coords->avg('lat'),
                'lon' => $coords->avg('lon'),
            ]
            : null;

        // Nếu không có tọa độ screen, lấy từ site
        if (! $mapCenter) {
            $siteCoords = $screens->filter(fn($s) => $s->site?->lat && $s->site?->lon);
            if ($siteCoords->isNotEmpty()) {
                $mapCenter = [
                    'lat' => $siteCoords->avg(fn($s) => $s->site->lat),
                    'lon' => $siteCoords->avg(fn($s) => $s->site->lon),
                ];
            }
        }

        return [
            'network'   => $network,
            'screens'   => $screens,
            'mapCenter' => $mapCenter,
            'mapMarkers' => $screens->map(fn($s) => [
                'lat'  => $s->lat ?? $s->site?->lat,
                'lon'  => $s->lon ?? $s->site?->lon,
                'name' => $s->name,
                'uuid' => $s->uuid,
            ])->filter(fn($m) => $m['lat'] && $m['lon'])->values(),
        ];
    }
}
