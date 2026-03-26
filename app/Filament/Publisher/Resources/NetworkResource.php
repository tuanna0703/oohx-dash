<?php

namespace App\Filament\Publisher\Resources;

use App\Filament\Publisher\Resources\NetworkResource\Pages;
use App\Filament\Shared\Resources\BaseNetworkResource;
use App\Services\TenantPermission;
use Illuminate\Database\Eloquent\Builder;

class NetworkResource extends BaseNetworkResource
{
    // ── Phân quyền theo tenant ────────────────────────────────────────────────

    public static function canViewAny(): bool  { return TenantPermission::check('view_inventory'); }
    public static function canCreate(): bool   { return TenantPermission::check('manage_inventory'); }
    public static function canEdit($r): bool   { return TenantPermission::check('manage_inventory'); }
    public static function canDelete($r): bool { return TenantPermission::check('manage_inventory'); }

    // ── Multi-tenant scoping ──────────────────────────────────────────────────

    public static function getEloquentQuery(): Builder
    {
        $ownerId = auth()->user()?->current_owner_id;

        return parent::getEloquentQuery()
            ->when($ownerId, fn($q) => $q->where('owner_id', $ownerId));
    }

    // ── Publisher có trang View riêng ─────────────────────────────────────────

    protected static function hasViewPage(): bool
    {
        return true;
    }

    // ── Pages ─────────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListNetwork::route('/'),
            'create' => Pages\CreateNetwork::route('/create'),
            'edit'   => Pages\EditNetwork::route('/{record}/edit'),
            'view'   => Pages\ViewNetwork::route('/{record}'),
        ];
    }
}
