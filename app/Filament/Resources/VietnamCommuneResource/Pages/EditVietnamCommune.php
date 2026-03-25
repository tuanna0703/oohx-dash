<?php

namespace App\Filament\Resources\VietnamCommuneResource\Pages;

use App\Filament\Resources\VietnamCommuneResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVietnamCommune extends EditRecord
{
    protected static string $resource = VietnamCommuneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
