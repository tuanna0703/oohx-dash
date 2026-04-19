<?php

namespace App\Filament\Resources\OohxEstimateResource\Pages;

use App\Filament\Resources\OohxEstimateResource;
use Filament\Resources\Pages\ViewRecord;

class ViewOohxEstimate extends ViewRecord
{
    protected static string $resource = OohxEstimateResource::class;

    protected function getHeaderActions(): array
    {
        return []; // read-only — no edit/delete
    }
}
