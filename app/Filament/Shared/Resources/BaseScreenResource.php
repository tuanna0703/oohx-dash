<?php

namespace App\Filament\Shared\Resources;

use App\Models\Network;
use App\Models\Screen;
use App\Models\Site;
use App\Models\VenueType;
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
use Illuminate\Support\Facades\DB;

/**
 * Lớp base canonical cho Screens.
 * Admin và Publisher chỉ override phần khác biệt (phân quyền, scoping, dropdown options).
 */
abstract class BaseScreenResource extends Resource
{
    protected static ?string $model           = Screen::class;
    protected static ?string $navigationIcon  = null;
    protected static ?string $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Screens';
    protected static ?int    $navigationSort  = 2;

    // ── Hooks cho subclass ────────────────────────────────────────────────────

    /**
     * Kiểm tra quyền pricing.
     * Admin: luôn true. Publisher: TenantPermission::check('manage_pricing').
     */
    protected static function canPricing(): bool
    {
        return true;
    }

    /**
     * Options cho site_id Select trong form.
     * Admin: tất cả sites. Publisher: chỉ sites của tenant.
     */
    protected static function siteFormOptions(): array
    {
        return Site::orderBy('name')->pluck('name', 'id')->toArray();
    }

    /**
     * Options cho inventory.network_id Select trong form.
     * Admin: tất cả networks. Publisher: chỉ networks của tenant.
     */
    protected static function networkFormOptions(): array
    {
        return Network::orderBy('name')->pluck('name', 'id')->toArray();
    }

    /**
     * Options cho Site filter trong table.
     * Admin: tất cả sites. Publisher: chỉ sites của tenant (cached).
     */
    protected static function siteFilterOptions(): array
    {
        return cache()->remember('filter_sites_all', 300,
            fn() => Site::orderBy('name')->pluck('name', 'id')->toArray()
        );
    }

    /**
     * Options cho Network filter trong table.
     * Admin: tất cả networks. Publisher: chỉ networks của tenant (cached).
     */
    protected static function networkFilterOptions(): array
    {
        return cache()->remember('filter_networks_all', 300,
            fn() => Network::orderBy('name')->pluck('name', 'id')->toArray()
        );
    }

    /** Admin thêm cột owner vào bảng. */
    protected static function additionalTableColumns(): array
    {
        return [];
    }

    /** Admin thêm owner filter vào bảng. */
    protected static function additionalFilters(): array
    {
        return [];
    }

    // ── Form ─────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        $canPricing = static::canPricing();

        return $form->schema([

            // ══════════════════════════════════════════════════════════════════
            // SECTION 1 — Basic Info
            // ══════════════════════════════════════════════════════════════════
            Forms\Components\Section::make()->schema([

                Forms\Components\TextInput::make('external_id')
                    ->label('Screen ID')
                    ->required()->maxLength(75)
                    ->helperText('e.g. GUARD-HOR-IND-001'),

                Forms\Components\TextInput::make('unit_id')
                    ->label('Unit ID')
                    ->maxLength(100)
                    ->nullable(),

                Forms\Components\TextInput::make('name')
                    ->label('Name')
                    ->required()->maxLength(199),

                Forms\Components\Textarea::make('internal_notes')
                    ->label('Internal notes')
                    ->rows(2),

                Forms\Components\Select::make('spec.facing_direction')
                    ->label('Facing direction')
                    ->placeholder('Select facing direction')
                    ->options([
                        'N'        => 'North',   'NE' => 'North East',
                        'E'        => 'East',    'SE' => 'South East',
                        'S'        => 'South',   'SW' => 'South West',
                        'W'        => 'West',    'NW' => 'North West',
                        'Rotating' => 'Rotating',
                    ])->nullable(),

                Forms\Components\Select::make('site_id')
                    ->label('Site')
                    ->required()->placeholder('Select a site')
                    ->options(fn() => static::siteFormOptions())
                    ->searchable(),

                Forms\Components\TextInput::make('inventory.share_of_voice_max_pct')
                    ->label('Max share of voice (%)')
                    ->numeric()->default(100)->minValue(1)->maxValue(100)
                    ->suffix('%')
                    ->helperText('Phần trăm tối đa của vòng lặp dành cho một advertiser. Mặc định 100%.')
                    ->hint('ⓘ')->hintColor('primary'),

                Forms\Components\Select::make('inventory.network_id')
                    ->label('Network')
                    ->required()->placeholder('Select a network')
                    ->options(fn() => static::networkFormOptions())
                    ->searchable(),

                Forms\Components\Select::make('inventory.venue_type')
                    ->label('Venue type')
                    ->placeholder('Select a media type or leave empty')
                    ->options(fn() => VenueType::forSelect()
                        ->get()->groupBy('category')
                        ->map(fn($items) => $items->pluck('venue_type', 'venue_type'))
                        ->toArray())
                    ->searchable(),

                Forms\Components\TextInput::make('inventory.floor_cpm')
                    ->label('Floor CPM')
                    ->numeric()->prefix('$')->default(10.00)
                    ->hint('ⓘ')->hintColor('primary')
                    ->visible($canPricing),

                Forms\Components\TextInput::make('inventory.screen_count_override')
                    ->label('Screen count override')
                    ->numeric()->minValue(1)->maxValue(999)->nullable()
                    ->placeholder('1 (mặc định)')
                    ->helperText('Override số màn hình tại location này — dùng để nhân hệ số impression. Để trống = 1.')
                    ->hint('ⓘ')->hintColor('primary')
                    ->visible($canPricing),

                Forms\Components\TextInput::make('uuid')
                    ->label('Ad request API UUID')
                    ->helperText('Auto-generated if empty. Player uses this for ad requests.')
                    ->maxLength(255),

            ])->columns(1),

            // ══════════════════════════════════════════════════════════════════
            // SECTION 2 — Player integration
            // ══════════════════════════════════════════════════════════════════
            Forms\Components\Section::make('Player integration')
                ->icon('heroicon-o-play-circle')
                ->collapsed()
                ->schema([
                    Forms\Components\Select::make('player_type')
                        ->label('Player type')
                        ->options([
                            'adtrue_android' => 'AdTRUE Android',
                            'adtrue_webview' => 'AdTRUE WebView',
                            'third_party'    => '3rd Party Player',
                            'vast_only'      => 'VAST Only',
                        ])->default('adtrue_android'),

                    Forms\Components\Toggle::make('active')
                        ->label('Enabled')
                        ->default(true),
                ])->columns(2),

            // ══════════════════════════════════════════════════════════════════
            // SECTION 3 — Screen photo
            // ══════════════════════════════════════════════════════════════════
            Forms\Components\Section::make('Screen photo')
                ->icon('heroicon-o-photo')
                ->collapsed()
                ->schema([
                    Forms\Components\FileUpload::make('spec.photo_url')
                        ->label('')
                        ->image()
                        ->disk('public')
                        ->directory('screen-photos')
                        ->imagePreviewHeight('160')
                        ->uploadButtonPosition('center')
                        ->uploadProgressIndicatorPosition('center'),
                ]),

            // ══════════════════════════════════════════════════════════════════
            // SECTION 4 — Screen resolution
            // ══════════════════════════════════════════════════════════════════
            Forms\Components\Section::make('Screen resolution')
                ->icon('heroicon-o-squares-2x2')
                ->schema([
                    Forms\Components\View::make('filament.publisher.components.resolution-picker')
                        ->columnSpan('full'),

                    Forms\Components\Hidden::make('spec.width_px')->default(1920),
                    Forms\Components\Hidden::make('spec.height_px')->default(1080),
                    Forms\Components\Hidden::make('spec.resolution_preset')->default('1920x1080'),

                    Forms\Components\Grid::make(4)->schema([
                        Forms\Components\TextInput::make('spec.width_cm')
                            ->label('Screen width')->numeric(),
                        Forms\Components\Select::make('spec.width_unit')
                            ->label('Unit')->options(['cm' => 'cm', 'in' => 'in'])->default('cm'),
                        Forms\Components\TextInput::make('spec.height_cm')
                            ->label('Screen height')->numeric(),
                        Forms\Components\Select::make('spec.height_unit')
                            ->label('Unit')->options(['cm' => 'cm', 'in' => 'in'])->default('cm'),
                    ]),
                ]),

            // ══════════════════════════════════════════════════════════════════
            // SECTION 5 — Selective listing
            // ══════════════════════════════════════════════════════════════════
            Forms\Components\Section::make('Selective listing')
                ->visible($canPricing)
                ->schema([
                    Forms\Components\View::make('filament.publisher.components.selective-listing-picker')
                        ->columnSpan('full'),
                    Forms\Components\Hidden::make('inventory.programmatic_enabled')->default(false),
                    Forms\Components\Hidden::make('inventory.pmp_only')->default(false),
                    Forms\Components\Hidden::make('inventory.ad_server_enabled')->default(true),
                    Forms\Components\Hidden::make('inventory.deals_enabled')->default(true),
                ]),

            // ══════════════════════════════════════════════════════════════════
            // SECTION 6 — Playback
            // ══════════════════════════════════════════════════════════════════
            Forms\Components\Section::make('Playback')
                ->icon('heroicon-o-play')
                ->schema([
                    Forms\Components\TextInput::make('inventory.spot_length')
                        ->label('Default ad duration')
                        ->numeric()->default(15)->suffix('seconds'),

                    Forms\Components\Toggle::make('allow_different_durations')
                        ->label('Allow playback of ads of different durations')
                        ->default(false)->dehydrated(false),

                    Forms\Components\View::make('filament.publisher.components.allowed-content-picker')
                        ->columnSpan('full'),

                    Forms\Components\Hidden::make('spec.allow_image')->default(true),
                    Forms\Components\Hidden::make('spec.allow_video')->default(true),
                    Forms\Components\Hidden::make('spec.allow_html')->default(false),
                    Forms\Components\Hidden::make('spec.allow_zip')->default(false),

                    Forms\Components\TextInput::make('inventory.max_spot_length')
                        ->label('Max spot duration')
                        ->numeric()->default(180)->suffix('seconds'),

                    Forms\Components\TextInput::make('inventory.min_spot_length')
                        ->label('Min spot duration')
                        ->numeric()->default(3)->suffix('seconds'),

                    Forms\Components\TextInput::make('inventory.loop_length')
                        ->label('Loop length')
                        ->numeric()->suffix('seconds'),

                    Forms\Components\TextInput::make('inventory.weekly_impressions')
                        ->label('Weekly impressions')
                        ->numeric(),

                ])->columns(1),

            // ══════════════════════════════════════════════════════════════════
            // SECTION 7 — Frequency cap
            // ══════════════════════════════════════════════════════════════════
            Forms\Components\Section::make('Frequency cap')
                ->collapsed()
                ->visible($canPricing)
                ->schema([
                    Forms\Components\TextInput::make('inventory.frequency_cap')
                        ->label('Advertiser frequency cap')
                        ->numeric()->default(0)->suffix('seconds')
                        ->helperText('0 = không giới hạn')
                        ->hint('ⓘ')->hintColor('primary'),

                    Forms\Components\TextInput::make('inventory.category_frequency_cap')
                        ->label('Category frequency cap')
                        ->numeric()->default(0)->suffix('seconds')
                        ->helperText('0 = không giới hạn')
                        ->hint('ⓘ')->hintColor('primary'),

                    Forms\Components\Toggle::make('inventory.strict_frequency_capping')
                        ->label('Strict frequency capping?')
                        ->default(false)
                        ->helperText('Từ chối ad nếu đã đạt frequency cap thay vì chờ')
                        ->hint('ⓘ')->hintColor('primary'),
                ])->columns(2),

            // ══════════════════════════════════════════════════════════════════
            // SECTION 8 — Screen location and geofence
            // ══════════════════════════════════════════════════════════════════
            Forms\Components\Section::make('Screen location and geofence')
                ->icon('heroicon-o-map-pin')
                ->schema([
                    Forms\Components\Placeholder::make('location_hint')
                        ->label('')
                        ->content('ⓘ Type in the latitude and longitude or use the pin on the map.'),

                    Forms\Components\Grid::make(3)->schema([
                        Forms\Components\TextInput::make('site.lat')
                            ->label('Latitude')
                            ->numeric()->step(0.0000001),

                        Forms\Components\TextInput::make('site.lon')
                            ->label('Longitude')
                            ->numeric()->step(0.0000001),

                        Forms\Components\Select::make('inventory.timezone')
                            ->label('Time zone')
                            ->options(fn() => collect(timezone_identifiers_list())
                                ->mapWithKeys(fn($tz) => [$tz => $tz]))
                            ->searchable()
                            ->default('Asia/Ho_Chi_Minh'),
                    ]),
                ]),

            // ══════════════════════════════════════════════════════════════════
            // SECTION 9 — Operating hours
            // ══════════════════════════════════════════════════════════════════
            Forms\Components\Section::make('Operating hours')
                ->icon('heroicon-o-clock')
                ->schema([
                    Forms\Components\View::make('filament.publisher.components.operating-hours-grid'),
                    Forms\Components\Hidden::make('inventory.operating_hours'),
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

                // ── Identity ──────────────────────────────────────────────────
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')->searchable()->sortable()->wrap()
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
                    ->label('Screen ID')->searchable()->copyable()->sortable(),

                Tables\Columns\TextColumn::make('unit_id')
                    ->label('Unit ID')->searchable()->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                // ── Status ────────────────────────────────────────────────────
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Device')
                    ->colors(['success' => 'online', 'danger' => 'offline', 'warning' => 'maintenance'])
                    ->formatStateUsing(fn($state) => ucfirst($state ?? 'unknown')),

                Tables\Columns\IconColumn::make('active')
                    ->label('Enabled')->boolean(),

                Tables\Columns\TextColumn::make('last_heartbeat_at')
                    ->label('Last ping')->dateTime('d/m/y H:i')->sortable()
                    ->toggleable(),

                // ── Location ──────────────────────────────────────────────────
                Tables\Columns\TextColumn::make('site.name')
                    ->label('Site')->sortable()->searchable()->toggleable(),

                Tables\Columns\TextColumn::make('network_name')
                    ->label('Network')
                    ->getStateUsing(fn(Screen $r) => $r->inventory?->network?->name ?? $r->inventory?->network_name ?? '—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('site_location')
                    ->label('City / Region')
                    ->getStateUsing(fn(Screen $r) => implode(', ', array_filter([
                        $r->site?->city, $r->site?->region,
                    ])) ?: '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('inventory.venue_type')
                    ->label('Venue type')->searchable()->toggleable(),

                Tables\Columns\TextColumn::make('inventory.timezone')
                    ->label('Timezone')->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                // ── Screen specs ──────────────────────────────────────────────
                Tables\Columns\TextColumn::make('resolution')
                    ->label('Resolution')
                    ->getStateUsing(fn(Screen $r) => $r->spec
                        ? "{$r->spec->width_px}×{$r->spec->height_px} px"
                        : '—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('physical_size')
                    ->label('Size')
                    ->getStateUsing(fn(Screen $r) => $r->spec?->width_cm
                        ? "{$r->spec->width_cm} × {$r->spec->height_cm} " . ($r->spec->width_unit ?? 'cm')
                        : '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('spec.facing_direction')
                    ->label('Facing')->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('allowed_content')
                    ->label('Content types')
                    ->getStateUsing(fn(Screen $r) => implode(', ', array_filter([
                        $r->spec?->allow_image ? 'Image' : null,
                        $r->spec?->allow_video ? 'Video' : null,
                        $r->spec?->allow_html  ? 'HTML'  : null,
                        $r->spec?->allow_zip   ? 'ZIP'   : null,
                        $r->spec?->allow_vast  ? 'VAST'  : null,
                    ])) ?: '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                // ── Player ────────────────────────────────────────────────────
                Tables\Columns\TextColumn::make('player_type')
                    ->label('Player')
                    ->formatStateUsing(fn($state) => match($state) {
                        'adtrue_android' => 'Android',
                        'adtrue_webview' => 'WebView',
                        'third_party'    => '3rd Party',
                        'vast_only'      => 'VAST Only',
                        default          => $state ?? '—',
                    })
                    ->badge()->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                // ── Inventory & Commercial ────────────────────────────────────
                Tables\Columns\TextColumn::make('inventory.spot_length')
                    ->label('Ad duration')
                    ->formatStateUsing(fn($state) => $state ? "{$state}s" : '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('inventory.weekly_impressions')
                    ->label('Weekly impr.')
                    ->formatStateUsing(fn($state) => $state ? number_format((int) $state) : '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('inventory.share_of_voice_max_pct')
                    ->label('Max SOV')
                    ->formatStateUsing(fn($state) => ($state ?? 100) . '%')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('inventory.floor_cpm')
                    ->label('Floor CPM')
                    ->formatStateUsing(fn($state, Screen $r) => $state
                        ? number_format((float) $state, 0, '.', ',') . ' ' . ($r->inventory?->floor_cpm_currency ?? 'VND')
                        : '—')
                    ->visible($canPricing)->toggleable(),

                Tables\Columns\IconColumn::make('inventory.programmatic_enabled')
                    ->label('RTB')->boolean()
                    ->visible($canPricing)->toggleable(),

                Tables\Columns\IconColumn::make('inventory.deals_enabled')
                    ->label('Deals')->boolean()
                    ->visible($canPricing)->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('inventory.ad_server_enabled')
                    ->label('Ad server')->boolean()
                    ->visible($canPricing)->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('inventory.screen_count_override')
                    ->label('Screen count')
                    ->formatStateUsing(fn($state) => $state > 1 ? $state : '—')
                    ->visible($canPricing)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Device status')
                    ->options(['online' => 'Online', 'offline' => 'Offline', 'maintenance' => 'Maintenance']),

                TernaryFilter::make('active')
                    ->label('Enabled'),

                Filter::make('online_now')
                    ->label('Online trong 5 phút')
                    ->query(fn(Builder $query) => $query
                        ->whereNotNull('last_heartbeat_at')
                        ->where('last_heartbeat_at', '>=', now()->subMinutes(5))
                    )
                    ->toggle(),

                SelectFilter::make('site_id')
                    ->label('Site')
                    ->options(fn() => static::siteFilterOptions())
                    ->searchable(),

                SelectFilter::make('network')
                    ->label('Network')
                    ->options(fn() => static::networkFilterOptions())
                    ->query(fn(Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn($q, $v) => $q->whereIn('id',
                            DB::table('screen_inventory')->where('network_id', $v)->pluck('screen_id')
                        )
                    ))
                    ->searchable(),

                SelectFilter::make('player_type')
                    ->label('Player')
                    ->options([
                        'adtrue_android' => 'AdTRUE Android',
                        'adtrue_webview' => 'AdTRUE WebView',
                        'third_party'    => '3rd Party',
                        'vast_only'      => 'VAST Only',
                    ]),

                SelectFilter::make('venue_type')
                    ->label('Venue Type')
                    ->options(fn() => cache()->remember('filter_venue_types', 600, fn() =>
                        VenueType::forSelect()->get()->pluck('venue_type', 'venue_type')->toArray()
                    ))
                    ->query(fn(Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn($q, $v) => $q->whereIn('id',
                            DB::table('screen_inventory')->where('venue_type', $v)->pluck('screen_id')
                        )
                    ))
                    ->searchable(),

                TernaryFilter::make('programmatic_enabled')
                    ->label('RTB enabled')
                    ->queries(
                        true:  fn(Builder $query) => $query->whereIn('id',
                            DB::table('screen_inventory')->where('programmatic_enabled', true)->pluck('screen_id')
                        ),
                        false: fn(Builder $query) => $query->whereIn('id',
                            DB::table('screen_inventory')->where('programmatic_enabled', false)->pluck('screen_id')
                        ),
                    ),

                ...static::additionalFilters(),
            ])
            ->filtersFormColumns(3)
            ->recordUrl(fn(Screen $r) => static::getUrl('view', ['record' => $r]))
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle_rtb')
                    ->label(fn(Screen $r) => $r->inventory?->programmatic_enabled ? 'Disable RTB' : 'Enable RTB')
                    ->icon('heroicon-o-signal')
                    ->color(fn(Screen $r) => $r->inventory?->programmatic_enabled ? 'danger' : 'success')
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
