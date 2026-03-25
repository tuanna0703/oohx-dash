<?php

namespace App\Filament\Publisher\Resources\NetworkResource\Pages;

use App\Filament\Publisher\Resources\NetworkResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateNetwork extends CreateRecord
{
    protected static string $resource = NetworkResource::class;



    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['owner_id'] = auth()->user()->current_owner_id;
        return $data;
    }
}
