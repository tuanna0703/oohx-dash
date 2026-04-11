<?php
namespace App\Filament\Resources;

use App\Models\Owner;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Tables\Filters\SelectFilter;

use App\Filament\Resources\OwnerResource\Pages;
use App\Filament\Resources\OwnerResource\RelationManagers;

class OwnerResource extends Resource
{
    protected static ?string $model = Owner::class;

    protected static ?string $navigationGroup = 'Organizations';

    protected static ?int $navigationSort = 1;
    protected static ?string $label = 'Media Owner';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Basic Info')->columns(2)->schema([
                Forms\Components\TextInput::make('name')
                    ->required()->maxLength(255)->columnSpan(2),
                Forms\Components\TextInput::make('slug')
                    ->required()->unique(ignoreRecord:true)
                    ->helperText('URL-friendly, e.g. guardian-vn'),
                Forms\Components\Select::make('type')
                    ->options(['retailer'=>'Retailer','media_owner'=>'Media Owner','self'=>'Self'])
                    ->required(),
                Forms\Components\Select::make('onboard_method')
                    ->options(['cms'=>'CMS + Player','api'=>'API','vast'=>'VAST','hardware'=>'Hardware Bundle'])
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options(['pending'=>'Pending','active'=>'Active','suspended'=>'Suspended'])
                    ->default('pending')->required(),
            ]),
            Forms\Components\Section::make('Revenue & Billing')->columns(2)->schema([
                Forms\Components\TextInput::make('revenue_share_pct')
                    ->label('Revenue Share %')->numeric()->default(70)
                    ->minValue(0)->maxValue(100)->suffix('%'),
                Forms\Components\KeyValue::make('billing_info')
                    ->label('Billing Info')->columnSpan(2),
                Forms\Components\Textarea::make('notes')->columnSpan(2),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->searchable(),
                Tables\Columns\BadgeColumn::make('type')
                    ->colors(['primary'=>'media_owner','success'=>'retailer','warning'=>'self']),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors(['warning'=>'pending','success'=>'active','danger'=>'suspended']),
                Tables\Columns\TextColumn::make('screens_count')
                    ->label('Screens')->counts('screens')->sortable(),
                Tables\Columns\TextColumn::make('revenue_share_pct')->label('Rev Share')->suffix('%'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(['pending'=>'Pending','active'=>'Active','suspended'=>'Suspended']),
                SelectFilter::make('type')->options(['retailer'=>'Retailer','media_owner'=>'Media Owner']),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('stats')
                        ->icon('heroicon-o-chart-bar')
                        ->url(fn(Owner $r) => static::getUrl('edit', ['record'=>$r])),
                ]),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Overview')->columns(3)->schema([
                Infolists\Components\TextEntry::make('name'),
                Infolists\Components\TextEntry::make('status')->badge(),
                Infolists\Components\TextEntry::make('type')->badge(),
                Infolists\Components\TextEntry::make('onboard_method'),
                Infolists\Components\TextEntry::make('revenue_share_pct')->suffix('%'),
                Infolists\Components\TextEntry::make('created_at')->dateTime(),
            ]),
        ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OwnerUsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOwners::route('/'),
            'create' => Pages\CreateOwner::route('/create'),
            'edit'   => Pages\EditOwner::route('/{record}/edit'),
        ];
    }
}
