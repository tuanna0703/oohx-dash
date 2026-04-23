<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OohxCampaignEstimateResource\Pages;
use App\Models\Oohx\CampaignEstimate;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Phase 4.1 — Campaign Planner (Branch A, handoff §5).
 *
 * History browser cho `output.campaign_estimates`. User tạo campaign qua
 * Create page (form → enqueue job → redirect to View khi worker done).
 *
 * Connection `oohx` readonly — không INSERT/UPDATE/DELETE trực tiếp. Write
 * routing qua JobOrchestrator::enqueueCampaign() → worker INSERT.
 *
 * Disclaimer (handoff §7.4): reach numbers là directional estimate, sẽ refine
 * khi Phase 4.2.1 ship real population density.
 */
class OohxCampaignEstimateResource extends Resource
{
    protected static ?string $model = CampaignEstimate::class;

    protected static ?string $navigationIcon  = 'heroicon-o-megaphone';
    protected static ?string $navigationGroup = 'OOHX · Data Engine';
    protected static ?string $navigationLabel = 'Campaign Planner';
    protected static ?string $modelLabel      = 'Campaign';
    protected static ?int    $navigationSort  = 60;

    public static function canEdit($r): bool { return false; }
    public static function canDelete($r): bool { return false; }

    public static function form(Form $form): Form
    {
        return $form->schema([]); // Form handled in Create page (screen picker)
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                Tables\Columns\TextColumn::make('campaign_name')
                    ->label('Campaign')
                    ->searchable()
                    ->wrap()
                    ->limit(40)
                    ->default('—'),

                Tables\Columns\TextColumn::make('screen_count')
                    ->label('Screens')
                    ->alignCenter()
                    ->getStateUsing(fn (CampaignEstimate $r) => $r->screen_count
                        . ($r->screens_missing_estimate
                            ? " ({$r->screens_missing_estimate} miss)"
                            : '')),

                Tables\Columns\TextColumn::make('duration_days')
                    ->label('Duration')
                    ->formatStateUsing(fn (?int $state) => $state ? "{$state} days" : '—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_impressions_for_duration')
                    ->label('Total imps')
                    ->numeric(decimalPlaces: 0, thousandsSeparator: ',')
                    ->sortable(),

                Tables\Columns\TextColumn::make('estimated_unique_reach')
                    ->label('Reach')
                    ->numeric(decimalPlaces: 0, thousandsSeparator: ',')
                    ->tooltip('Spatial dedup via ST_GeoHash(7). Approximate.')
                    ->sortable(),

                Tables\Columns\TextColumn::make('estimated_frequency')
                    ->label('Freq')
                    ->badge()
                    ->formatStateUsing(fn (?float $state) => $state !== null
                        ? number_format($state, 1) . '×'
                        : '—')
                    ->color(fn (CampaignEstimate $r) => $r->frequency_color)
                    ->tooltip(fn (CampaignEstimate $r) => $r->frequency_warning ?? 'Impressions per reached person')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_budget')
                    ->label('Budget')
                    ->money('VND')
                    ->default('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('estimated_cpm')
                    ->label('CPM')
                    ->money('VND')
                    ->default('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('avg_confidence')
                    ->label('Confidence')
                    ->badge()
                    ->formatStateUsing(fn (?float $state) => $state !== null
                        ? number_format($state, 2)
                        : '—')
                    ->color(fn (CampaignEstimate $r) => $r->confidence_color)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('formula_version_id')
                    ->label('Formula')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('computed_at')
                    ->label('Computed')
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('computed_at', 'desc')
            ->filters([
                SelectFilter::make('duration_bucket')
                    ->label('Duration')
                    ->options([
                        'short'  => '≤ 7 days',
                        'medium' => '8 – 30 days',
                        'long'   => '> 30 days',
                    ])
                    ->query(fn (Builder $q, array $data) => match ($data['value'] ?? null) {
                        'short'  => $q->where('duration_days', '<=', 7),
                        'medium' => $q->whereBetween('duration_days', [8, 30]),
                        'long'   => $q->where('duration_days', '>', 30),
                        default  => $q,
                    }),

                SelectFilter::make('confidence_tier')
                    ->label('Confidence')
                    ->options([
                        'high' => 'High (≥ 0.7)',
                        'mid'  => 'Medium (0.5 – 0.7)',
                        'low'  => 'Low (< 0.5)',
                    ])
                    ->query(fn (Builder $q, array $data) => match ($data['value'] ?? null) {
                        'high' => $q->where('avg_confidence', '>=', 0.7),
                        'mid'  => $q->whereBetween('avg_confidence', [0.5, 0.7])->where('avg_confidence', '<', 0.7),
                        'low'  => $q->where('avg_confidence', '<', 0.5),
                        default => $q,
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                // No bulk — read-only
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            // ── Campaign meta ─────────────────────────────────────────
            Infolists\Components\Section::make('Campaign')
                ->icon('heroicon-o-megaphone')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('id')
                        ->label('Campaign ID')
                        ->copyable(),
                    Infolists\Components\TextEntry::make('campaign_name')
                        ->label('Name')
                        ->default('—')
                        ->columnSpan(2),
                    Infolists\Components\TextEntry::make('duration_days')
                        ->label('Duration')
                        ->suffix(' days'),
                    Infolists\Components\TextEntry::make('screen_count')
                        ->label('Total screens')
                        ->getStateUsing(fn ($record) => $record->screen_count),
                    Infolists\Components\TextEntry::make('computed_at')
                        ->label('Computed')
                        ->dateTime()
                        ->since(),
                ]),

            // ── Warnings banner (handoff §6.2) ─────────────────────────
            Infolists\Components\Section::make('Warnings')
                ->icon('heroicon-o-exclamation-triangle')
                ->iconColor('warning')
                ->columns(1)
                ->visible(fn ($record) =>
                    ($record->screens_missing_estimate ?? 0) > 0
                    || ($record->estimated_frequency ?? 0) > 100
                    || ($record->avg_confidence !== null && $record->avg_confidence < 0.5))
                ->schema([
                    Infolists\Components\TextEntry::make('missing_screens_warning')
                        ->label('Missing data')
                        ->badge()
                        ->color('warning')
                        ->default('—')
                        ->visible(fn ($record) => ($record->screens_missing_estimate ?? 0) > 0),

                    Infolists\Components\TextEntry::make('frequency_warning')
                        ->label('Frequency')
                        ->badge()
                        ->color(fn ($record) => $record->frequency_color)
                        ->default('—')
                        ->visible(fn ($record) => ($record->estimated_frequency ?? 0) > 100),

                    Infolists\Components\TextEntry::make('avg_confidence_warning')
                        ->label('Low confidence')
                        ->badge()
                        ->color('danger')
                        ->getStateUsing(fn ($record) => $record->avg_confidence !== null && $record->avg_confidence < 0.5
                            ? "avg_confidence = " . number_format($record->avg_confidence, 2) . ' — data quality yếu'
                            : null)
                        ->visible(fn ($record) => $record->avg_confidence !== null && $record->avg_confidence < 0.5),
                ]),

            // ── Core metrics ──────────────────────────────────────────
            Infolists\Components\Section::make('Forecast metrics')
                ->icon('heroicon-o-chart-bar')
                ->columns(4)
                ->schema([
                    Infolists\Components\TextEntry::make('total_daily_impressions')
                        ->label('Daily impressions')
                        ->numeric(decimalPlaces: 0),
                    Infolists\Components\TextEntry::make('total_impressions_for_duration')
                        ->label('Total impressions')
                        ->numeric(decimalPlaces: 0)
                        ->weight('bold')
                        ->helperText('Daily × duration'),
                    Infolists\Components\TextEntry::make('estimated_unique_reach')
                        ->label('Unique reach')
                        ->numeric(decimalPlaces: 0)
                        ->helperText('Spatial dedup'),
                    Infolists\Components\TextEntry::make('estimated_frequency')
                        ->label('Frequency')
                        ->badge()
                        ->formatStateUsing(fn (?float $state) => $state !== null
                            ? number_format($state, 1) . '×'
                            : '—')
                        ->color(fn ($record) => $record->frequency_color)
                        ->helperText('Impr / reach'),

                    Infolists\Components\TextEntry::make('unique_geohash_cells')
                        ->label('Distinct areas')
                        ->numeric()
                        ->helperText('ST_GeoHash(7) ≈ 150m cells'),
                    Infolists\Components\TextEntry::make('screens_with_estimate')
                        ->label('Screens with data'),
                    Infolists\Components\TextEntry::make('screens_missing_estimate')
                        ->label('Screens missing data')
                        ->badge()
                        ->color(fn ($state) => ($state ?? 0) > 0 ? 'warning' : 'gray'),
                    Infolists\Components\TextEntry::make('avg_confidence')
                        ->label('Avg confidence')
                        ->badge()
                        ->formatStateUsing(fn (?float $state) => $state !== null
                            ? number_format($state, 2)
                            : '—')
                        ->color(fn ($record) => $record->confidence_color),
                ]),

            // ── Budget / CPM ──────────────────────────────────────────
            Infolists\Components\Section::make('Budget & CPM')
                ->icon('heroicon-o-banknotes')
                ->columns(2)
                ->visible(fn ($record) => $record->total_budget > 0)
                ->schema([
                    Infolists\Components\TextEntry::make('total_budget')
                        ->label('Total budget')
                        ->money('VND'),
                    Infolists\Components\TextEntry::make('estimated_cpm')
                        ->label('Effective CPM')
                        ->money('VND')
                        ->helperText('Budget / (Impressions / 1000)'),
                ]),

            // ── Disclaimer (handoff §7.4) ──────────────────────────────
            Infolists\Components\Section::make('About the model')
                ->icon('heroicon-o-information-circle')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Infolists\Components\TextEntry::make('_model_note')
                        ->hiddenLabel()
                        ->getStateUsing(fn () =>
                            'Reach dựa trên mô hình spatial dedup simple (ST_GeoHash 150m cells × 500 viewers/day × log saturation). '
                            . 'Numbers là directional estimate, không phải measured reality. '
                            . 'Sẽ refine dần khi có real population density (Phase 4.2.1) + traffic calibration samples (Phase 4.2.4).'
                        )
                        ->columnSpanFull(),
                    Infolists\Components\TextEntry::make('formula_version_id')
                        ->label('Formula version ID')
                        ->default('—'),
                ]),

            // ── Raw payload (collapsed) ────────────────────────────────
            Infolists\Components\Section::make('Screen IDs (Data Engine bigint)')
                ->icon('heroicon-o-list-bullet')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Infolists\Components\TextEntry::make('screen_ids')
                        ->hiddenLabel()
                        ->listWithLineBreaks()
                        ->bulleted()
                        ->formatStateUsing(fn ($state) => is_array($state) ? $state : [])
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOohxCampaignEstimates::route('/'),
            'create' => Pages\CreateOohxCampaignEstimate::route('/create'),
            'view'   => Pages\ViewOohxCampaignEstimate::route('/{record}'),
        ];
    }
}
