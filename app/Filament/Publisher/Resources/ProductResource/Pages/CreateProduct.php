<?php

namespace App\Filament\Publisher\Resources\ProductResource\Pages;

use App\Filament\Concerns\ResolvesSlug;
use App\Filament\Publisher\Resources\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    use ResolvesSlug;

    protected static string $resource = ProductResource::class;

    protected array $pendingScreenIds = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['owner_id'] = auth()->user()->current_owner_id;
        $data = $this->resolveSlug($data);

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
