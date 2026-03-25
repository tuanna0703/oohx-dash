<?php

namespace App\Filament\Publisher\Resources\NetworkResource\Pages;

use App\Filament\Publisher\Resources\NetworkResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNetwork extends ListRecords
{
    protected static string $resource = NetworkResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }


}
