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
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $this->syncRoleAndOwners($this->record);
    }

    private function syncRoleAndOwners(\App\Models\User $user): void
    {
        $data = $this->data;

        // 1. Sync Spatie role
        $role = $data['roles'] ?? null;
        if ($role) {
            $user->syncRoles([$role]);
        }

        // 2. Sync Owner memberships — replace all
        $memberships = $data['owner_memberships'] ?? [];
        $keepOwnerIds = [];

        foreach ($memberships as $m) {
            if (empty($m['owner_id'])) continue;

            OwnerUser::updateOrCreate(
                ['owner_id' => $m['owner_id'], 'user_id' => $user->id],
                ['role' => $m['role'] ?? 'read_only']
            );

            $keepOwnerIds[] = $m['owner_id'];
        }

        // Remove memberships not in the form
        $user->ownerUsers()
            ->whereNotIn('owner_id', $keepOwnerIds)
            ->delete();

        // Fix current_owner_id if it was removed
        if ($user->current_owner_id && ! in_array($user->current_owner_id, $keepOwnerIds)) {
            $user->update([
                'current_owner_id' => $keepOwnerIds[0] ?? null,
            ]);
        }
    }
}
