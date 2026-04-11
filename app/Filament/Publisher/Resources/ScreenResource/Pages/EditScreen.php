<?php

namespace App\Filament\Publisher\Resources\ScreenResource\Pages;

use App\Filament\Concerns\SavesScreenRelationships;
use App\Filament\Publisher\Resources\ScreenResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditScreen extends EditRecord
{
    use SavesScreenRelationships;

    protected static string $resource = ScreenResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    // Load dữ liệu spec và inventory vào form khi mở Edit
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $screen = $this->record;
        $screen->load(['spec', 'inventory', 'site']);

        // Flatten spec.* vào form data (nested array — Filament 3)
        if ($screen->spec) {
            foreach ($screen->spec->toArray() as $key => $val) {
                $data['spec'][$key] = $val;
            }
        }

        // Flatten inventory.* vào form data (nested array — Filament 3)
        if ($screen->inventory) {
            foreach ($screen->inventory->toArray() as $key => $val) {
                $data['inventory'][$key] = $val;
            }
        }

        // Flatten site lat/lon into virtual fields
        if ($screen->site) {
            $data['_site_lat'] = $screen->site->lat;
            $data['_site_lon'] = $screen->site->lon;
        }

        return $data;
    }
}
