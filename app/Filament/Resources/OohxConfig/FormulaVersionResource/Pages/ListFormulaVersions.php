<?php

namespace App\Filament\Resources\OohxConfig\FormulaVersionResource\Pages;

use App\Filament\Resources\OohxConfig\FormulaVersionResource;
use Filament\Resources\Pages\ListRecords;

class ListFormulaVersions extends ListRecords
{
    protected static string $resource = FormulaVersionResource::class;

    protected function getHeaderActions(): array
    {
        return []; // publish action đã ở table headerActions
    }
}
