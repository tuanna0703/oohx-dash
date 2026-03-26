<?php

namespace App\Filament\Shared\Resources;

use App\Models\Network;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lớp base canonical cho Networks.
 * Admin và Publisher chỉ override phần khác biệt.
 */
abstract class BaseNetworkResource extends Resource
{
    protected static ?string $model           = Network::class;
    protected static ?string $navigationIcon  = null;
    protected static ?string $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Networks';
    protected static ?int    $navigationSort  = 3;

    // ── Hooks cho subclass ────────────────────────────────────────────────────

    /** Admin trả về Select::make('owner_id'); Publisher trả null (auto từ session). */
    protected static function ownerFormField(): ?Forms\Components\Component
    {
        return null;
    }

    /** Admin thêm cột owner.name vào bảng. */
    protected static function additionalTableColumns(): array
    {
        return [];
    }

    /** Admin thêm owner filter vào bảng. */
    protected static function additionalFilters(): array
    {
        return [];
    }

    /**
     * Publisher override để trả về true (có trang View).
     * Admin không có View page cho Network.
     */
    protected static function hasViewPage(): bool
    {
        return false;
    }

    // ── Form ─────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        $ownerField = static::ownerFormField();

        return $form->schema([
            Forms\Components\Section::make()->columns(2)->schema(
                array_values(array_filter([
                    $ownerField,

                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->columnSpan($ownerField ? 1 : 2),

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
                ]))
            ),
        ]);
    }

    // ── Table ─────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ...static::additionalTableColumns(),

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
                        true:  fn(Builder $query) => $query->whereExists(fn($q) =>
                            $q->from('screen_inventory')->whereColumn('network_id', 'networks.id')
                        ),
                        false: fn(Builder $query) => $query->whereNotExists(fn($q) =>
                            $q->from('screen_inventory')->whereColumn('network_id', 'networks.id')
                        ),
                    ),

                ...static::additionalFilters(),
            ])
            ->filtersFormColumns(3)
            ->recordUrl(fn(Network $record) =>
                static::hasViewPage() ? static::getUrl('view', ['record' => $record]) : null
            )
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Chưa có network nào')
            ->emptyStateIcon('heroicon-o-signal');
    }
}
