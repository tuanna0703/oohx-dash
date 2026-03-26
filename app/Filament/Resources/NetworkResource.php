<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NetworkResource\Pages;
use App\Filament\Shared\Resources\BaseNetworkResource;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;

class NetworkResource extends BaseNetworkResource
{
    // Admin xem tất cả owners, không scope theo tenant
    protected static ?int $navigationSort = 4;

    // ── Phân quyền: admin luôn có toàn quyền ─────────────────────────────────

    public static function canViewAny(): bool  { return true; }
    public static function canCreate(): bool   { return true; }
    public static function canEdit($r): bool   { return true; }
    public static function canDelete($r): bool { return true; }

    // getEloquentQuery() không override → admin thấy toàn bộ networks

    // ── Owner field trong form ────────────────────────────────────────────────

    protected static function ownerFormField(): Forms\Components\Component
    {
        return Forms\Components\Select::make('owner_id')
            ->label('Media Owner')
            ->relationship('owner', 'name')
            ->searchable()
            ->preload()
            ->required();
    }

    // ── Owner column trong table ──────────────────────────────────────────────

    protected static function additionalTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('owner.name')
                ->label('Owner')
                ->sortable()
                ->searchable()
                ->toggleable(),
        ];
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

    // ── Admin không có trang View riêng cho Network ───────────────────────────
    // hasViewPage() trả false theo default của base class

    // ── Pages ─────────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListNetworks::route('/'),
            'create' => Pages\CreateNetwork::route('/create'),
            'edit'   => Pages\EditNetwork::route('/{record}/edit'),
        ];
    }
}
