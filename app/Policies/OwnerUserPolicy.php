<?php

namespace App\Policies;
use App\Models\OwnerUser;
use App\Models\User;

class OwnerUserPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('super_admin')) return true;

        return OwnerUser::where('owner_id', $user->current_owner_id)
            ->where('user_id', $user->id)
            ->exists();
    }

    public function create(User $user): bool
    {
        if ($user->hasRole('super_admin')) return true;

        return OwnerUser::where('owner_id', $user->current_owner_id)
            ->where('user_id', $user->id)
            ->where('role', 'owner')
            ->exists();
    }

    public function update(User $user, OwnerUser $target): bool
    {
        if ($user->hasRole('super_admin')) return true;

        $actor = OwnerUser::where('owner_id', $target->owner_id)
            ->where('user_id', $user->id)
            ->first();

        if (! $actor?->can('manage_users')) return false;
        if ($target->user_id === $user->id) return false;
        if ($target->role === 'owner') return false;

        return true;
    }

    public function delete(User $user, OwnerUser $target): bool
    {
        if ($user->hasRole('super_admin')) return true;

        $actor = OwnerUser::where('owner_id', $target->owner_id)
            ->where('user_id', $user->id)
            ->first();

        if (! $actor?->can('manage_users')) return false;
        if ($target->user_id === $user->id) return false;
        if ($target->role === 'owner') return false;

        return true;
    }
}
