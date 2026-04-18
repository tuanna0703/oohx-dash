<?php

namespace App\Filament\Resources\PoiSnapshotResource\Pages;

use App\Filament\Resources\PoiSnapshotResource;
use App\Models\PoiSnapshot;
use App\Services\PoiContextEnricher;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPoiSnapshot extends ViewRecord
{
    protected static string $resource = PoiSnapshotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refresh')
                ->label('Refresh từ OSM')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->modalDescription('Re-fetch POI từ Overpass cho location này.')
                ->action(function (PoiContextEnricher $enricher) {
                    $r = $this->record;
                    try {
                        $pois = $enricher->fetchPoisOnly(
                            (float) $r->lat_key,
                            (float) $r->lon_key,
                            $r->radius,
                        );
                        Notification::make()
                            ->title('Refreshed')
                            ->body(count($pois) . ' POIs cập nhật')
                            ->success()
                            ->send();
                        $this->refreshFormData(['pois', 'poi_count', 'features', 'fetched_at', 'expires_at']);
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Refresh failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('open_osm')
                ->label('Mở trên OpenStreetMap')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn () => sprintf(
                    'https://www.openstreetmap.org/?mlat=%s&mlon=%s#map=17/%s/%s',
                    $this->record->lat_key, $this->record->lon_key,
                    $this->record->lat_key, $this->record->lon_key,
                ))
                ->openUrlInNewTab(),

            Actions\DeleteAction::make(),
        ];
    }
}
