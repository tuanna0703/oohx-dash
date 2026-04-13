<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected function afterCreate(): void
    {
        $screenIds = $this->form->getState()['screenIds'] ?? [];
        if (! empty($screenIds)) {
            $this->record->screens()->sync($screenIds);
            $this->record->update(['total_units' => count($screenIds)]);
        }
    }
}
