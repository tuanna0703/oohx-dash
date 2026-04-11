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
        $this->syncRoleAndOwners($this->record);
    }

    private function syncRoleAndOwners(\App\Models\User $user): void
    {
        $data = $this->data;

        // 1. Sync Spatie role
        $role = $data['roles'] ?? 'publisher';
        $user->syncRoles([$role]);

        // 2. Sync Owner memberships
        $memberships = $data['owner_memberships'] ?? [];
        $firstOwnerId = null;

        foreach ($memberships as $m) {
            if (empty($m['owner_id'])) continue;

            OwnerUser::updateOrCreate(
                ['owner_id' => $m['owner_id'], 'user_id' => $user->id],
                ['role' => $m['role'] ?? 'read_only']
            );

            $firstOwnerId ??= $m['owner_id'];
        }

        // Set current_owner_id to first owner if not set
        if ($firstOwnerId && ! $user->current_owner_id) {
            $user->update(['current_owner_id' => $firstOwnerId]);
        }
    }
}
