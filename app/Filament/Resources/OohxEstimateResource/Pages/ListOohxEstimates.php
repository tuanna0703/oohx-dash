<?php

namespace App\Filament\Resources\OohxEstimateResource\Pages;

use App\Filament\Resources\OohxEstimateResource;
use Filament\Resources\Pages\ListRecords;

class ListOohxEstimates extends ListRecords
{
    protected static string $resource = OohxEstimateResource::class;

    protected function getHeaderActions(): array
    {
        return []; // no create — read-only data source
    }
}
