<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\OwnerUser;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        $this->syncRoleAndOwner($this->record);
    }

    private function syncRoleAndOwner(\App\Models\User $user): void
    {
        $data = $this->data;

        // 1. Gán Spatie role
        $role = $data['roles'] ?? 'publisher';
        $user->syncRoles([$role]);

        // 2. Gán vào Owner nếu có chọn
        $ownerId   = $data['owner_id'] ?? null;
        $ownerRole = $data['owner_role'] ?? 'read_only';

        if ($ownerId) {
            OwnerUser::updateOrCreate(
                ['owner_id' => $ownerId, 'user_id' => $user->id],
                ['role' => $ownerRole]
            );

            // Set current_owner_id
            $user->update(['current_owner_id' => $ownerId]);
        }
    }
}
