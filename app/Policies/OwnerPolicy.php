<?php
namespace App\Policies;

use App\Models\Owner;
use App\Models\User;

class OwnerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->owners()->exists();
    }

    public function view(User $user, Owner $owner): bool
    {
        return $user->hasRole('super_admin')
            || $user->owners()->where('owners.id', $owner->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super_admin');
    }

    public function update(User $user, Owner $owner): bool
    {
        if ($user->hasRole('super_admin')) return true;
        $role = $user->getRoleInOwner($owner->id);
        return in_array($role, ['admin', 'manager']);
    }

    public function delete(User $user, Owner $owner): bool
    {
        return $user->hasRole('super_admin');
    }
}
