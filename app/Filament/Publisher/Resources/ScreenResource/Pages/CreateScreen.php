<?php

namespace App\Filament\Publisher\Resources\ScreenResource\Pages;

use App\Filament\Concerns\SavesScreenRelationships;
use App\Filament\Publisher\Resources\ScreenResource;
use Filament\Resources\Pages\CreateRecord;

class CreateScreen extends CreateRecord
{
    use SavesScreenRelationships;

    protected static string $resource = ScreenResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
