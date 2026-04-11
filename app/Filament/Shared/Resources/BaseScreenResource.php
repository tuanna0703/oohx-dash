<?php

namespace App\Filament\Shared\Resources;

use App\Models\Network;
use App\Models\Screen;
use App\Models\Site;
use App\Models\VenueCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lớp base canonical cho Screens.
 * Phase 1: Marketplace listing/booking — form tối giản, adops collapsed.
 */
abstract class BaseScreenResource extends Resource
{
    protected static ?string $model           = Screen::class;
    protected static ?string $navigationIcon  = null;
    protected static ?string $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Screens';
    protected static ?int    $navigationSort  = 1;

    // ── Hooks cho subclass ────────────────────────────────────────────────────

    protected static function canPricing(): bool
    {
        return true;
    }

    protected static function siteFormOptions(): array
    {
        return Site::orderBy('name')->pluck('name', 'id')->toArray();
    }

    protected static function networkFormOptions(): array
    {
        return Network::orderBy('name')->pluck('name', 'id')->toArray();
    }

    protected static function siteFilterOptions(): array
    {
        return cache()->remember('filter_sites_all', 300,
            fn () => Site::orderBy('name')->pluck('name', 'id')->toArray()
        );
    }

    protected static function networkFilterOptions(): array
    {
        return cache()->remember('filter_networks_all', 300,
            fn () => Network::orderBy('name')->pluck('name', 'id')->toArray()
        );
    }

    protected static function additionalTableColumns(): array
    {
        return [];
    }

    protected static function additionalFilters(): array
    {
        return [];
    }

    // ── Form ─────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        $canPricing = static::canPricing();

        return $form->schema([

            Forms\Components\Tabs::make('screen_tabs')
                ->tabs([

                    // ── TAB 1: Thông tin chung ───────────────────────────────
                    Forms\Components\Tabs\Tab::make('Thông tin')
                        ->icon('heroicon-o-information-circle')
                        ->columns(3)
                        ->schema([
                            Forms\Components\TextInput::make('external_id')
                                ->label('Screen ID')
                                ->required()
                                ->maxLength(75)
                                ->placeholder('GUARD-HOR-IND-001'),

                            Forms\Components\TextInput::make('name')
                                ->label('Tên màn hình')
                                ->required()
                                ->maxLength(199)
                                ->columnSpan(2),

                            Forms\Components\Select::make('site_id')
                                ->label('Site')
                                ->required()
                                ->placeholder('Chọn site')
                                ->options(fn () => static::siteFormOptions())
                                ->searchable(),

                            Forms\Components\Select::make('inventory.network_id')
                                ->label('Network')
                                ->required()
                                ->placeholder('Chọn network')
                                ->options(fn () => static::networkFormOptions())
                                ->searchable(),

                            Forms\Components\Select::make('inventory.vn_category_id')
                                ->label('Loại biển')
                                ->placeholder('Chọn loại biển')
                                ->options(fn () => VenueCategory::where('is_active', true)
                                    ->orderBy('sort_order')
                                    ->pluck('name_vi', 'id')
                                    ->toArray())
                                ->searchable(),

                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\Select::make('status')
                                    ->label('Device status')
                                    ->options([
                                        'online'      => 'Online',
                                        'offline'     => 'Offline',
                                        'maintenance' => 'Maintenance',
                                    ])
                                    ->default('offline'),

                                Forms\Components\Toggle::make('active')
                                    ->label('Hiển thị trên marketplace')
                                    ->default(true)
                                    ->inline(false),

                                Forms\Components\Toggle::make('inventory.programmatic_enabled')
                                    ->label('Cho phép Programmatic')
                                    ->default(false)
                                    ->inline(false),
                            ])->columnSpan(3),

                            Forms\Components\Textarea::make('description')
                                ->label('Mô tả')
                                ->rows(2)
                                ->columnSpan(3),
                        ]),

                    // ── TAB 2: Kỹ thuật & Ảnh ───────────────────────────────
                    Forms\Components\Tabs\Tab::make('Kỹ thuật')
                        ->icon('heroicon-o-tv')
                        ->columns(2)
                        ->schema([
                            Forms\Components\FileUpload::make('spec.photo_url')
                                ->label('Ảnh màn hình')
                                ->image()
                                ->disk('public')
                                ->directory('screen-photos')
                                ->imagePreviewHeight('160')
                                ->columnSpan(2),

                            Forms\Components\View::make('filament.publisher.components.resolution-picker')
                                ->columnSpan(2),

                            Forms\Components\Hidden::make('spec.width_px')->default(1920),
                            Forms\Components\Hidden::make('spec.height_px')->default(1080),
                            Forms\Components\Hidden::make('spec.resolution_preset')->default('1920x1080'),

                            Forms\Components\TextInput::make('spec.width_cm')
                                ->label('Chiều rộng (cm)')
                                ->numeric(),

                            Forms\Components\TextInput::make('spec.height_cm')
                                ->label('Chiều cao (cm)')
                                ->numeric(),

                            Forms\Components\Hidden::make('spec.width_unit')->default('cm'),
                            Forms\Components\Hidden::make('spec.height_unit')->default('cm'),
                            Forms\Components\Hidden::make('spec.allow_image')->default(true),
                            Forms\Components\Hidden::make('spec.allow_video')->default(true),
                            Forms\Components\Hidden::make('spec.allow_html')->default(false),
                            Forms\Components\Hidden::make('spec.allow_zip')->default(false),
                        ]),

                    // ── TAB 3: Giá & Lịch ────────────────────────────────────
                    Forms\Components\Tabs\Tab::make('Giá & Lịch')
                        ->icon('heroicon-o-currency-dollar')
                        ->columns(3)
                        ->schema([
                            Forms\Components\TextInput::make('inventory.floor_cpm')
                                ->label('Giá sàn (Floor CPM)')
                                ->numeric()
                                ->prefix('₫')
                                ->default(10.00)
                                ->visible($canPricing),

                            Forms\Components\TextInput::make('inventory.weekly_impressions')
                                ->label('Lượt xem / tuần')
                                ->numeric(),

                            Forms\Components\TextInput::make('inventory.spot_length')
                                ->label('Thời lượng QC')
                                ->numeric()
                                ->default(15)
                                ->suffix('giây'),

                            Forms\Components\Section::make('Lịch hoạt động')
                                ->columnSpan(3)
                                ->compact()
                                ->schema([
                                    Forms\Components\View::make('filament.publisher.components.operating-hours-grid'),
                                    Forms\Components\Hidden::make('inventory.operating_hours'),
                                ]),
                        ]),

                    // ── TAB 4: Vị trí ────────────────────────────────────────
                    Forms\Components\Tabs\Tab::make('Vị trí')
                        ->icon('heroicon-o-map-pin')
                        ->columns(3)
                        ->schema([
                            Forms\Components\TextInput::make('site.lat')
                                ->label('Latitude')
                                ->numeric()
                                ->step(0.0000001),

                            Forms\Components\TextInput::make('site.lon')
                                ->label('Longitude')
                                ->numeric()
                                ->step(0.0000001),

                            Forms\Components\Select::make('inventory.timezone')
                                ->label('Múi giờ')
                                ->options(fn () => collect(timezone_identifiers_list())
                                    ->mapWithKeys(fn ($tz) => [$tz => $tz]))
                                ->searchable()
                                ->default('Asia/Ho_Chi_Minh'),
                        ]),

                ])
                ->persistTabInQueryString('tab')
                ->columnSpanFull(),

            // ── AdOps nâng cao (ngoài tabs, collapsed) ───────────────────
            Forms\Components\Section::make('AdOps nâng cao')
                ->description('Cấu hình SSP/programmatic — bỏ qua nếu chỉ cần listing marketplace.')
                ->collapsed()
                ->visible($canPricing && config('oohx.show_adops_fields', false))
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('unit_id')
                        ->label('Unit ID')
                        ->maxLength(100)
                        ->nullable(),

                    Forms\Components\TextInput::make('uuid')
                        ->label('Ad request API UUID')
                        ->helperText('Auto-generated if empty.')
                        ->maxLength(255),

                    Forms\Components\Select::make('player_type')
                        ->label('Player type')
                        ->options([
                            'adtrue_android' => 'AdTRUE Android',
                            'adtrue_webview' => 'AdTRUE WebView',
                            'third_party'    => '3rd Party Player',
                            'vast_only'      => 'VAST Only',
                        ])
                        ->default('adtrue_android'),

                    Forms\Components\Select::make('spec.facing_direction')
                        ->label('Hướng mặt biển')
                        ->placeholder('Chọn hướng')
                        ->options([
                            'N' => 'North', 'NE' => 'North East',
                            'E' => 'East',  'SE' => 'South East',
                            'S' => 'South', 'SW' => 'South West',
                            'W' => 'West',  'NW' => 'North West',
                            'Rotating' => 'Rotating',
                        ])
                        ->nullable(),

                    Forms\Components\TextInput::make('inventory.share_of_voice_max_pct')
                        ->label('Max SOV (%)')
                        ->numeric()
                        ->default(100)
                        ->suffix('%'),

                    Forms\Components\TextInput::make('inventory.screen_count_override')
                        ->label('Screen count')
                        ->numeric()
                        ->nullable()
                        ->placeholder('1'),

                    Forms\Components\TextInput::make('inventory.max_spot_length')
                        ->label('Max duration')
                        ->numeric()
                        ->default(180)
                        ->suffix('s'),

                    Forms\Components\TextInput::make('inventory.min_spot_length')
                        ->label('Min duration')
                        ->numeric()
                        ->default(3)
                        ->suffix('s'),

                    Forms\Components\TextInput::make('inventory.loop_length')
                        ->label('Loop length')
                        ->numeric()
                        ->suffix('s'),

                    Forms\Components\TextInput::make('inventory.frequency_cap')
                        ->label('Freq cap')
                        ->numeric()
                        ->default(0)
                        ->suffix('s')
                        ->helperText('0 = unlimited'),

                    Forms\Components\TextInput::make('inventory.category_frequency_cap')
                        ->label('Cat freq cap')
                        ->numeric()
                        ->default(0)
                        ->suffix('s'),

                    Forms\Components\Toggle::make('inventory.strict_frequency_capping')
                        ->label('Strict freq cap')
                        ->default(false),

                    Forms\Components\Textarea::make('internal_notes')
                        ->label('Ghi chú nội bộ')
                        ->rows(2)
                        ->columnSpan(3),

                    Forms\Components\View::make('filament.publisher.components.selective-listing-picker')
                        ->columnSpan(3),
                    Forms\Components\Hidden::make('inventory.pmp_only')->default(false),
                    Forms\Components\Hidden::make('inventory.ad_server_enabled')->default(true),
                    Forms\Components\Hidden::make('inventory.deals_enabled')->default(true),
                ]),

        ])->columns(1);
    }

    // ── Table ─────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        $canPricing = static::canPricing();

        return $table
            ->deferLoading()
            ->columns([
                ...static::additionalTableColumns(),

                // ── Identity ─────────────────────────────────────────────────
                Tables\Columns\TextColumn::make('name')
                    ->label('Tên')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->extraAttributes(['x-init' => '
                        $nextTick(() => {
                            const td = $el.closest("td"); if (!td) return;
                            const cb = td.previousElementSibling;
                            if (cb) {
                                cb.style.cssText += ";position:sticky;left:0;z-index:2;background-color:white";
                                td.style.cssText  = "position:sticky;left:" + cb.offsetWidth + "px;z-index:2;background-color:white;box-shadow:inset -1px 0 0 #e5e7eb";
                            } else {
                                td.style.cssText  = "position:sticky;left:0;z-index:2;background-color:white;box-shadow:inset -1px 0 0 #e5e7eb";
                            }
                        })
                    '])
                    ->extraHeaderAttributes(['x-init' => '
                        $nextTick(() => {
                            const th = $el;
                            const cb = th.previousElementSibling;
                            if (cb) {
                                cb.style.cssText += ";position:sticky;left:0;z-index:3;background-color:#f9fafb";
                                th.style.cssText  = "position:sticky;left:" + cb.offsetWidth + "px;z-index:3;background-color:#f9fafb;box-shadow:inset -1px 0 0 #e5e7eb";
                            } else {
                                th.style.cssText  = "position:sticky;left:0;z-index:3;background-color:#f9fafb;box-shadow:inset -1px 0 0 #e5e7eb";
                            }
                        })
                    ']),

                Tables\Columns\TextColumn::make('external_id')
                    ->label('Screen ID')
                    ->searchable()
                    ->copyable()
                    ->sortable(),

                // ── Status ───────────────────────────────────────────────────
                Tables\Columns\IconColumn::make('active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Device')
                    ->colors([
                        'success' => 'online',
                        'danger'  => 'offline',
                        'warning' => 'maintenance',
                    ])
                    ->formatStateUsing(fn ($state) => ucfirst($state ?? 'unknown')),

                // ── Location ─────────────────────────────────────────────────
                Tables\Columns\TextColumn::make('site.name')
                    ->label('Site')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('network_name')
                    ->label('Network')
                    ->getStateUsing(fn (Screen $r) => $r->inventory?->network?->name ?? $r->inventory?->network_name ?? '—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('inventory.vnCategory.name_vi')
                    ->label('Loại biển')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('site_location')
                    ->label('Thành phố')
                    ->getStateUsing(fn (Screen $r) => $r->site?->city ?? '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                // ── Specs ────────────────────────────────────────────────────
                Tables\Columns\TextColumn::make('resolution')
                    ->label('Resolution')
                    ->getStateUsing(fn (Screen $r) => $r->spec
                        ? "{$r->spec->width_px}×{$r->spec->height_px}"
                        : '—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('physical_size')
                    ->label('Kích thước')
                    ->getStateUsing(fn (Screen $r) => $r->spec?->width_cm
                        ? "{$r->spec->width_cm}×{$r->spec->height_cm} cm"
                        : '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                // ── Commercial ───────────────────────────────────────────────
                Tables\Columns\TextColumn::make('inventory.floor_cpm')
                    ->label('Floor CPM')
                    ->formatStateUsing(fn ($state, Screen $r) => $state
                        ? number_format((float) $state, 0, '.', ',') . ' ' . ($r->inventory?->floor_cpm_currency ?? 'VND')
                        : '—')
                    ->visible($canPricing)
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('inventory.weekly_impressions')
                    ->label('Impr./tuần')
                    ->formatStateUsing(fn ($state) => $state ? number_format((int) $state) : '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('inventory.programmatic_enabled')
                    ->label('RTB')
                    ->boolean()
                    ->visible($canPricing)
                    ->toggleable(isToggledHiddenByDefault: true),

                // ── Timestamps ───────────────────────────────────────────────
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('active')
                    ->label('Active'),

                SelectFilter::make('status')
                    ->label('Device status')
                    ->options([
                        'online'      => 'Online',
                        'offline'     => 'Offline',
                        'maintenance' => 'Maintenance',
                    ]),

                SelectFilter::make('site_id')
                    ->label('Site')
                    ->options(fn () => static::siteFilterOptions())
                    ->searchable(),

                SelectFilter::make('network')
                    ->label('Network')
                    ->options(fn () => static::networkFilterOptions())
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn ($q, $v) => $q->whereHas('inventory', fn ($iq) => $iq->where('network_id', $v))
                    ))
                    ->searchable(),

                SelectFilter::make('vn_category')
                    ->label('Loại biển')
                    ->options(fn () => VenueCategory::where('is_active', true)
                        ->orderBy('sort_order')
                        ->pluck('name_vi', 'id')
                        ->toArray())
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn ($q, $v) => $q->whereHas('inventory', fn ($iq) => $iq->where('vn_category_id', $v))
                    ))
                    ->searchable(),

                ...static::additionalFilters(),
            ])
            ->filtersFormColumns(3)
            ->recordUrl(fn (Screen $r) => static::getUrl('view', ['record' => $r]))
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle_rtb')
                    ->label(fn (Screen $r) => $r->inventory?->programmatic_enabled ? 'Disable RTB' : 'Enable RTB')
                    ->icon('heroicon-o-signal')
                    ->color(fn (Screen $r) => $r->inventory?->programmatic_enabled ? 'danger' : 'success')
                    ->visible($canPricing)
                    ->requiresConfirmation()
                    ->action(function (Screen $r) {
                        $inv = $r->inventory;
                        if ($inv) {
                            $inv->update(['programmatic_enabled' => ! $inv->programmatic_enabled]);
                            Notification::make()->title('Đã cập nhật RTB')->success()->send();
                        }
                    }),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
