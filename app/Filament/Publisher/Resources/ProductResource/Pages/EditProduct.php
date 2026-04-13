<?php

namespace App\Filament\Publisher\Resources\ProductResource\Pages;

use App\Filament\Publisher\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected array $pendingScreenIds = [];

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Lưu screenIds trước khi Filament loại bỏ (dehydrated: false)
        $this->pendingScreenIds = $data['screenIds'] ?? [];
        unset($data['screenIds']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->screens()->sync($this->pendingScreenIds);
        $this->record->update(['total_units' => count($this->pendingScreenIds)]);
    }
}
