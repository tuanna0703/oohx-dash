<?php

namespace App\Filament\Resources\OohxConfig\FormulaVersionResource\Pages;

use App\Filament\Resources\OohxConfig\FormulaVersionResource;
use App\Services\Oohx\ConfigManagerService;
use App\Services\Oohx\JobOrchestrator;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewFormulaVersion extends ViewRecord
{
    protected static string $resource = FormulaVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('activate')
                ->label('Activate this version')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => ! $this->record->is_active)
                ->requiresConfirmation()
                ->modalHeading(fn () => "Activate {$this->record->tag}?")
                ->modalDescription('Switch active version. Python sẽ dùng formula mới trong ≤ 5 phút. Existing estimates vẫn dùng version cũ cho tới khi recompute.')
                ->form([
                    Forms\Components\Checkbox::make('recompute_stale')
                        ->label('Also enqueue recompute-stale job')
                        ->helperText('Tự trigger bulk recompute cho screens chưa có estimate trên version mới.')
                        ->default(false),
                ])
                ->action(function (array $data) {
                    try {
                        app(ConfigManagerService::class)->activateVersion($this->record->tag);

                        if ($data['recompute_stale'] ?? false) {
                            $job = app(JobOrchestrator::class)->enqueueBulkAction('recompute_stale', priority: 50);
                            Notification::make()
                                ->title("Activated {$this->record->tag} + queued recompute-stale")
                                ->body("Job #{$job->id}. Xem progress ở Recompute Jobs.")
                                ->success()->persistent()->send();
                        } else {
                            Notification::make()
                                ->title("Activated {$this->record->tag}")
                                ->success()->send();
                        }

                        $this->refreshFormData(['is_active', 'activated_at']);
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Activate failed')
                            ->body($e->getMessage())
                            ->danger()->send();
                    }
                }),
        ];
    }
}
