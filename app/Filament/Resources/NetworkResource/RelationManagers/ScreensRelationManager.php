<?php

namespace App\Filament\Resources\NetworkResource\RelationManagers;

use App\Models\Screen;
use App\Models\Site;
use App\Models\VietnamCommune;
use App\Models\VietnamProvince;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ScreensRelationManager extends RelationManager
{
    protected static string $relationship = 'inventoryScreens';

    protected static ?string $title = 'Màn hình';

    public function table(Table $table): Table
    {
        return $table
            ->heading(null)
            ->columns([
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
                SelectFilter::make('province_id')
                    ->label('Tỉnh/Thành')
                    ->options(fn() => VietnamProvince::toSelectOptions())
                    ->query(fn(Builder $query, array $data) =>
                        $data['value']
                            ? $query->whereHas('site', fn($q) => $q->where('province_id', $data['value']))
                            : $query
                    )
                    ->searchable(),

                SelectFilter::make('commune_id')
                    ->label('Phường/Xã')
                    ->options(fn() => VietnamCommune::orderBy('full_name')->pluck('full_name', 'id')->toArray())
                    ->query(fn(Builder $query, array $data) =>
                        $data['value']
                            ? $query->whereHas('site', fn($q) => $q->where('commune_id', $data['value']))
                            : $query
                    )
                    ->searchable(),

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
            ->headerActions([])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('view')
                        ->label('View')
                        ->icon('heroicon-o-eye')
                        ->url(fn(Screen $record) =>
                            \App\Filament\Resources\ScreenResource::getUrl('view', ['record' => $record])
                        ),
                    Tables\Actions\Action::make('edit')
                        ->label('Edit')
                        ->icon('heroicon-o-pencil')
                        ->url(fn(Screen $record) =>
                            \App\Filament\Resources\ScreenResource::getUrl('edit', ['record' => $record])
                        ),
                ]),
            ])
            ->emptyStateHeading('Chưa có màn hình nào trong network này')
            ->emptyStateIcon('heroicon-o-device-tablet');
    }
}
