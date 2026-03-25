<?php

namespace App\Filament\Publisher\Resources\NetworkResource\Pages;

use App\Filament\Publisher\Resources\NetworkResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNetwork extends EditRecord
{
    protected static string $resource = NetworkResource::class;


    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['owner_id'] = auth()->user()->current_owner_id;
        return $data;
    }

}
