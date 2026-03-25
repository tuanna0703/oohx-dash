<?php

namespace App\Filament\Publisher\Resources\SiteResource\Pages;

use App\Filament\Publisher\Resources\SiteResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSite extends CreateRecord
{
    protected static string $resource = SiteResource::class;


    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['owner_id'] = auth()->user()->current_owner_id;
        return $data;
    }

}
