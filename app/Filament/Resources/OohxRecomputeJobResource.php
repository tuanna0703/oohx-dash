<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OohxRecomputeJobResource\Pages;
use App\Models\Oohx\RecomputeJob;
use App\Services\Oohx\JobOrchestrator;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Filament Resource cho core.recompute_jobs (Phase 2.B).
 *
 * Operations:
 *   - List với filter status/type/city + realtime counter header
 *   - Enqueue screen (single uuid)
 *   - Enqueue city (select from distinct cities)
 *   - Enqueue bulk action — 3 radio + explicit list option
 *   - Retry failed/cancelled
 *   - Cancel pending or bulk+processing (semantic Phase 2.B)
 *
 * Xem PHASE-2B-HANDOFF.md cho cooperative cancel + progress tracking semantics.
 */
class OohxRecomputeJobResource extends Resource
{
    protected static ?string $model = RecomputeJob::class;

    protected static ?string $navigationIcon  = 'heroicon-o-queue-list';
    protected static ?string $navigationGroup = 'OOHX · Data Engine';
    protected static ?string $navigationLabel = 'Recompute Jobs';
    protected static ?string $modelLabel      = 'Recompute Job';
    protected static ?int    $navigationSort  = 65;

    public static function canCreate(): bool { return false; } // create qua header actions only
    public static function canEdit($r): bool { return false; }
    public static function canDelete($r): bool { return false; } // worker Python own lifecycle

    public static function form(Form $form): Form
    {
        return $form->schema([]); // no generic form — custom enqueue modals
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // Eager-load screen relation (cross-connection via subquery, no raw JOIN)
        return parent::getEloquentQuery()->with('screen');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('10s') // refresh table mỗi 10s để cập nhật status realtime
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),

                Tables\Columns\TextColumn::make('job_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'screen'  => 'info',
                        'city'    => 'warning',
                        'bulk'    => 'primary',
                        'preview' => 'gray',
                        default   => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('target_label')
                    ->label('Target')
                    ->searchable(query: function (\Illuminate\Database\Eloquent\Builder $q, string $search) {
                        return $q->where('city', 'ilike', "%{$search}%")
                            ->orWhereHas('screen', fn ($q) => $q->where('external_id', 'ilike', "%{$search}%"));
                    })
                    ->wrap()
                    ->limit(50),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending'    => 'warning',
                        'processing' => 'info',
                        'done'       => 'success',
                        'failed'     => 'danger',
                        'cancelled'  => 'gray',
                        default      => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('progress_percent')
                    ->label('Progress')
                    ->formatStateUsing(function ($record) {
                        $p = $record->progress_data;
                        if (! $p) return $record->status === 'processing' ? '...' : '—';
                        $done   = ($p['done'] ?? 0) + ($p['failed'] ?? 0);
                        $total  = $p['total'] ?? 0;
                        return $total > 0 ? "{$done}/{$total}" : '—';
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('priority')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('retry_count')
                    ->label('Retries')
                    ->numeric()
                    ->toggleable()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray'),

                Tables\Columns\TextColumn::make('requested_at')
                    ->label('Requested')
                    ->dateTime()
                    ->since()
                    ->sortable()
                    ->tooltip(fn ($state) => $state?->format('Y-m-d H:i:s')),

                Tables\Columns\TextColumn::make('duration_seconds')
                    ->label('Duration')
                    ->formatStateUsing(fn (?int $state) => $state !== null ? "{$state}s" : '—')
                    ->toggleable(),
            ])
            ->defaultSort('requested_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending'    => 'Pending',
                        'processing' => 'Processing',
                        'done'       => 'Done',
                        'failed'     => 'Failed',
                        'cancelled'  => 'Cancelled',
                    ]),
                SelectFilter::make('job_type')
                    ->options([
                        'screen'  => 'Screen',
                        'city'    => 'City',
                        'bulk'    => 'Bulk',
                        'preview' => 'Preview (Phase 3.A)',
                    ]),
                SelectFilter::make('city')
                    ->label('City (city-type jobs)')
                    ->options(fn () => RecomputeJob::query()
                        ->whereNotNull('city')
                        ->distinct()
                        ->orderBy('city')
                        ->pluck('city', 'city')
                        ->toArray()),
            ])
            ->headerActions([
                self::enqueueScreenAction(),
                self::enqueueCityAction(),
                self::enqueueBulkAction(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (RecomputeJob $r) => in_array($r->status, ['failed', 'cancelled'], true))
                    ->requiresConfirmation()
                    ->modalDescription('Reset job về pending để worker pick lại. retry_count = 0.')
                    ->action(fn (RecomputeJob $r) => self::handleRetry($r->id)),

                Tables\Actions\Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (RecomputeJob $r) => $r->is_cancellable)
                    ->requiresConfirmation()
                    ->modalDescription(fn (RecomputeJob $r) => $r->status === 'processing'
                        ? 'Bulk job đang chạy. Worker sẽ stop sau ≤25 screens. Continue?'
                        : 'Cancel pending job?')
                    ->action(fn (RecomputeJob $r) => self::handleCancel($r->id)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('retry_bulk')
                        ->label('Retry selected failed')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalDescription('Reset tất cả failed/cancelled jobs đã chọn về pending.')
                        ->action(function ($records) {
                            $svc = app(JobOrchestrator::class);
                            $ok = 0; $skip = 0; $fail = 0;
                            foreach ($records as $r) {
                                if (! in_array($r->status, ['failed', 'cancelled'], true)) {
                                    $skip++;
                                    continue;
                                }
                                try { $svc->retry($r->id); $ok++; }
                                catch (\Throwable) { $fail++; }
                            }
                            Notification::make()
                                ->title("Retry done")
                                ->body("✓ {$ok} · Skipped (not retryable): {$skip} · Failed: {$fail}")
                                ->{$fail > 0 ? 'warning' : 'success'}()
                                ->send();
                        }),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Job metadata')
                ->columns(4)
                ->schema([
                    Infolists\Components\TextEntry::make('id')->label('Job ID'),
                    Infolists\Components\TextEntry::make('job_type')->badge(),
                    Infolists\Components\TextEntry::make('status')->badge(),
                    Infolists\Components\TextEntry::make('priority')->numeric(),
                    Infolists\Components\TextEntry::make('target_label')->label('Target')->columnSpan(2),
                    Infolists\Components\TextEntry::make('action')->label('Bulk action')
                        ->placeholder('—')
                        ->badge()
                        ->visible(fn ($record) => $record->job_type === 'bulk'),
                    Infolists\Components\TextEntry::make('retry_count')->label('Retries'),
                ]),

            Infolists\Components\Section::make('Timing')
                ->columns(4)
                ->schema([
                    Infolists\Components\TextEntry::make('requested_at')->dateTime()->since(),
                    Infolists\Components\TextEntry::make('started_at')->dateTime()->placeholder('—'),
                    Infolists\Components\TextEntry::make('finished_at')->dateTime()->placeholder('—'),
                    Infolists\Components\TextEntry::make('duration_seconds')
                        ->label('Duration')
                        ->formatStateUsing(fn ($state) => $state !== null ? "{$state}s" : '—'),
                    Infolists\Components\TextEntry::make('cancelled_at')->dateTime()->placeholder('—'),
                ]),

            // Progress panel — chỉ hiển thị cho bulk jobs có progress data
            Infolists\Components\Section::make('Progress')
                ->visible(fn ($record) => $record->progress_data !== null)
                ->schema([
                    Infolists\Components\ViewEntry::make('progress_widget')
                        ->view('filament.resources.oohx-recompute-job.progress-widget'),
                ]),

            // Phase 3.A — Preview result panel (dry-run so sánh version)
            Infolists\Components\Section::make('Preview result')
                ->icon('heroicon-o-magnifying-glass-plus')
                ->description('Dry-run comparison — NOT applied to output.screen_traffic_estimates.')
                ->visible(fn ($record) => $record->is_preview)
                ->schema([
                    Infolists\Components\ViewEntry::make('preview_widget')
                        ->view('filament.resources.oohx-recompute-job.preview-result'),
                ]),

            Infolists\Components\Section::make('Error')
                ->visible(fn ($record) => ! empty($record->error_message))
                ->schema([
                    Infolists\Components\TextEntry::make('error_message')
                        ->label(false)
                        ->prose()
                        ->markdown(false),
                ]),

            Infolists\Components\Section::make('Payload (debug)')
                ->collapsible()
                ->collapsed()
                ->schema([
                    Infolists\Components\TextEntry::make('payload_json')
                        ->label(false)
                        // getStateUsing replace state NGAY — Filament không thấy array nữa
                        ->getStateUsing(fn ($record) => json_encode(
                            $record->payload ?? [],
                            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
                        ))
                        ->columnSpanFull()
                        ->extraAttributes([
                            'class' => 'font-mono text-xs whitespace-pre-wrap break-all',
                        ]),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRecomputeJobs::route('/'),
            'view'  => Pages\ViewRecomputeJob::route('/{record}'),
        ];
    }

    /**
     * Badge: số pending + processing (jobs cần attention). Silent fail.
     */
    public static function getNavigationBadge(): ?string
    {
        try {
            $n = RecomputeJob::active()->count();
            return $n > 0 ? (string) $n : null;
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
        return 'Pending + Processing jobs';
    }

    // ── Header actions (factory methods) ─────────────────────────────

    private static function enqueueScreenAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('enqueue_screen')
            ->label('Enqueue screen')
            ->icon('heroicon-o-tv')
            ->color('info')
            ->form([
                Forms\Components\TextInput::make('screen_id')
                    ->label('Screen ID (numeric, core.screens.id)')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->helperText('Internal Data Engine ID (BIGINT). Xem Traffic Estimates list để lookup.'),
                Forms\Components\TextInput::make('priority')
                    ->label('Priority (lower = higher)')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(1000)
                    ->default(100),
            ])
            ->action(function (array $data) {
                try {
                    $job = app(JobOrchestrator::class)->enqueueScreen(
                        (int) $data['screen_id'],
                        (int) $data['priority'],
                    );
                    Notification::make()
                        ->title("Enqueued job #{$job->id}")
                        ->body("Screen {$data['screen_id']} · priority {$data['priority']}")
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()->title('Enqueue failed')->body($e->getMessage())->danger()->send();
                }
            });
    }

    private static function enqueueCityAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('enqueue_city')
            ->label('Enqueue city')
            ->icon('heroicon-o-map')
            ->color('warning')
            ->form([
                Forms\Components\Select::make('city')
                    ->label('City')
                    ->options(fn () => self::cityOptions())
                    ->required()
                    ->searchable(),
                Forms\Components\TextInput::make('priority')
                    ->required()
                    ->numeric()
                    ->default(200),
            ])
            ->action(function (array $data) {
                try {
                    $job = app(JobOrchestrator::class)->enqueueCity($data['city'], (int) $data['priority']);
                    Notification::make()
                        ->title("Enqueued city job #{$job->id}")
                        ->body("City: {$data['city']}")
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()->title('Enqueue failed')->body($e->getMessage())->danger()->send();
                }
            });
    }

    private static function enqueueBulkAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('enqueue_bulk')
            ->label('Enqueue bulk')
            ->icon('heroicon-o-queue-list')
            ->color('primary')
            ->form([
                Forms\Components\Radio::make('source')
                    ->label('Source')
                    ->options([
                        'recompute_all'     => 'Recompute ALL active screens',
                        'recompute_stale'   => 'Recompute STALE screens (not on active formula version)',
                        'recompute_by_city' => 'Recompute by city',
                        'explicit'          => 'Explicit screen IDs list',
                    ])
                    ->required()
                    ->default('recompute_stale')
                    ->live(),

                Forms\Components\Select::make('city')
                    ->label('City')
                    ->options(fn () => self::cityOptions())
                    ->searchable()
                    ->visible(fn (Forms\Get $get) => $get('source') === 'recompute_by_city')
                    ->required(fn (Forms\Get $get) => $get('source') === 'recompute_by_city'),

                Forms\Components\Textarea::make('screen_ids_text')
                    ->label('Screen IDs (numeric, one per line)')
                    ->rows(6)
                    ->placeholder("123\n456\n789")
                    ->helperText('Tối đa 5000 IDs. Dùng recompute_all nếu muốn nhiều hơn.')
                    ->visible(fn (Forms\Get $get) => $get('source') === 'explicit')
                    ->required(fn (Forms\Get $get) => $get('source') === 'explicit'),

                Forms\Components\TextInput::make('priority')
                    ->required()
                    ->numeric()
                    ->default(150)
                    ->helperText('Lower = higher priority. 50 = urgent, 200 = background.'),
            ])
            ->action(function (array $data) {
                try {
                    $svc = app(JobOrchestrator::class);
                    $priority = (int) $data['priority'];

                    if ($data['source'] === 'explicit') {
                        $ids = array_filter(array_map('intval', preg_split('/\s+/', trim($data['screen_ids_text']))));
                        $job = $svc->enqueueBulk($ids, $priority);
                    } else {
                        $job = $svc->enqueueBulkAction(
                            $data['source'],
                            $data['city'] ?? null,
                            $priority,
                        );
                    }

                    Notification::make()
                        ->title("Enqueued bulk job #{$job->id}")
                        ->body("Source: {$data['source']}")
                        ->success()
                        ->persistent()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()->title('Enqueue failed')->body($e->getMessage())->danger()->persistent()->send();
                }
            });
    }

    // ── Shared action handlers ────────────────────────────────────────

    public static function handleRetry(int $jobId): void
    {
        try {
            app(JobOrchestrator::class)->retry($jobId);
            Notification::make()->title("Job #{$jobId} reset to pending")->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Retry failed')->body($e->getMessage())->danger()->send();
        }
    }

    public static function handleCancel(int $jobId): void
    {
        try {
            app(JobOrchestrator::class)->cancel($jobId);
            Notification::make()->title("Job #{$jobId} cancelled")->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Cancel failed')->body($e->getMessage())->danger()->send();
        }
    }

    /**
     * Distinct cities — fetch từ core.screens thay vì recompute_jobs để cover
     * cities mới chưa có job nào.
     */
    private static function cityOptions(): array
    {
        try {
            $rows = \Illuminate\Support\Facades\DB::connection('oohx_control')->select("
                SELECT DISTINCT city FROM core.screens
                WHERE city IS NOT NULL AND city != ''
                ORDER BY city
            ");
            return collect($rows)->pluck('city', 'city')->toArray();
        } catch (\Throwable) {
            return [];
        }
    }
}
