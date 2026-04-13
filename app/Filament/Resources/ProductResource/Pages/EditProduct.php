<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected array $pendingScreenIds = [];

    protected function getHeaderActions(): array
    {
        return [Actions\ViewAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
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
