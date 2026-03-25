<?php

namespace App\Filament\Publisher\Resources;

use App\Filament\Publisher\Resources\NetworkResource\Pages;
use App\Models\Network;
use App\Services\TenantPermission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class NetworkResource extends Resource
{
    protected static ?string $model           = Network::class;
    protected static ?string $navigationIcon  = null;
    protected static ?string $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Networks';
    protected static ?int    $navigationSort  = 3;

    public static function canViewAny(): bool  { return TenantPermission::check('view_inventory'); }
    public static function canCreate(): bool   { return TenantPermission::check('manage_inventory'); }
    public static function canEdit($r): bool   { return TenantPermission::check('manage_inventory'); }
    public static function canDelete($r): bool { return TenantPermission::check('manage_inventory'); }

    public static function getEloquentQuery(): Builder
    {
        $ownerId = auth()->user()?->current_owner_id;

        return parent::getEloquentQuery()
            ->when($ownerId, fn($q) => $q->where('owner_id', $ownerId));
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->columns(2)->schema([

                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(2),

                Forms\Components\Textarea::make('description')
                    ->columnSpan(2),

                Forms\Components\TextInput::make('default_floor_cpm')
                    ->label('Default Floor CPM')
                    ->numeric()
                    ->helperText('Giá sàn CPM mặc định cho toàn network'),

                Forms\Components\Select::make('default_floor_cpm_currency')
                    ->label('Currency')
                    ->options(['VND' => 'VND', 'USD' => 'USD'])
                    ->default('VND'),

                Forms\Components\Select::make('status')
                    ->options(['active' => 'Active', 'paused' => 'Paused'])
                    ->default('active'),

            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $canManage = TenantPermission::check('manage_inventory');

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('default_floor_cpm')
                    ->label('Floor CPM')
                    ->numeric(thousandsSeparator: ',')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('default_floor_cpm_currency')
                    ->label('Currency')
                    ->badge(),

                Tables\Columns\TextColumn::make('screens_count')
                    ->label('Screens')
                    ->getStateUsing(fn(Network $record) =>
                    \App\Models\Screen::whereHas('inventory', fn($q) =>
                    $q->where('network_id', $record->id)
                    )->count()

                    ),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors(['success' => 'active', 'warning' => 'paused']),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(['active' => 'Active', 'paused' => 'Paused']),

                SelectFilter::make('default_floor_cpm_currency')
                    ->label('Currency')
                    ->options(['VND' => 'VND', 'USD' => 'USD']),

                TernaryFilter::make('has_screens')
                    ->label('Có màn hình')
                    ->queries(
                        true:  fn(Builder $q) => $q->whereExists(fn($q2) =>
                            $q2->from('screen_inventory')->whereColumn('network_id', 'networks.id')
                        ),
                        false: fn(Builder $q) => $q->whereNotExists(fn($q2) =>
                            $q2->from('screen_inventory')->whereColumn('network_id', 'networks.id')
                        ),
                    ),
            ])
            ->filtersFormColumns(3)
            ->actions([
                Tables\Actions\EditAction::make()->visible($canManage),
                Tables\Actions\DeleteAction::make()->visible($canManage),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()->visible($canManage),
                ]),
            ])
            ->recordUrl(fn(Network $record) => NetworkResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Chưa có network nào')
            ->emptyStateIcon('heroicon-o-signal');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListNetwork::route('/'),
            'create' => Pages\CreateNetwork::route('/create'),
            'edit'   => Pages\EditNetwork::route('/{record}/edit'),
            'view'   => Pages\ViewNetwork::route('/{record}'),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['owner_id'] = auth()->user()->current_owner_id;
        return $data;
    }
}
