<?php
namespace App\Filament\Resources;

use App\Models\Owner;
use App\Models\Site;
use App\Models\VietnamCommune;
use App\Models\VietnamProvince;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use App\Filament\Resources\SiteResource\Pages;


class SiteResource extends Resource
{
    protected static ?string $model = Site::class;

    protected static ?string $navigationGroup = 'Inventory';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Site Identity')->columns(2)->schema([
                Forms\Components\Select::make('owner_id')
                    ->label('Media Owner')
                    ->relationship('owner', 'name')
                    ->searchable()->preload()->required(),
                Forms\Components\TextInput::make('external_id')
                    ->label('Media Owner Site ID')
                    ->required()->maxLength(75)
                    ->helperText('e.g. GUARD-HOR-IND-SITE001'),
                Forms\Components\TextInput::make('name')
                    ->required()->maxLength(255)->columnSpan(2),
                Forms\Components\Textarea::make('description')->columnSpan(2),
            ]),
            Forms\Components\Section::make('Location')->columns(2)->schema([
                // ── Địa giới hành chính Việt Nam ──────────────────────────
                Forms\Components\Select::make('province_id')
                    ->label('Tỉnh / Thành phố')
                    ->options(fn() => VietnamProvince::orderByRaw("type = 'thanh_pho' DESC")
                        ->orderBy('name')->pluck('full_name', 'id'))
                    ->searchable()
                    ->nullable()
                    ->live()
                    ->afterStateUpdated(function (callable $set, callable $get) {
                        $set('commune_id', null);
                        $p = VietnamProvince::find($get('province_id'));
                        if ($p) {
                            $set('city', $p->name);
                            $set('region', $p->region);
                        }
                    }),

                Forms\Components\Select::make('commune_id')
                    ->label('Phường / Xã / Thị trấn')
                    ->options(fn(callable $get) => VietnamCommune::optionsForProvince($get('province_id')))
                    ->searchable()
                    ->nullable()
                    ->disabled(fn(callable $get) => ! $get('province_id'))
                    ->helperText(fn(callable $get) => ! $get('province_id') ? 'Chọn tỉnh/thành trước' : null),

                // ── Địa chỉ chi tiết ──────────────────────────────────────
                Forms\Components\TextInput::make('address')->columnSpan(2),
                Forms\Components\TextInput::make('city'),
                Forms\Components\TextInput::make('region'),
                Forms\Components\Select::make('country')
                    ->options(['VN'=>'Vietnam','SG'=>'Singapore','TH'=>'Thailand','ID'=>'Indonesia'])
                    ->default('VN'),
                Forms\Components\Select::make('status')
                    ->options(['active'=>'Active','paused'=>'Paused','closed'=>'Closed'])
                    ->default('active'),
                Forms\Components\TextInput::make('lat')->label('Latitude')->numeric(),
                Forms\Components\TextInput::make('lon')->label('Longitude')->numeric(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('owner.name')->label('Owner')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('external_id')->label('External ID')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('province.name')
                    ->label('Tỉnh/Thành')->sortable()->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('city')->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('region')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('screens_count')
                    ->label('Screens')->counts('screens')->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors(['success'=>'active','warning'=>'paused','danger'=>'closed']),
                Tables\Columns\TextColumn::make('lat')->label('Lat')->toggleable(isToggledHiddenByDefault:true),
                Tables\Columns\TextColumn::make('lon')->label('Lon')->toggleable(isToggledHiddenByDefault:true),
            ])
            ->filters([
                SelectFilter::make('status')->options(['active'=>'Active','paused'=>'Paused','closed'=>'Closed']),
                SelectFilter::make('country')->options(['VN'=>'Vietnam','SG'=>'Singapore']),
                SelectFilter::make('owner')->relationship('owner','name')->searchable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSites::route('/'),
            'create' => Pages\CreateSite::route('/create'),
            'edit'   => Pages\EditSite::route('/{record}/edit'),
        ];
    }
}
