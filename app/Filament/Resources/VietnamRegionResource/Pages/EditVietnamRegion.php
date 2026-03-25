<?php

namespace App\Filament\Resources\VietnamRegionResource\Pages;

use App\Filament\Resources\VietnamRegionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVietnamRegion extends EditRecord
{
    protected static string $resource = VietnamRegionResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
