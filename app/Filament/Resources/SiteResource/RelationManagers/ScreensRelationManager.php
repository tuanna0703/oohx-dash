<?php

namespace App\Filament\Resources\SiteResource\RelationManagers;

use App\Models\Screen;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ScreensRelationManager extends RelationManager
{
    protected static string $relationship = 'screens';

    protected static ?string $title = 'Screens';

    public function table(Table $table): Table
    {
        return $table
            ->heading(null)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Screen Name')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('external_id')
                    ->label('Screen ID')
                    ->searchable()
                    ->copyable()
                    ->fontFamily('mono'),

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
            ->filtersFormColumns(2)
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
            ->emptyStateHeading('No screens at this site yet')
            ->emptyStateIcon('heroicon-o-device-tablet');
    }
}
