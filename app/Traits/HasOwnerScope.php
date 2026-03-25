<?php

namespace App\Traits;

use App\Models\ApiClient;
use Illuminate\Database\Eloquent\Builder;

trait HasOwnerScope
{
    protected static function bootHasOwnerScope(): void
    {
        static::addGlobalScope('owner_scope', function (Builder $query) {
            $user = auth()->user();
            if (! $user) return;
            // ApiClient token (OOHX) không bị giới hạn theo owner — thấy tất cả screens
            if ($user instanceof ApiClient) return;
            if ($user->hasRole('super_admin')) return;
            if ($user->current_owner_id) {
                $query->where($query->getModel()->getTable() . '.owner_id', $user->current_owner_id);
            }
        });
    }

    public function scopeForOwner(Builder $query, string $ownerId): Builder
    {
        return $query->withoutGlobalScope('owner_scope')
                     ->where('owner_id', $ownerId);
    }
}
