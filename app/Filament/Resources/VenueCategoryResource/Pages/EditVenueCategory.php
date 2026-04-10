<?php

namespace App\Filament\Resources\VenueCategoryResource\Pages;

use App\Filament\Resources\VenueCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditVenueCategory extends EditRecord
{
    protected static string $resource = VenueCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
