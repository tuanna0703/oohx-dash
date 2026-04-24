<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OohxEstimateResource\Pages;
use App\Models\Oohx\ScreenEstimate;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Read-only browser cho output.screen_traffic_estimates (Data Engine VPS).
 *
 * User `oohx_readonly` không có quyền INSERT/UPDATE/DELETE → disable tất cả
 * mutation actions. Data được precompute bởi Data Engine cron; Filament chỉ
 * list + view + filter.
 *
 * Query qua SSH tunnel port 5433 → latency ~50ms per request, pagination 25
 * vẫn mượt với dataset hiện tại (~100 rows).
 */
class OohxEstimateResource extends Resource
{
    protected static ?string $model = ScreenEstimate::class;

    protected static ?string $navigationIcon  = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'System Settings';
    protected static ?string $navigationLabel = 'Traffic Estimates';
    protected static ?string $modelLabel      = 'Traffic Estimate';
    protected static ?int    $navigationSort  = 55;

    public static function canCreate(): bool { return false; }
    public static function canEdit($r): bool { return false; }
    public static function canDelete($r): bool { return false; }

    protected static function hasViewPage(): bool { return true; }

    public static function form(Form $form): Form
    {
        return $form->schema([]); // no form — read-only
    }

    public static function getEloquentQuery(): Builder
    {
        // Eager-load screen + contextMetrics (Phase 2.D factor display) — tránh N+1
        return parent::getEloquentQuery()->with(['screen', 'contextMetrics']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('screen.external_id')
                    ->label('UUID')
                    ->formatStateUsing(fn (?string $state) => $state ? mb_substr($state, 0, 8) . '…' : '—')
                    ->copyable()
                    ->copyableState(fn (?string $state) => $state)
                    ->copyMessage('Đã copy UUID')
                    ->searchable(query: function (Builder $q, string $search): Builder {
                        return $q->whereHas('screen', fn ($q) => $q->where('external_id', 'ilike', "%{$search}%"));
                    })
                    ->tooltip(fn (?string $state) => $state),

                Tables\Columns\TextColumn::make('screen.name')
                    ->label('Screen')
                    ->searchable(query: function (Builder $q, string $search): Builder {
                        return $q->whereHas('screen', fn ($q) => $q->where('name', 'ilike', "%{$search}%"));
                    })
                    ->wrap()
                    ->limit(50),

                Tables\Columns\TextColumn::make('screen.city')
                    ->label('City')
                    ->badge()
                    ->sortable(query: function (Builder $q, string $direction): Builder {
                        return $q->join('core.screens as s', 's.id', '=', 'output.screen_traffic_estimates.screen_id')
                                 ->orderBy('s.city', $direction)
                                 ->select('output.screen_traffic_estimates.*');
                    }),

                Tables\Columns\TextColumn::make('screen.indoor_outdoor')
                    ->label('I/O')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'indoor'  => 'info',
                        'outdoor' => 'warning',
                        default   => 'gray',
                    }),

                Tables\Columns\TextColumn::make('screen.zone_type')
                    ->label('Zone')
                    ->toggleable(),

                // Phase 3.A Part 2 + 4.2.1 — data completeness 3-tier badge
                Tables\Columns\TextColumn::make('data_completeness')
                    ->label('Data')
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->contextMetrics
                        ? $record->contextMetrics->completeness_badge_label
                        : 'No metrics')
                    ->color(fn ($record) => $record->contextMetrics?->completeness_badge_color ?? 'gray')
                    ->tooltip(fn ($record) => $record->contextMetrics
                        ? ($record->contextMetrics->has_complete_data
                            ? 'Complete: road + POI + population resolved'
                            : implode(' · ', $record->contextMetrics->missing_data_reasons))
                        : 'No context metrics yet — screen chưa được enrich')
                    ->toggleable(),

                // Phase 4.2.1 — population density (toggleable, hidden by default)
                Tables\Columns\TextColumn::make('contextMetrics.population_density_300m')
                    ->label('Pop density (300m)')
                    ->numeric(decimalPlaces: 0, thousandsSeparator: ',')
                    ->suffix(' /km²')
                    ->placeholder('—')
                    ->color(fn (?float $state) => match (true) {
                        $state === null  => 'gray',
                        $state >= 30000  => 'success',  // dense urban
                        $state >= 10000  => 'info',     // residential
                        default          => 'warning',  // suburban / rural
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                // Phase 4.2.2 — venue footfall source (toggleable, hidden by default)
                Tables\Columns\TextColumn::make('contextMetrics.venue_footfall_source')
                    ->label('VF source')
                    ->badge()
                    ->color(fn ($record) => $record->contextMetrics?->venue_footfall_source_color ?? 'gray')
                    ->placeholder('—')
                    ->tooltip(fn ($record) => $record->contextMetrics?->venue_footfall_updated_at
                        ? 'Fetched ' . $record->contextMetrics->venue_footfall_updated_at->diffForHumans()
                            . ($record->contextMetrics->venue_footfall_is_stale ? ' — STALE' : '')
                        : 'No data')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('estimated_daily_impressions')
                    ->label('Daily imps')
                    ->numeric(decimalPlaces: 0, thousandsSeparator: ',')
                    ->sortable()
                    ->color(fn (?float $state) => match (true) {
                        $state === null    => 'gray',
                        $state >= 10000    => 'success',
                        $state >= 1000     => 'info',
                        default            => 'gray',
                    }),

                Tables\Columns\TextColumn::make('estimated_monthly_impressions')
                    ->label('Monthly imps')
                    ->numeric(decimalPlaces: 0, thousandsSeparator: ',')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('estimated_daily_ots')
                    ->label('Daily OTS')
                    ->numeric(decimalPlaces: 0, thousandsSeparator: ',')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('confidence_score')
                    ->label('Confidence')
                    ->badge()
                    ->formatStateUsing(fn (?float $state) => $state !== null
                        ? number_format($state, 2) . ' · ' . self::tierLabel($state)
                        : '—')
                    ->color(fn (?float $state) => match (true) {
                        $state === null => 'gray',
                        $state >= 0.7   => 'success',
                        $state >= 0.5   => 'warning',
                        default         => 'danger',
                    })
                    ->sortable(),

                // Phase 2.D — contextual factors từ metrics.screen_context_metrics
                Tables\Columns\TextColumn::make('contextMetrics.weather_factor')
                    ->label('Weather')
                    ->badge()
                    ->formatStateUsing(fn (?float $state) => $state !== null
                        ? number_format($state, 2)
                        : '—')
                    ->color(fn (?float $state) => match (true) {
                        $state === null => 'gray',
                        $state < 0.86   => 'danger',
                        $state < 0.96   => 'warning',
                        default         => 'success',
                    })
                    ->tooltip('weather_factor — 0.70-1.00, < 1 khi mưa/thời tiết xấu')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('contextMetrics.seasonality_factor')
                    ->label('Seasonality')
                    ->badge()
                    ->formatStateUsing(fn (?float $state) => $state !== null
                        ? number_format($state, 2)
                        : '—')
                    ->color(fn (?float $state) => match (true) {
                        $state === null => 'gray',
                        $state > 1.05   => 'success',
                        $state < 0.95   => 'danger',
                        default         => 'warning',
                    })
                    ->tooltip('seasonality_factor — >1 peak (summer), <1 low (Tet)')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('estimation_method')
                    ->label('Method')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('model_version')
                    ->label('Model')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('last_calculated_at')
                    ->label('Calculated')
                    ->dateTime()
                    ->since()
                    ->sortable()
                    ->color(fn ($state) => $state && now()->diffInHours($state) > 24 ? 'warning' : 'gray'),
            ])
            ->defaultSort('estimated_daily_impressions', 'desc')
            ->filters([
                SelectFilter::make('city')
                    ->label('City')
                    ->options(fn () => \App\Models\Oohx\Screen::query()
                        ->whereNotNull('city')
                        ->distinct()
                        ->orderBy('city')
                        ->pluck('city', 'city')
                        ->toArray())
                    ->query(fn (Builder $q, array $data) => ! empty($data['value'])
                        ? $q->whereHas('screen', fn ($q) => $q->where('city', $data['value']))
                        : $q),

                SelectFilter::make('indoor_outdoor')
                    ->label('Indoor / Outdoor')
                    ->options([
                        'indoor'  => 'Indoor',
                        'outdoor' => 'Outdoor',
                    ])
                    ->query(fn (Builder $q, array $data) => ! empty($data['value'])
                        ? $q->whereHas('screen', fn ($q) => $q->where('indoor_outdoor', $data['value']))
                        : $q),

                SelectFilter::make('confidence_tier')
                    ->label('Confidence tier')
                    ->options([
                        'high' => 'High (≥ 0.7)',
                        'mid'  => 'Medium (0.5 – 0.7)',
                        'low'  => 'Low (< 0.5)',
                    ])
                    ->query(fn (Builder $q, array $data) => match ($data['value'] ?? null) {
                        'high'  => $q->where('confidence_score', '>=', 0.7),
                        'mid'   => $q->whereBetween('confidence_score', [0.5, 0.7])->where('confidence_score', '<', 0.7),
                        'low'   => $q->where('confidence_score', '<', 0.5),
                        default => $q,
                    }),

                SelectFilter::make('estimation_method')
                    ->label('Method')
                    ->options(fn () => ScreenEstimate::query()
                        ->whereNotNull('estimation_method')
                        ->distinct()
                        ->pluck('estimation_method', 'estimation_method')
                        ->toArray()),

                TernaryFilter::make('stale')
                    ->label('Data freshness')
                    ->placeholder('All')
                    ->trueLabel('Stale (> 24h)')
                    ->falseLabel('Fresh (≤ 24h)')
                    ->queries(
                        true:  fn (Builder $q) => $q->where('last_calculated_at', '<', now()->subDay()),
                        false: fn (Builder $q) => $q->where('last_calculated_at', '>=', now()->subDay()),
                    ),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // No bulk actions — read-only. CSV export có thể add sau nếu cần.
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            // ── Screen context ────────────────────────────────────────────
            Infolists\Components\Section::make('Screen')
                ->icon('heroicon-o-tv')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('screen.external_id')
                        ->label('External ID (UUID)')
                        ->copyable()
                        ->columnSpan(2),
                    Infolists\Components\TextEntry::make('screen.name')
                        ->label('Name')
                        ->columnSpan(1),
                    Infolists\Components\TextEntry::make('screen.city')
                        ->label('City')
                        ->badge(),
                    Infolists\Components\TextEntry::make('screen.indoor_outdoor')
                        ->label('Indoor / Outdoor')
                        ->badge()
                        ->color(fn (?string $state) => match ($state) {
                            'indoor'  => 'info',
                            'outdoor' => 'warning',
                            default   => 'gray',
                        }),
                    Infolists\Components\TextEntry::make('screen.zone_type')
                        ->label('Zone')
                        ->placeholder('—'),
                ]),

            // ── Daily metrics ─────────────────────────────────────────────
            Infolists\Components\Section::make('Daily metrics')
                ->icon('heroicon-o-calendar-days')
                ->columns(5)
                ->schema([
                    Infolists\Components\TextEntry::make('estimated_daily_passby')
                        ->label('Passby')
                        ->numeric()
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('estimated_daily_screen_flow')
                        ->label('Screen flow')
                        ->numeric()
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('estimated_daily_ots')
                        ->label('OTS')
                        ->numeric()
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('estimated_daily_impressions')
                        ->label('Impressions')
                        ->numeric()
                        ->weight('bold')
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('estimated_daily_reach')
                        ->label('Reach')
                        ->numeric()
                        ->placeholder('—'),
                ]),

            // ── Aggregated metrics ────────────────────────────────────────
            Infolists\Components\Section::make('Weekly & Monthly')
                ->icon('heroicon-o-chart-bar-square')
                ->columns(4)
                ->schema([
                    Infolists\Components\TextEntry::make('estimated_weekly_impressions')
                        ->label('Weekly imps')
                        ->numeric()
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('estimated_monthly_impressions')
                        ->label('Monthly imps')
                        ->numeric()
                        ->weight('bold')
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('estimated_weekly_reach')
                        ->label('Weekly reach')
                        ->numeric()
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('estimated_monthly_reach')
                        ->label('Monthly reach')
                        ->numeric()
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('estimated_frequency')
                        ->label('Frequency')
                        ->numeric(decimalPlaces: 2)
                        ->placeholder('—')
                        ->helperText('Impressions per reach'),
                    Infolists\Components\TextEntry::make('impression_multiplier')
                        ->label('Multiplier')
                        ->numeric(decimalPlaces: 3)
                        ->placeholder('—')
                        ->helperText('Visibility × share_of_voice'),
                ]),

            // ── Quality & meta ────────────────────────────────────────────
            Infolists\Components\Section::make('Quality & meta')
                ->icon('heroicon-o-shield-check')
                ->columns(4)
                ->schema([
                    Infolists\Components\TextEntry::make('confidence_score')
                        ->label('Confidence')
                        ->badge()
                        ->formatStateUsing(fn (?float $state) => $state !== null
                            ? number_format($state, 2) . ' · ' . self::tierLabel($state)
                            : '—')
                        ->color(fn (?float $state) => match (true) {
                            $state === null => 'gray',
                            $state >= 0.7   => 'success',
                            $state >= 0.5   => 'warning',
                            default         => 'danger',
                        }),
                    Infolists\Components\TextEntry::make('estimation_method')
                        ->label('Method')
                        ->badge()
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('model_version')
                        ->label('Model version')
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('last_calculated_at')
                        ->label('Last calculated')
                        ->dateTime()
                        ->since()
                        ->placeholder('—'),
                    Infolists\Components\TextEntry::make('estimated_cpm')
                        ->label('Estimated CPM')
                        ->money('VND')
                        ->placeholder('—')
                        ->columnSpan(2),
                ]),

            // Phase 3.A Part 2 + Phase 4.2.1 — Data completeness 3-source check
            Infolists\Components\Section::make('Data completeness')
                ->icon('heroicon-o-shield-exclamation')
                ->description('3-source check: road + POI + population (HRSL). Warning hiện khi thiếu source nào.')
                ->columns(4)
                ->visible(fn ($record) => $record->contextMetrics !== null)
                ->schema([
                    Infolists\Components\TextEntry::make('contextMetrics.completeness_badge_label')
                        ->label('Status')
                        ->badge()
                        ->color(fn ($record) => $record->contextMetrics?->completeness_badge_color ?? 'gray'),

                    Infolists\Components\TextEntry::make('contextMetrics.nearest_road_id')
                        ->label('Nearest road ID')
                        ->placeholder('—')
                        ->color(fn ($state) => $state === null ? 'danger' : null),

                    Infolists\Components\TextEntry::make('contextMetrics.poi_count_300m')
                        ->label('POI count (300m)')
                        ->numeric()
                        ->placeholder('0')
                        ->color(fn ($state) => ($state ?? 0) === 0 ? 'danger' : null),

                    Infolists\Components\TextEntry::make('contextMetrics.population_density_300m')
                        ->label('Population (300m)')
                        ->numeric(decimalPlaces: 0)
                        ->suffix(' /km²')
                        ->placeholder('— (HRSL chưa cover)')
                        ->color(fn ($state) => match (true) {
                            $state === null => 'danger',
                            $state >= 30000 => 'success',
                            $state >= 10000 => 'info',
                            default         => 'warning',
                        })
                        ->helperText(fn ($state) => match (true) {
                            $state === null => null,
                            $state >= 30000 => 'Dense urban',
                            $state >= 10000 => 'Residential',
                            default         => 'Suburban / rural',
                        }),

                    Infolists\Components\TextEntry::make('missing_reasons')
                        ->label('Issues')
                        ->getStateUsing(fn ($record) => implode(' · ', $record->contextMetrics?->missing_data_reasons ?? []) ?: '—')
                        ->columnSpanFull()
                        ->visible(fn ($record) => ! ($record->contextMetrics?->has_complete_data ?? true)),
                ]),

            // Phase 4.2.2 — Venue Footfall multi-provider (handoff §3.4)
            Infolists\Components\Section::make('Venue Footfall')
                ->icon('heroicon-o-map-pin')
                ->description('Signal + source tracking. Source winner được chọn bởi priority chain trên DE (Foursquare > OSM fallback).')
                ->columns(3)
                ->visible(fn ($record) => $record->contextMetrics !== null)
                ->schema([
                    Infolists\Components\TextEntry::make('contextMetrics.venue_footfall')
                        ->label('Signal (footfall proxy)')
                        ->numeric(decimalPlaces: 2)
                        ->placeholder('— (chưa fetch)')
                        ->suffixAction(
                            Infolists\Components\Actions\Action::make('stale')
                                ->icon('heroicon-o-exclamation-triangle')
                                ->color('danger')
                                ->tooltip('Stale — updated > 45 ngày')
                                ->visible(fn ($record) => $record->contextMetrics?->venue_footfall_is_stale ?? false)
                        ),

                    Infolists\Components\TextEntry::make('contextMetrics.venue_footfall_source')
                        ->label('Source')
                        ->badge()
                        ->color(fn ($record) => $record->contextMetrics?->venue_footfall_source_color ?? 'gray')
                        ->placeholder('—')
                        ->helperText(fn ($record) => match ($record->contextMetrics?->venue_footfall_source) {
                            'foursquare' => 'Primary — paid API',
                            'google'     => 'Premium — activated',
                            'osm'        => 'Fallback — POI count proxy',
                            'foody', 'grab', 'momo' => 'Partnership provider',
                            default      => null,
                        }),

                    Infolists\Components\TextEntry::make('contextMetrics.venue_footfall_updated_at')
                        ->label('Last fetched')
                        ->dateTime()
                        ->since()
                        ->placeholder('never')
                        ->color(fn ($record) => $record->contextMetrics?->venue_footfall_is_stale ? 'danger' : 'gray'),
                ]),

            // Phase 2.D — Contextual factors applied bởi formula
            Infolists\Components\Section::make('Contextual factors (Phase 2.D)')
                ->icon('heroicon-o-sparkles')
                ->description('Weather/seasonality/calibration multiplier apply vào passby (outdoor) hoặc screen_flow (indoor, sensitivity 0.3).')
                ->columns(3)
                ->visible(fn ($record) => $record->contextMetrics !== null)
                ->schema([
                    Infolists\Components\TextEntry::make('contextMetrics.weather_factor')
                        ->label('Weather factor')
                        ->badge()
                        ->formatStateUsing(fn (?float $state) => $state !== null
                            ? number_format($state, 3)
                            : '— (no recent weather data)')
                        ->color(fn (?float $state) => match (true) {
                            $state === null => 'gray',
                            $state < 0.86   => 'danger',
                            $state < 0.96   => 'warning',
                            default         => 'success',
                        })
                        ->helperText('0.70-1.00, < 1 khi mưa/thời tiết xấu. NULL nếu weather snapshot > 6h'),

                    Infolists\Components\TextEntry::make('contextMetrics.seasonality_factor')
                        ->label('Seasonality factor')
                        ->badge()
                        ->formatStateUsing(fn (?float $state) => $state !== null
                            ? number_format($state, 3)
                            : '— (city not seeded)')
                        ->color(fn (?float $state) => match (true) {
                            $state === null => 'gray',
                            $state > 1.05   => 'success',
                            $state < 0.95   => 'danger',
                            default         => 'warning',
                        })
                        ->helperText('> 1 peak season · < 1 low (Tet) · theo city + month hiện tại'),

                    Infolists\Components\TextEntry::make('contextMetrics.calibration_factor')
                        ->label('Calibration factor')
                        ->badge()
                        ->placeholder('—')
                        ->color('gray')
                        ->helperText('(Phase 3 — hiện NULL → estimator dùng 1.0)'),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOohxEstimates::route('/'),
            'view'  => Pages\ViewOohxEstimate::route('/{record}'),
        ];
    }

    /**
     * Navigation badge — số estimates có confidence thấp (cần chú ý).
     * Silent fail khi tunnel down để tránh crash navbar.
     */
    public static function getNavigationBadge(): ?string
    {
        try {
            $count = ScreenEstimate::query()->where('confidence_score', '<', 0.5)->count();
            return $count > 0 ? (string) $count : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Số estimates có confidence < 0.5 (cần review)';
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private static function tierLabel(?float $score): string
    {
        if ($score === null)  return '—';
        if ($score >= 0.7)    return 'high';
        if ($score >= 0.5)    return 'mid';
        return 'low';
    }
}
