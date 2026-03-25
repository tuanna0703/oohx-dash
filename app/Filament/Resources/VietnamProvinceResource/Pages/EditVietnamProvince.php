<?php

namespace App\Filament\Resources\VietnamProvinceResource\Pages;

use App\Filament\Resources\VietnamProvinceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVietnamProvince extends EditRecord
{
    protected static string $resource = VietnamProvinceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
