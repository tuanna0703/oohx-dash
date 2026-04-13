<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected array $pendingScreenIds = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingScreenIds = $data['screenIds'] ?? [];
        unset($data['screenIds']);

        return $data;
    }

    protected function afterCreate(): void
    {
        if (! empty($this->pendingScreenIds)) {
            $this->record->screens()->sync($this->pendingScreenIds);
            $this->record->update(['total_units' => count($this->pendingScreenIds)]);
        }
    }
}
