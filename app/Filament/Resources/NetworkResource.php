<?php
namespace App\Filament\Resources;

use App\Models\Network;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Resources\NetworkResource\Pages;


class NetworkResource extends Resource
{
    protected static ?string $model = Network::class;

    protected static ?string $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('owner_id')
                ->relationship('owner', 'name')->searchable()->preload()->required(),
            Forms\Components\TextInput::make('name')->required()->maxLength(255),
            Forms\Components\Textarea::make('description'),
            Forms\Components\TextInput::make('default_floor_cpm')->numeric()->label('Default Floor CPM'),
            Forms\Components\Select::make('default_floor_cpm_currency')
                ->options(['VND'=>'VND','USD'=>'USD'])->default('VND'),
            Forms\Components\Select::make('status')
                ->options(['active'=>'Active','paused'=>'Paused'])->default('active'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
            Tables\Columns\TextColumn::make('owner.name')->label('Owner'),
            Tables\Columns\TextColumn::make('default_floor_cpm')->label('Floor CPM'),
            Tables\Columns\TextColumn::make('default_floor_cpm_currency')->label('Currency'),
            Tables\Columns\BadgeColumn::make('status')
                ->colors(['success'=>'active','warning'=>'paused']),
        ])
        ->actions([Tables\Actions\EditAction::make(), Tables\Actions\DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListNetworks::route('/'),
            'create' => Pages\CreateNetwork::route('/create'),
            'edit'   => Pages\EditNetwork::route('/{record}/edit'),
        ];
    }
}
