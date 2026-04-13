<?php

namespace App\Filament\Publisher\Resources\ProductResource\Pages;

use App\Filament\Publisher\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['owner_id'] = auth()->user()->current_owner_id;
        return $data;
    }

    protected function afterCreate(): void
    {
        $screenIds = $this->form->getState()['screenIds'] ?? [];
        if (! empty($screenIds)) {
            $this->record->screens()->sync($screenIds);
            $this->record->update(['total_units' => count($screenIds)]);
        }
    }
}
