<?php

namespace App\Policies;

use App\Models\OwnerUser;
use App\Models\User;

class OwnerUserPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('super_admin')) return true;

        return $this->actorRole($user, $user->current_owner_id) === 'owner';
    }

    public function view(User $user, OwnerUser $target): bool
    {
        if ($user->hasRole('super_admin')) return true;

        return $this->actorRole($user, $target->owner_id) !== null;
    }

    /**
     * Tạo OwnerUser mới (invite/create).
     * Chỉ super_admin hoặc role 'owner' trong owner đang xem mới được phép.
     */
    public function create(User $user): bool
    {
        if ($user->hasRole('super_admin')) return true;

        return $this->actorRole($user, $user->current_owner_id) === 'owner';
    }

    /**
     * Update role của một thành viên.
     * - Không tự update chính mình (tránh self-demote/promote ngầm).
     * - Không update record có role 'owner' (chỉ super_admin).
     */
    public function update(User $user, OwnerUser $target): bool
    {
        if ($user->hasRole('super_admin')) return true;

        $actorRole = $this->actorRole($user, $target->owner_id);
        if ($actorRole !== 'owner') return false;
        if ($target->user_id === $user->id) return false;
        if ($target->role === 'owner') return false;

        return true;
    }

    /**
     * Xoá thành viên khỏi owner (chuyển role).
     */
    public function delete(User $user, OwnerUser $target): bool
    {
        if ($user->hasRole('super_admin')) return true;

        $actorRole = $this->actorRole($user, $target->owner_id);
        if ($actorRole !== 'owner') return false;
        if ($target->user_id === $user->id) return false;
        if ($target->role === 'owner') return false;

        return true;
    }

    /** Lấy role của actor trong owner cụ thể, null nếu không phải member. */
    private function actorRole(User $user, ?string $ownerId): ?string
    {
        if (! $ownerId) return null;

        return OwnerUser::where('owner_id', $ownerId)
            ->where('user_id', $user->id)
            ->value('role');
    }
}
