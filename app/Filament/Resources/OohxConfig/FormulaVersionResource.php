<?php

namespace App\Filament\Resources\OohxConfig;

use App\Filament\Resources\OohxConfig\FormulaVersionResource\Pages;
use App\Models\Oohx\Config\FormulaVersion;
use App\Services\Oohx\ConfigManagerService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Filament Resource cho config.formula_versions.
 *
 * UI flow:
 *   1. Header action "Publish current state as new version" — snapshot config.* tables
 *   2. Per-row "Activate" — switch active version (atomic, partial unique index ensure max 1 active)
 *   3. View page hiển thị snapshot JSON (4 nhóm coefficient)
 *
 * Lưu ý: KHÔNG cho edit/delete versions — versions là immutable historical record.
 */
class FormulaVersionResource extends Resource
{
    protected static ?string $model = FormulaVersion::class;

    protected static ?string $navigationIcon  = 'heroicon-o-archive-box';
    protected static ?string $navigationGroup = 'OOHX · Data Engine';
    protected static ?string $navigationLabel = 'Formula versions';
    protected static ?string $modelLabel      = 'Formula version';
    protected static ?int    $navigationSort  = 70;

    public static function canCreate(): bool { return false; } // create qua publishVersion action only
    public static function canEdit($r): bool { return false; }
    public static function canDelete($r): bool { return false; }

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),

                Tables\Columns\TextColumn::make('tag')
                    ->label('Tag')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn ($record) => $record->is_active ? 'success' : 'gray'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('description')
                    ->wrap()
                    ->limit(60)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_by')
                    ->label('Published by')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Published')
                    ->dateTime()
                    ->since()
                    ->sortable(),

                Tables\Columns\TextColumn::make('activated_at')
                    ->label('Activated')
                    ->dateTime()
                    ->since()
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                Tables\Actions\Action::make('publish_version')
                    ->label('Publish current state')
                    ->icon('heroicon-o-archive-box-arrow-down')
                    ->color('primary')
                    ->modalHeading('Publish formula version mới')
                    ->modalDescription('Snapshot toàn bộ config.* hiện tại. Có thể activate sau publish.')
                    ->form([
                        Forms\Components\TextInput::make('tag')
                            ->label('Version tag')
                            ->required()
                            ->placeholder('vd: v-2026-05-15')
                            ->helperText('Unique identifier — không trùng version cũ.')
                            ->maxLength(50),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->required()
                            ->placeholder('Lý do publish version này')
                            ->rows(3)
                            ->maxLength(500),

                        Forms\Components\Toggle::make('activate_immediately')
                            ->label('Activate ngay sau publish')
                            ->helperText('Bật = Python sẽ dùng formula mới trong ≤ 5 phút.')
                            ->default(false),
                    ])
                    ->action(function (array $data) {
                        try {
                            $svc = app(ConfigManagerService::class);
                            $version = $svc->publishVersion($data['tag'], $data['description']);

                            if ($data['activate_immediately'] ?? false) {
                                $svc->activateVersion($data['tag']);
                                Notification::make()
                                    ->title("Published + activated {$data['tag']}")
                                    ->body('Python sẽ dùng version mới trong ≤ 5 phút.')
                                    ->success()->persistent()->send();
                            } else {
                                Notification::make()
                                    ->title("Published {$data['tag']}")
                                    ->body('Click "Activate" để switch.')
                                    ->success()->send();
                            }
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Publish failed')
                                ->body($e->getMessage())
                                ->danger()->persistent()->send();
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (FormulaVersion $record) => ! $record->is_active)
                    ->requiresConfirmation()
                    ->modalHeading(fn (FormulaVersion $record) => "Activate {$record->tag}?")
                    ->modalDescription('Switch active version. Python sẽ dùng formula mới trong ≤ 5 phút. Existing estimates vẫn dùng version cũ cho tới khi recompute.')
                    ->form([
                        Forms\Components\Checkbox::make('recompute_stale')
                            ->label('Also enqueue recompute-stale job')
                            ->helperText('Tự trigger bulk recompute cho screens chưa có estimate trên version mới. Khuyến nghị cho production changes.')
                            ->default(false),
                    ])
                    ->action(function (FormulaVersion $record, array $data) {
                        try {
                            app(ConfigManagerService::class)->activateVersion($record->tag);

                            if ($data['recompute_stale'] ?? false) {
                                $job = app(\App\Services\Oohx\JobOrchestrator::class)
                                    ->enqueueBulkAction('recompute_stale', priority: 50);
                                Notification::make()
                                    ->title("Activated {$record->tag} + enqueued recompute-stale")
                                    ->body("Job #{$job->id} queued. Xem progress ở Recompute Jobs.")
                                    ->success()->persistent()->send();
                            } else {
                                Notification::make()
                                    ->title("Activated {$record->tag}")
                                    ->body('Trigger recompute manually từ Recompute Jobs nếu cần.')
                                    ->success()->send();
                            }
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Activate failed')
                                ->body($e->getMessage())
                                ->danger()->persistent()->send();
                        }
                    }),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Version metadata')
                ->columns(3)
                ->schema([
                    Infolists\Components\TextEntry::make('tag')->badge(),
                    Infolists\Components\IconEntry::make('is_active')->boolean(),
                    Infolists\Components\TextEntry::make('id')->label('ID'),
                    Infolists\Components\TextEntry::make('description')->columnSpan(2),
                    Infolists\Components\TextEntry::make('created_by')->label('Published by'),
                    Infolists\Components\TextEntry::make('created_at')->dateTime(),
                    Infolists\Components\TextEntry::make('activated_at')->dateTime()->placeholder('—'),
                ]),

            Infolists\Components\Section::make('Snapshot — coefficient frozen tại thời điểm publish')
                ->collapsible()
                ->schema([
                    Infolists\Components\ViewEntry::make('snapshot')
                        ->view('filament.resources.oohx-config.formula-version-snapshot'),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFormulaVersions::route('/'),
            'view'  => Pages\ViewFormulaVersion::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            $active = FormulaVersion::where('is_active', true)->first();
            return $active?->tag;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }
}
