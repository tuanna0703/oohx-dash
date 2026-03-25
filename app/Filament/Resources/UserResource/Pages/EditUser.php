<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\OwnerUser;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $this->syncRoleAndOwner($this->record);
    }

    private function syncRoleAndOwner(\App\Models\User $user): void
    {
        $data = $this->data;

        // 1. Sync Spatie role
        $role = $data['roles'] ?? null;
        if ($role) {
            $user->syncRoles([$role]);
        }

        // 2. Sync OwnerUser
        $ownerId   = $data['owner_id'] ?? null;
        $ownerRole = $data['owner_role'] ?? 'read_only';

        if ($ownerId) {
            OwnerUser::updateOrCreate(
                ['owner_id' => $ownerId, 'user_id' => $user->id],
                ['role' => $ownerRole]
            );
            $user->update(['current_owner_id' => $ownerId]);
        }
    }
}
