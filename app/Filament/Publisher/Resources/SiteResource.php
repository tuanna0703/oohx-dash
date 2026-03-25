<?php

namespace App\Filament\Publisher\Resources;

use App\Filament\Publisher\Resources\SiteResource\Pages;
use App\Models\Site;
use App\Models\VietnamCommune;
use App\Models\VietnamProvince;
use App\Services\GeocodingService;
use App\Services\TenantPermission;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SiteResource extends Resource
{
    protected static ?string $model          = Site::class;
    protected static ?string $navigationIcon = null;
    protected static ?string $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Sites';
    protected static ?int    $navigationSort  = 1;

    // Chỉ hiện nếu có quyền view_inventory
    public static function canViewAny(): bool
    {
        return TenantPermission::check('view_inventory');
    }

    public static function canCreate(): bool
    {
        return TenantPermission::check('manage_inventory');
    }

    public static function canEdit($record): bool
    {
        return TenantPermission::check('manage_inventory');
    }

    public static function canDelete($record): bool
    {
        return TenantPermission::check('manage_inventory');
    }

    // Scope query theo current_owner_id — HasOwnerScope đã handle nhưng explicit cho chắc
    public static function getEloquentQuery(): Builder
    {
        $ownerId = auth()->user()?->current_owner_id;

        return parent::getEloquentQuery()
            ->when($ownerId, fn($q) => $q->where('owner_id', $ownerId));
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Site Identity')->columns(2)->schema([
                Forms\Components\TextInput::make('external_id')
                    ->label('Site ID')
                    ->required()
                    ->maxLength(75)
                    ->helperText('Mã định danh duy nhất, ví dụ: GUARD-HN-SITE-001'),

                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(2),

                Forms\Components\Textarea::make('description')
                    ->columnSpan(2),
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
                            // Chưa có commune → city chỉ có tỉnh
                            $set('city', $p->name);
                            $set('region', $p->region);
                        } else {
                            $set('city', null);
                            $set('region', null);
                        }
                    }),

                Forms\Components\Select::make('commune_id')
                    ->label('Phường / Xã / Thị trấn')
                    ->options(fn(callable $get) => VietnamCommune::optionsForProvince($get('province_id')))
                    ->searchable()
                    ->nullable()
                    ->live()
                    ->disabled(fn(callable $get) => ! $get('province_id'))
                    ->helperText(fn(callable $get) => ! $get('province_id') ? 'Chọn tỉnh/thành trước' : null)
                    ->afterStateUpdated(function (callable $set, callable $get) {
                        $p = VietnamProvince::find($get('province_id'));
                        if (! $p) return;

                        $c = VietnamCommune::find($get('commune_id'));

                        // city = "Tỉnh > Phường/Xã" (không có quận vì chọn thủ công)
                        $set('city', implode(' > ', array_filter([
                            $p->name,
                            $c?->full_name,
                        ])));
                    }),

                // ── Địa chỉ chi tiết ──────────────────────────────────────
                Forms\Components\TextInput::make('address')
                    ->label('Địa chỉ chi tiết')
                    ->columnSpan(2),

                Forms\Components\TextInput::make('city')
                    ->label('Thành phố / Quận (tự động điền)'),

                Forms\Components\TextInput::make('region')
                    ->label('Vùng / Khu vực (tự động điền)'),

                Forms\Components\Select::make('country')
                    ->options(['VN' => 'Vietnam', 'SG' => 'Singapore', 'TH' => 'Thailand', 'ID' => 'Indonesia'])
                    ->default('VN'),

                Forms\Components\Select::make('status')
                    ->options(['active' => 'Active', 'paused' => 'Paused', 'closed' => 'Closed'])
                    ->default('active'),

                Forms\Components\TextInput::make('lat')->label('Latitude')->numeric(),
                Forms\Components\TextInput::make('lon')->label('Longitude')->numeric(),
            ]),
        ]);
    }

    // Tự động gán owner_id khi tạo mới
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['owner_id'] = auth()->user()->current_owner_id;
        return $data;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('external_id')
                    ->label('Site ID')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('province.name')
                    ->label('Tỉnh/Thành')
                    ->sortable()
                    ->searchable()
                    ->placeholder('⚠ Chưa có')
                    ->color(fn ($record) => $record->province_id ? null : 'danger'),

                Tables\Columns\TextColumn::make('commune.name')
                    ->label('Phường/Xã')
                    ->searchable()
                    ->placeholder('⚠ Chưa có')
                    ->color(fn ($record) => $record->commune_id ? null : 'warning')
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('city')
                    ->sortable()->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('region')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('screens_count')
                    ->label('Screens')
                    ->counts('screens')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'paused',
                        'danger'  => 'closed',
                    ]),

                Tables\Columns\TextColumn::make('lat')
                    ->label('Lat')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('lon')
                    ->label('Lon')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(['active' => 'Active', 'paused' => 'Paused', 'closed' => 'Closed']),

                SelectFilter::make('city')
                    ->label('Thành phố')
                    ->options(function () {
                        $ownerId = auth()->user()?->current_owner_id;
                        return cache()->remember("filter_site_cities_{$ownerId}", 300, fn() =>
                            DB::table('sites')
                                ->where('owner_id', $ownerId)
                                ->whereNull('deleted_at')
                                ->whereNotNull('city')->where('city', '!=', '')
                                ->distinct()->orderBy('city')
                                ->pluck('city', 'city')
                                ->toArray()
                        );
                    })
                    ->searchable(),

                SelectFilter::make('region')
                    ->label('Khu vực')
                    ->options(function () {
                        $ownerId = auth()->user()?->current_owner_id;
                        return cache()->remember("filter_site_regions_{$ownerId}", 300, fn() =>
                            DB::table('sites')
                                ->where('owner_id', $ownerId)
                                ->whereNull('deleted_at')
                                ->whereNotNull('region')->where('region', '!=', '')
                                ->distinct()->orderBy('region')
                                ->pluck('region', 'region')
                                ->toArray()
                        );
                    })
                    ->searchable(),

                SelectFilter::make('country')
                    ->label('Quốc gia')
                    ->options(['VN' => 'Vietnam', 'SG' => 'Singapore', 'TH' => 'Thailand', 'ID' => 'Indonesia']),

                TernaryFilter::make('has_screens')
                    ->label('Có màn hình')
                    ->queries(
                        true:  fn(Builder $query) => $query->whereIn('id',
                            DB::table('screens')->whereNull('deleted_at')->whereNotNull('site_id')->pluck('site_id')
                        ),
                        false: fn(Builder $query) => $query->whereNotIn('id',
                            DB::table('screens')->whereNull('deleted_at')->whereNotNull('site_id')->pluck('site_id')
                        ),
                    ),

                Filter::make('has_gps')
                    ->label('Có tọa độ GPS')
                    ->toggle()
                    ->query(fn(Builder $query, array $data) => $query->when(
                        $data['isActive'] ?? false,
                        fn(Builder $query) => $query->whereNotNull('lat')->whereNotNull('lon')
                    )),

                // ── Bộ lọc thiếu thông tin địa chỉ ────────────────────────
                Filter::make('location_completeness')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label('Trạng thái địa chỉ')
                            ->placeholder('-- Tất cả --')
                            ->options([
                                'missing_province' => '⚠ Thiếu Tỉnh/Thành',
                                'missing_commune'  => '⚠ Thiếu Phường/Xã',
                                'missing_both'     => '⚠ Thiếu cả Tỉnh lẫn Phường/Xã',
                                'gps_not_geocoded' => '📍 Có GPS, chưa geocode',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['status'] ?? null) {
                            'missing_province' => $query->whereNull('province_id'),
                            'missing_commune'  => $query->whereNull('commune_id'),
                            'missing_both'     => $query->whereNull('province_id')->whereNull('commune_id'),
                            'gps_not_geocoded' => $query->whereNotNull('lat')->whereNotNull('lon')->whereNull('province_id'),
                            default            => $query,
                        };
                    })
                    ->indicateUsing(function (array $data): ?string {
                        return match ($data['status'] ?? null) {
                            'missing_province' => '⚠ Thiếu Tỉnh/Thành',
                            'missing_commune'  => '⚠ Thiếu Phường/Xã',
                            'missing_both'     => '⚠ Thiếu cả Tỉnh lẫn Phường/Xã',
                            'gps_not_geocoded' => '📍 Có GPS, chưa geocode',
                            default            => null,
                        };
                    }),
            ])
            ->filtersFormColumns(3)
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(TenantPermission::check('manage_inventory')),
                Tables\Actions\DeleteAction::make()
                    ->visible(TenantPermission::check('manage_inventory')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('geocode_bulk')
                        ->label('Geocode từ GPS')
                        ->icon('heroicon-o-map-pin')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading('Geocode từ tọa độ GPS')
                        ->modalDescription('Chỉ các sites có lat/lon mới được xử lý. Mỗi request mất ~1 giây.')
                        ->visible(TenantPermission::check('manage_inventory'))
                        ->action(function (Collection $records) {
                            $geocoding = app(GeocodingService::class);
                            $updated   = 0;
                            $skipped   = 0;

                            foreach ($records as $site) {
                                if (! $site->lat || ! $site->lon) {
                                    $skipped++;
                                    continue;
                                }

                                $result = $geocoding->resolveLocation(
                                    (float) $site->lat,
                                    (float) $site->lon
                                );

                                if ($result) {
                                    $site->update($result);
                                    $updated++;
                                } else {
                                    $skipped++;
                                }

                                sleep(1); // Nominatim rate limit
                            }

                            Notification::make()
                                ->title('Geocode hoàn tất')
                                ->body("Đã cập nhật {$updated} site(s)" . ($skipped ? ", bỏ qua {$skipped}" : '') . '.')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(TenantPermission::check('manage_inventory')),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSite::route('/'),
            'create' => Pages\CreateSite::route('/create'),
            'edit'   => Pages\EditSite::route('/{record}/edit'),
        ];
    }
}
