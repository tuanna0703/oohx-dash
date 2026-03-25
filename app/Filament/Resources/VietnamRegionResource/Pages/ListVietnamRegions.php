<?php

namespace App\Filament\Resources\VietnamRegionResource\Pages;

use App\Filament\Resources\VietnamRegionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVietnamRegions extends ListRecords
{
    protected static string $resource = VietnamRegionResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
