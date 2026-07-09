<?php

namespace App\Policies;

use App\Models\OrganizationUser;
use App\Models\User;

class OrganizationUserPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('super_admin')) return true;

        return $this->actorRole($user, $user->current_organization_id) === 'admin';
    }

    public function view(User $user, OrganizationUser $target): bool
    {
        if ($user->hasRole('super_admin')) return true;

        return $this->actorRole($user, $target->organization_id) !== null;
    }

    /**
     * Tạo OrganizationUser mới (invite).
     * Chỉ super_admin hoặc role 'admin' của org đang xem mới được phép.
     */
    public function create(User $user): bool
    {
        if ($user->hasRole('super_admin')) return true;

        return $this->actorRole($user, $user->current_organization_id) === 'admin';
    }

    /**
     * Update role của thành viên.
     * - Không tự update chính mình.
     * - Không update record có role 'admin' (chỉ super_admin).
     */
    public function update(User $user, OrganizationUser $target): bool
    {
        if ($user->hasRole('super_admin')) return true;

        $actorRole = $this->actorRole($user, $target->organization_id);
        if ($actorRole !== 'admin') return false;
        if ($target->user_id === $user->id) return false;
        if ($target->role === 'admin') return false;

        return true;
    }

    public function delete(User $user, OrganizationUser $target): bool
    {
        if ($user->hasRole('super_admin')) return true;

        $actorRole = $this->actorRole($user, $target->organization_id);
        if ($actorRole !== 'admin') return false;
        if ($target->user_id === $user->id) return false;
        if ($target->role === 'admin') return false;

        return true;
    }

    private function actorRole(User $user, ?string $organizationId): ?string
    {
        if (! $organizationId) return null;

        return OrganizationUser::where('organization_id', $organizationId)
            ->where('user_id', $user->id)
            ->value('role');
    }
}
