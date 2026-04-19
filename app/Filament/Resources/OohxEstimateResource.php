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
        // Eager-load screen relation — used in nhiều columns để tránh N+1
        return parent::getEloquentQuery()->with('screen');
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
