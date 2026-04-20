<?php

namespace App\Filament\Resources\OohxRecomputeJobResource\Pages;

use App\Filament\Resources\OohxRecomputeJobResource;
use Filament\Resources\Pages\ListRecords;

class ListRecomputeJobs extends ListRecords
{
    protected static string $resource = OohxRecomputeJobResource::class;

    protected function getHeaderActions(): array
    {
        return []; // enqueue actions ở table headerActions
    }
}
