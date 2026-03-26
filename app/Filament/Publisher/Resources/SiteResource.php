<?php

namespace App\Filament\Publisher\Resources;

use App\Filament\Publisher\Resources\SiteResource\Pages;
use App\Filament\Shared\Resources\BaseSiteResource;
use App\Services\TenantPermission;
use Illuminate\Database\Eloquent\Builder;

class SiteResource extends BaseSiteResource
{
    // ── Phân quyền theo tenant ────────────────────────────────────────────────

    public static function canViewAny(): bool  { return TenantPermission::check('view_inventory'); }
    public static function canView($r): bool   { return TenantPermission::check('view_inventory'); }
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

    // ── Pages ─────────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSite::route('/'),
            'create' => Pages\CreateSite::route('/create'),
            'edit'   => Pages\EditSite::route('/{record}/edit'),
            'view'   => Pages\ViewSite::route('/{record}'),
        ];
    }
}
