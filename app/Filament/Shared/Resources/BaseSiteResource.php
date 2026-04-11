<?php

namespace App\Filament\Shared\Resources;

use App\Models\Site;
use App\Models\VietnamCommune;
use App\Models\VietnamProvince;
use App\Services\GeocodingService;
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

/**
 * Lớp base chứa toàn bộ UI canonical cho Sites.
 * Admin và Publisher chỉ override phần khác biệt (phân quyền, scoping, owner field).
 *
 * Không đặt trong thư mục được panel discover → không tự đăng ký.
 */
abstract class BaseSiteResource extends Resource
{
    protected static ?string $model           = Site::class;
    protected static ?string $navigationIcon  = null;
    protected static ?string $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Sites';
    protected static ?int    $navigationSort  = 1;

    // ── Hooks cho subclass override ───────────────────────────────────────────

    /**
     * Admin override để trả về field chọn owner.
     * Publisher trả null — owner_id được tự động gán từ session.
     */
    protected static function ownerFormField(): ?Forms\Components\Component
    {
        return null;
    }

    /**
     * Admin override để thêm cột owner vào bảng.
     */
    protected static function additionalTableColumns(): array
    {
        return [];
    }

    /**
     * Admin override để thêm filter owner vào bảng.
     */
    protected static function additionalFilters(): array
    {
        return [];
    }

    // ── Form ─────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        $ownerField = static::ownerFormField();

        return $form->schema([

            Forms\Components\Section::make('Site Identity')->columns(2)->schema(
                array_values(array_filter([
                    $ownerField,

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

                    Forms\Components\TextInput::make('hivestack_site_id')
                        ->label('Hivestack Site ID')
                        ->disabled()
                        ->dehydrated(false)
                        ->placeholder('—')
                        ->helperText('Tự động gán khi import từ Hivestack')
                        ->visible(fn (?Site $record) => $record?->hivestack_site_id !== null),
                ]))
            ),

            Forms\Components\Section::make('Hình ảnh')->schema([
                Forms\Components\FileUpload::make('banner')
                    ->label('Banner')
                    ->image()
                    ->directory('sites/banners')
                    ->disk('public')
                    ->imageResizeMode('cover')
                    ->imageCropAspectRatio('21:9')
                    ->imageResizeTargetWidth('1200')
                    ->imageResizeTargetHeight('514')
                    ->maxSize(2048)
                    ->helperText('Ảnh bìa hiển thị trên trang chi tiết site. Khuyến nghị 1200×514px, tối đa 2MB.'),
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
                    ->label('Thành phố / Quận')
                    ->disabled(fn (callable $get) => (bool) $get('province_id'))
                    ->dehydrated()
                    ->helperText(fn (callable $get) => $get('province_id') ? 'Tự động điền từ Tỉnh/Thành' : null),

                Forms\Components\TextInput::make('region')
                    ->label('Vùng / Khu vực')
                    ->disabled(fn (callable $get) => (bool) $get('province_id'))
                    ->dehydrated()
                    ->helperText(fn (callable $get) => $get('province_id') ? 'Tự động điền từ Tỉnh/Thành' : null),

                Forms\Components\Select::make('country')
                    ->options(['VN' => 'Vietnam', 'SG' => 'Singapore', 'TH' => 'Thailand', 'ID' => 'Indonesia'])
                    ->default('VN'),

                Forms\Components\Select::make('status')
                    ->options(['active' => 'Active', 'paused' => 'Paused', 'closed' => 'Closed'])
                    ->default('active'),

                Forms\Components\TextInput::make('lat')
                    ->label('Latitude')
                    ->numeric()
                    ->live(debounce: 800),

                Forms\Components\TextInput::make('lon')
                    ->label('Longitude')
                    ->numeric()
                    ->live(debounce: 800),

                // ── Map Preview ────────────────────────────────────────────
                Forms\Components\View::make('filament.forms.components.map-picker')
                    ->columnSpan(2),
            ]),
        ]);
    }

    // ── Table ─────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->columns([
                // Cột bổ sung từ subclass (ví dụ: owner cho admin)
                ...static::additionalTableColumns(),

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
                    ->color(fn($record) => $record->province_id ? null : 'danger'),

                Tables\Columns\TextColumn::make('commune.name')
                    ->label('Phường/Xã')
                    ->searchable()
                    ->placeholder('⚠ Chưa có')
                    ->color(fn($record) => $record->commune_id ? null : 'warning')
                    ->toggleable(isToggledHiddenByDefault: false),

                Tables\Columns\TextColumn::make('city')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

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

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(['active' => 'Active', 'paused' => 'Paused', 'closed' => 'Closed']),

                SelectFilter::make('city')
                    ->label('Thành phố')
                    ->options(function () {
                        $ownerId  = auth()->user()?->current_owner_id;
                        $cacheKey = 'filter_site_cities_' . ($ownerId ?? 'all');
                        return cache()->remember($cacheKey, 300, fn() =>
                            DB::table('sites')
                                ->when($ownerId, fn($q) => $q->where('owner_id', $ownerId))
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
                        $ownerId  = auth()->user()?->current_owner_id;
                        $cacheKey = 'filter_site_regions_' . ($ownerId ?? 'all');
                        return cache()->remember($cacheKey, 300, fn() =>
                            DB::table('sites')
                                ->when($ownerId, fn($q) => $q->where('owner_id', $ownerId))
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
                        true:  fn (Builder $query) => $query->whereHas('screens'),
                        false: fn (Builder $query) => $query->whereDoesntHave('screens'),
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

                // Filter bổ sung từ subclass (ví dụ: owner cho admin)
                ...static::additionalFilters(),
            ])
            ->filtersFormColumns(3)
            ->recordUrl(fn(Site $record) => static::getUrl('view', ['record' => $record]))
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
                        ->visible(fn() => static::canCreate())
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

                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
