<?php

namespace App\Filament\Publisher\Resources\NetworkResource\Pages;

use App\Filament\Publisher\Resources\NetworkResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EditNetwork extends EditRecord
{
    protected static string $resource = NetworkResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Verify the record belongs to the current tenant
        $ownerId = auth()->user()?->current_owner_id;
        if ($ownerId && $this->record->owner_id !== $ownerId) {
            throw new NotFoundHttpException();
        }
    }

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
