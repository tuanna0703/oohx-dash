<?php

namespace App\Filament\Publisher\Resources\ProductResource\Pages;

use App\Filament\Publisher\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function afterSave(): void
    {
        $screenIds = $this->form->getState()['screenIds'] ?? [];
        $this->record->screens()->sync($screenIds);
        $this->record->update(['total_units' => count($screenIds)]);
    }
}
