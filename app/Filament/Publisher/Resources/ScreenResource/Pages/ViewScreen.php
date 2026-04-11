<?php

namespace App\Filament\Publisher\Resources\ScreenResource\Pages;

use App\Filament\Publisher\Resources\ScreenResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewScreen extends ViewRecord
{
    protected static string $resource = ScreenResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\EditAction::make()];
    }
}
