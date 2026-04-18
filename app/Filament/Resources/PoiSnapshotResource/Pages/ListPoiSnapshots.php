<?php

namespace App\Filament\Resources\PoiSnapshotResource\Pages;

use App\Filament\Resources\PoiSnapshotResource;
use Filament\Resources\Pages\ListRecords;

class ListPoiSnapshots extends ListRecords
{
    protected static string $resource = PoiSnapshotResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
