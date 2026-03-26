<?php

namespace App\Filament\Publisher\Resources;

use App\Filament\Publisher\Resources\ScreenResource\Pages;
use App\Filament\Shared\Resources\BaseScreenResource;
use App\Models\Network;
use App\Models\Site;
use App\Services\TenantPermission;
use Illuminate\Database\Eloquent\Builder;

class ScreenResource extends BaseScreenResource
{
    // ── Phân quyền theo tenant ────────────────────────────────────────────────

    public static function canViewAny(): bool  { return TenantPermission::check('view_inventory'); }
    public static function canCreate(): bool   { return TenantPermission::check('manage_inventory'); }
    public static function canEdit($r): bool   { return TenantPermission::check('manage_inventory'); }
    public static function canDelete($r): bool { return TenantPermission::check('manage_inventory'); }

    protected static function canPricing(): bool
    {
        return TenantPermission::check('manage_pricing');
    }

    // ── Multi-tenant scoping ──────────────────────────────────────────────────

    public static function getEloquentQuery(): Builder
    {
        $ownerId = auth()->user()?->current_owner_id;

        return parent::getEloquentQuery()
            ->when($ownerId, fn($q) => $q->where('owner_id', $ownerId))
            ->with(['spec', 'inventory', 'inventory.network', 'site']);
    }

    // ── Dropdowns chỉ hiện dữ liệu của tenant ────────────────────────────────

    protected static function siteFormOptions(): array
    {
        return Site::where('owner_id', auth()->user()?->current_owner_id)
            ->orderBy('name')->pluck('name', 'id')->toArray();
    }

    protected static function networkFormOptions(): array
    {
        return Network::where('owner_id', auth()->user()?->current_owner_id)
            ->orderBy('name')->pluck('name', 'id')->toArray();
    }

    protected static function siteFilterOptions(): array
    {
        $ownerId = auth()->user()?->current_owner_id;
        return cache()->remember("filter_sites_{$ownerId}", 300,
            fn() => Site::where('owner_id', $ownerId)->orderBy('name')->pluck('name', 'id')->toArray()
        );
    }

    protected static function networkFilterOptions(): array
    {
        $ownerId = auth()->user()?->current_owner_id;
        return cache()->remember("filter_networks_{$ownerId}", 300,
            fn() => Network::where('owner_id', $ownerId)->orderBy('name')->pluck('name', 'id')->toArray()
        );
    }

    // ── Pages ─────────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListScreen::route('/'),
            'create' => Pages\CreateScreen::route('/create'),
            'edit'   => Pages\EditScreen::route('/{record}/edit'),
            'view'   => Pages\ViewScreen::route('/{record}'),
        ];
    }
}
