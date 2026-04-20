<?php

namespace App\Filament\Resources\OohxCollectorRunResource\Pages;

use App\Filament\Resources\OohxCollectorRunResource;
use Filament\Resources\Pages\ListRecords;

class ListCollectorRuns extends ListRecords
{
    protected static string $resource = OohxCollectorRunResource::class;

    protected function getHeaderActions(): array
    {
        return []; // trigger actions ở Overview Page
    }
}
