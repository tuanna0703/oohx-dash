<?php

namespace App\Filament\Resources\VenueCategoryResource\Pages;

use App\Filament\Resources\VenueCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVenueCategories extends ListRecords
{
    protected static string $resource = VenueCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
