<?php

namespace App\Filament\Publisher\Resources\NetworkResource\Pages;

use App\Filament\Publisher\Resources\NetworkResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateNetwork extends CreateRecord
{
    protected static string $resource = NetworkResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['owner_id'] = auth()->user()->current_owner_id;

        if (empty($data['code']) && ! empty($data['name'])) {
            $data['code'] = Str::slug($data['name']);
        }

        return $data;
    }
}
