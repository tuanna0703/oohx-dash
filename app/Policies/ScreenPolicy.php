<?php

namespace App\Policies;

use App\Models\Screen;
use App\Models\User;

class ScreenPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->current_owner_id !== null;
    }

    public function view(User $user, Screen $screen): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $screen->owner_id === $user->current_owner_id;
    }

    public function create(User $user): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $user->current_owner_id !== null
            && $this->hasManagePermission($user);
    }

    public function update(User $user, Screen $screen): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $screen->owner_id === $user->current_owner_id
            && $this->hasManagePermission($user);
    }

    public function delete(User $user, Screen $screen): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $screen->owner_id === $user->current_owner_id
            && $this->hasManagePermission($user);
    }

    public function deleteAny(User $user): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return $this->hasManagePermission($user);
    }

    private function hasManagePermission(User $user): bool
    {
        return \App\Services\TenantPermission::for($user)->can('manage_inventory');
    }
}
