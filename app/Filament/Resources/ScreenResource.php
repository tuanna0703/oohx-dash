<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ScreenResource\Pages;
use App\Filament\Shared\Resources\BaseScreenResource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;

class ScreenResource extends BaseScreenResource
{
    // Admin xem tất cả owners, không scope theo tenant
    protected static ?int $navigationSort = 2;

    // ── Phân quyền: admin luôn có toàn quyền ─────────────────────────────────

    public static function canViewAny(): bool  { return true; }
    public static function canCreate(): bool   { return true; }
    public static function canEdit($r): bool   { return true; }
    public static function canDelete($r): bool { return true; }

    // canPricing() không override → luôn true (base default)
    // getEloquentQuery() không override → admin thấy toàn bộ screens

    // Dropdowns siteFormOptions/networkFormOptions không override
    // → dùng default của base: tất cả sites/networks (không scope tenant)

    // ── Owner column trong table ──────────────────────────────────────────────

    protected static function additionalTableColumns(): array
    {
        return [];
    }

    // ── Owner filter trong table ──────────────────────────────────────────────

    protected static function additionalFilters(): array
    {
        return [
            SelectFilter::make('owner')
                ->label('Media Owner')
                ->relationship('owner', 'name')
                ->searchable(),
        ];
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
