<?php

namespace App\Filament\Publisher\Resources\SiteResource\Pages;

use App\Filament\Publisher\Resources\SiteResource;
use App\Models\VietnamCommune;
use App\Models\VietnamProvince;
use App\Services\GeocodingService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSite extends EditRecord
{
    protected static string $resource = SiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('geocode')
                ->label('Geocode từ GPS')
                ->icon('heroicon-o-map-pin')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Cập nhật địa chỉ từ tọa độ GPS')
                ->modalDescription('Hệ thống sẽ tra cứu OpenStreetMap để điền tỉnh/thành, phường/xã và địa chỉ dựa theo lat/lon hiện tại.')
                ->visible(fn () => $this->record->lat && $this->record->lon)
                ->action(function () {
                    $site = $this->record;

                    if (! $site->lat || ! $site->lon) {
                        Notification::make()
                            ->title('Site chưa có tọa độ GPS')
                            ->warning()
                            ->send();
                        return;
                    }

                    $result = app(GeocodingService::class)
                        ->resolveLocation((float) $site->lat, (float) $site->lon);

                    if (empty($result)) {
                        Notification::make()
                            ->title('Không tìm được địa chỉ')
                            ->body('Nominatim không trả về kết quả cho tọa độ này.')
                            ->danger()
                            ->send();
                        return;
                    }

                    $site->update($result);
                    $this->refreshFormData(array_keys($result));

                    $provinceName = isset($result['province_id'])
                        ? VietnamProvince::find($result['province_id'])?->full_name
                        : '(không xác định)';

                    $communeName = isset($result['commune_id'])
                        ? VietnamCommune::find($result['commune_id'])?->full_name
                        : '(không xác định)';

                    Notification::make()
                        ->title('Geocode thành công')
                        ->body("Tỉnh: {$provinceName}\nPhường/Xã: {$communeName}")
                        ->success()
                        ->send();
                }),

            Actions\DeleteAction::make(),
        ];
    }
}
