<?php

namespace App\Filament\Resources\NetworkResource\RelationManagers;

use App\Models\Screen;
use App\Models\Site;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ScreensRelationManager extends RelationManager
{
    protected static string $relationship = 'inventoryScreens';

    protected static ?string $title = 'Màn hình';

    public function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn(Screen $record) =>
                \App\Filament\Resources\ScreenResource::getUrl('view', ['record' => $record])
            )
            ->columns([
                Tables\Columns\TextColumn::make('external_id')
                    ->label('Screen ID')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Tên màn hình')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('site.name')
                    ->label('Site')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('site.province.name')
                    ->label('Tỉnh / Thành')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('site.commune.full_name')
                    ->label('Phường / Xã')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('inventory.floor_cpm')
                    ->label('Floor CPM')
                    ->formatStateUsing(fn($state, Screen $record) => $state
                        ? number_format((float) $state, 0, '.', ',')
                          . ' ' . ($record->inventory?->floor_cpm_currency ?? 'VND')
                        : '—')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('active')
                    ->label('Enabled')
                    ->boolean(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Device')
                    ->colors([
                        'success' => 'online',
                        'danger'  => 'offline',
                        'warning' => 'maintenance',
                    ])
                    ->formatStateUsing(fn($state) => ucfirst($state ?? 'unknown')),
            ])
            ->filters([
                SelectFilter::make('site_id')
                    ->label('Site')
                    ->options(fn() => Site::orderBy('name')->pluck('name', 'id')->toArray())
                    ->searchable(),

                TernaryFilter::make('active')
                    ->label('Enabled'),

                SelectFilter::make('status')
                    ->label('Device status')
                    ->options([
                        'online'      => 'Online',
                        'offline'     => 'Offline',
                        'maintenance' => 'Maintenance',
                    ]),
            ])
            ->filtersFormColumns(3)
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn(Screen $record) =>
                        \App\Filament\Resources\ScreenResource::getUrl('view', ['record' => $record])
                    ),
            ])
            ->emptyStateHeading('Chưa có màn hình nào trong network này')
            ->emptyStateIcon('heroicon-o-device-tablet');
    }
}
