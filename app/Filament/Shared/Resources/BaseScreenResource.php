<?php

namespace App\Filament\Shared\Resources;

use App\Models\Network;
use App\Models\Screen;
use App\Models\Site;
use App\Models\VenueCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
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

    protected static function showOwnerField(): bool
    {
        return false; // Admin override = true
    }

    protected static function canPricing(): bool
    {
        return true;
    }

    protected static function siteFormOptions(): array
    {
        return Site::orderBy('name')->pluck('name', 'id')->toArray();
    }

    protected static function sitesByNetwork(?string $networkId): array
    {
        if (! $networkId) return static::siteFormOptions();
        return Site::where('network_id', $networkId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
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
                            Forms\Components\Select::make('owner_id')
                                ->label('Media Owner')
                                ->relationship('owner', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->visible(static::showOwnerField())
                                ->afterStateUpdated(fn (callable $set) => $set('network_filter', null) || $set('site_id', null))
                                ->columnSpan(3),

                            Forms\Components\TextInput::make('name')
                                ->label('Tên màn hình')
                                ->required()
                                ->maxLength(199)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (callable $set, callable $get, ?string $state) {
                                    if ($state && ! $get('external_id')) {
                                        $set('external_id', strtoupper(\Illuminate\Support\Str::slug($state, '-')));
                                    }
                                    if ($state && ! $get('slug')) {
                                        $set('slug', \Illuminate\Support\Str::slug($state, '-', 'vi'));
                                    }
                                })
                                ->columnSpan(2),

                            Forms\Components\TextInput::make('external_id')
                                ->label('Screen ID')
                                ->maxLength(75)
                                ->helperText('Tự động từ tên. Có thể sửa tay.'),

                            Forms\Components\TextInput::make('slug')
                                ->label('Slug (SEO URL)')
                                ->maxLength(150)
                                ->unique(ignoreRecord: true)
                                ->helperText('Tự động từ tên. URL: /explore/{slug}'),

                            Forms\Components\Select::make('network_filter')
                                ->label('Network')
                                ->placeholder('Chọn network')
                                ->options(fn (callable $get) => static::showOwnerField() && $get('owner_id')
                                    ? Network::where('owner_id', $get('owner_id'))->orderBy('name')->pluck('name', 'id')->toArray()
                                    : static::networkFormOptions())
                                ->searchable()
                                ->live()
                                ->afterStateHydrated(function (Forms\Components\Select $component, ?Screen $record) {
                                    if ($record) {
                                        $component->state($record->site?->network_id);
                                    }
                                })
                                ->afterStateUpdated(fn (callable $set) => $set('site_id', null))
                                ->dehydrated(false),

                            Forms\Components\Select::make('site_id')
                                ->label('Site')
                                ->required()
                                ->placeholder(fn (callable $get) => $get('network_filter') ? 'Chọn site' : 'Chọn network trước')
                                ->options(function (callable $get) {
                                    $networkId = $get('network_filter');
                                    if (static::showOwnerField()) {
                                        $ownerId = $get('owner_id');
                                        if (! $ownerId) return [];
                                        $q = Site::where('owner_id', $ownerId);
                                        if ($networkId) $q->where('network_id', $networkId);
                                        return $q->orderBy('name')->pluck('name', 'id')->toArray();
                                    }
                                    return static::sitesByNetwork($networkId);
                                })
                                ->searchable()
                                ->live(),

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
                            Forms\Components\FileUpload::make('spec.photos')
                                ->label('Ảnh màn hình')
                                ->image()
                                ->multiple()
                                ->reorderable()
                                ->maxFiles(10)
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
                            Forms\Components\TextInput::make('_site_lat')
                                ->label('Latitude')
                                ->numeric()
                                ->step(0.0000001)
                                ->live(debounce: 800)
                                ->afterStateHydrated(fn ($component, $record) =>
                                    $component->state($record?->site?->lat))
                                ->dehydrated(false),

                            Forms\Components\TextInput::make('_site_lon')
                                ->label('Longitude')
                                ->numeric()
                                ->step(0.0000001)
                                ->live(debounce: 800)
                                ->afterStateHydrated(fn ($component, $record) =>
                                    $component->state($record?->site?->lon))
                                ->dehydrated(false),

                            Forms\Components\Select::make('inventory.timezone')
                                ->label('Múi giờ')
                                ->options(fn () => collect(timezone_identifiers_list())
                                    ->mapWithKeys(fn ($tz) => [$tz => $tz]))
                                ->searchable()
                                ->default('Asia/Ho_Chi_Minh'),

                            Forms\Components\View::make('filament.forms.components.screen-map-picker')
                                ->columnSpan(3),
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

                    Forms\Components\Hidden::make('uuid'),

                    Forms\Components\Select::make('player_type')
                        ->label('Player type')
                        ->options([
                            'adtrue_android' => 'OOHX Android',
                            'adtrue_webview' => 'OOHX WebView',
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

                Tables\Columns\TextColumn::make('name')
                    ->label('Tên')
                    ->searchable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('external_id')
                    ->label('Screen ID')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\IconColumn::make('active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'online',
                        'danger'  => 'offline',
                        'warning' => 'maintenance',
                    ])
                    ->formatStateUsing(fn ($state) => ucfirst($state ?? 'unknown')),
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
            ->recordAction('quick_detail')
            ->recordUrl(null)
            ->actions([
                Tables\Actions\Action::make('quick_detail')
                    ->label('Chi tiết')
                    ->icon('heroicon-o-information-circle')
                    ->color('gray')
                    ->modalHeading(fn (Screen $record) => $record->name)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Đóng')
                    ->modalWidth('4xl')
                    ->modalContent(fn (Screen $record) => view(
                        'filament.shared.components.screen-detail-modal',
                        ['screen' => $record->load(['owner', 'site.network', 'spec', 'inventory.vnCategory'])]
                    )),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
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
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    // ── Infolist (View page) ────────────────────────────────────────────────

    /**
     * Admin override để thêm section AdOps nâng cao.
     */
    protected static function showAdOpsSection(): bool
    {
        return config('oohx.show_adops_fields', false);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        $showAdOps = static::showAdOpsSection();

        return $infolist->schema([

            Infolists\Components\Tabs::make('screen_detail_tabs')
                ->persistTabInQueryString('tab')
                ->columnSpanFull()
                ->tabs(array_filter([

                    // ── Tab 1: Thông tin chung ──────────────────────────────
                    Infolists\Components\Tabs\Tab::make('Thông tin')
                        ->icon('heroicon-o-information-circle')
                        ->columns(3)
                        ->schema([
                            Infolists\Components\TextEntry::make('external_id')
                                ->label('Screen ID')
                                ->copyable()
                                ->fontFamily('mono'),

                            Infolists\Components\TextEntry::make('active_status')
                                ->label('Trạng thái')
                                ->badge()
                                ->getStateUsing(fn (Screen $r) => $r->active ? 'Active' : 'Inactive')
                                ->color(fn ($state) => $state === 'Active' ? 'success' : 'danger'),

                            Infolists\Components\TextEntry::make('status')
                                ->label('Device')
                                ->badge()
                                ->formatStateUsing(fn ($state) => ucfirst($state ?? 'unknown'))
                                ->color(fn ($state) => match ($state) {
                                    'online'      => 'success',
                                    'maintenance' => 'warning',
                                    default       => 'danger',
                                }),

                            Infolists\Components\TextEntry::make('owner.name')
                                ->label('Owner')
                                ->placeholder('—'),

                            Infolists\Components\TextEntry::make('site.name')
                                ->label('Site')
                                ->placeholder('—'),

                            Infolists\Components\TextEntry::make('site.network.name')
                                ->label('Network')
                                ->placeholder('—'),

                            Infolists\Components\TextEntry::make('inventory.vnCategory.name_vi')
                                ->label('Loại biển')
                                ->placeholder('—'),

                            Infolists\Components\TextEntry::make('site.city')
                                ->label('Thành phố')
                                ->placeholder('—'),

                            Infolists\Components\TextEntry::make('updated_at')
                                ->label('Cập nhật')
                                ->since(),
                        ]),

                    // ── Tab 2: Kỹ thuật & Giá ──────────────────────────────
                    Infolists\Components\Tabs\Tab::make('Kỹ thuật & Giá')
                        ->icon('heroicon-o-cog-6-tooth')
                        ->columns(4)
                        ->schema([
                            Infolists\Components\TextEntry::make('resolution')
                                ->label('Resolution')
                                ->getStateUsing(fn (Screen $r) => $r->spec
                                    ? "{$r->spec->width_px}×{$r->spec->height_px} px"
                                    : '—'),

                            Infolists\Components\TextEntry::make('physical_size')
                                ->label('Kích thước')
                                ->getStateUsing(fn (Screen $r) => $r->spec?->width_cm
                                    ? "{$r->spec->width_cm}×{$r->spec->height_cm} cm"
                                    : '—'),

                            Infolists\Components\TextEntry::make('content_types')
                                ->label('Content')
                                ->getStateUsing(fn (Screen $r) => implode(', ', array_filter([
                                    $r->spec?->allow_image ? 'Image' : null,
                                    $r->spec?->allow_video ? 'Video' : null,
                                ])) ?: '—'),

                            Infolists\Components\TextEntry::make('inventory.spot_length')
                                ->label('Spot')
                                ->formatStateUsing(fn ($state) => ($state ?? 15) . 's'),

                            Infolists\Components\TextEntry::make('floor_cpm_display')
                                ->label('Floor CPM')
                                ->getStateUsing(fn (Screen $r) => $r->inventory?->floor_cpm
                                    ? number_format((float) $r->inventory->floor_cpm, 0, '.', ',') . ' ' . ($r->inventory->floor_cpm_currency ?? 'VND')
                                    : '—'),

                            Infolists\Components\TextEntry::make('inventory.weekly_impressions')
                                ->label('Impressions/tuần')
                                ->formatStateUsing(fn ($state) => $state ? number_format((int) $state) : '—'),

                            Infolists\Components\TextEntry::make('programmatic_display')
                                ->label('Programmatic')
                                ->badge()
                                ->getStateUsing(fn (Screen $r) => $r->inventory?->programmatic_enabled ? 'Enabled' : 'Disabled')
                                ->color(fn ($state) => $state === 'Enabled' ? 'success' : 'gray'),

                            Infolists\Components\TextEntry::make('inventory.timezone')
                                ->label('Timezone')
                                ->placeholder('—'),
                        ]),

                    // ── Tab 3: Hình ảnh ─────────────────────────────────────
                    Infolists\Components\Tabs\Tab::make('Hình ảnh')
                        ->icon('heroicon-o-photo')
                        ->schema([
                            Infolists\Components\ImageEntry::make('spec.photos')
                                ->label('')
                                ->disk('public')
                                ->height(200)
                                ->stacked(false)
                                ->visible(fn (Screen $r) => ! empty($r->spec?->photos)),

                            Infolists\Components\TextEntry::make('no_photos')
                                ->label('')
                                ->getStateUsing(fn () => 'Chưa có hình ảnh.')
                                ->visible(fn (Screen $r) => empty($r->spec?->photos)),
                        ]),

                    // ── Tab 4: Lịch hoạt động ───────────────────────────────
                    Infolists\Components\Tabs\Tab::make('Lịch hoạt động')
                        ->icon('heroicon-o-clock')
                        ->schema([
                            Infolists\Components\ViewEntry::make('operating_hours_grid')
                                ->label('')
                                ->view('filament.publisher.components.operating-hours-view')
                                ->getStateUsing(fn (Screen $r) => $r->inventory?->operating_hours ?? []),
                        ]),

                    // ── Tab 6: AdOps (admin only) ───────────────────────────
                    $showAdOps ? Infolists\Components\Tabs\Tab::make('AdOps')
                        ->icon('heroicon-o-wrench-screwdriver')
                        ->columns(3)
                        ->schema([
                            Infolists\Components\TextEntry::make('uuid')
                                ->label('Ad request UUID')
                                ->copyable()
                                ->fontFamily('mono')
                                ->placeholder('—'),

                            Infolists\Components\TextEntry::make('unit_id')
                                ->label('Unit ID')
                                ->placeholder('—'),

                            Infolists\Components\TextEntry::make('player_type')
                                ->label('Player type')
                                ->formatStateUsing(fn ($state) => match ($state) {
                                    'adtrue_android' => 'OOHX Android',
                                    'adtrue_webview' => 'OOHX WebView',
                                    'third_party'    => '3rd Party',
                                    'vast_only'      => 'VAST Only',
                                    default          => $state ?? '—',
                                }),

                            Infolists\Components\TextEntry::make('share_of_voice')
                                ->label('Max SOV')
                                ->getStateUsing(fn (Screen $r) => ($r->inventory?->share_of_voice_max_pct ?? 100) . '%'),

                            Infolists\Components\TextEntry::make('frequency_cap_display')
                                ->label('Frequency cap')
                                ->getStateUsing(fn (Screen $r) => $r->inventory?->frequency_cap
                                    ? $r->inventory->frequency_cap . 's'
                                    : 'Unlimited'),

                            Infolists\Components\TextEntry::make('internal_notes')
                                ->label('Ghi chú nội bộ')
                                ->placeholder('—')
                                ->columnSpanFull(),
                        ]) : null,

                ])),

            // ── Section: Vị trí & Map (ngoài tabs) ──────────────────────────
            Infolists\Components\Section::make('Vị trí')
                ->icon('heroicon-o-map-pin')
                ->collapsible()
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('site.address')
                        ->label('Địa chỉ')
                        ->placeholder('—')
                        ->columnSpan(2),

                    Infolists\Components\TextEntry::make('coordinates')
                        ->label('Tọa độ')
                        ->getStateUsing(fn (Screen $r) => $r->site?->lat
                            ? number_format((float) $r->site->lat, 7) . ', ' . number_format((float) $r->site->lon, 7)
                            : '—'),

                    Infolists\Components\ViewEntry::make('screen_map')
                        ->label('')
                        ->view('filament.components.screen-map')
                        ->columnSpanFull()
                        ->visible(fn (Screen $r) => $r->site?->lat !== null),
                ]),
        ]);
    }
}
