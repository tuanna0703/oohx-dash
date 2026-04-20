<?php

namespace App\Filament\Resources\OohxConfig\FormulaVersionResource\Pages;

use App\Filament\Resources\OohxConfig\FormulaVersionResource;
use App\Models\Oohx\Config\FormulaVersion;
use App\Services\Oohx\ConfigManagerService;
use Filament\Actions;
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
                ->modalDescription(fn () =>
                    "Switch active → {$this->record->tag}. Python sẽ dùng formula mới trong ≤ 5 phút.")
                ->action(function () {
                    try {
                        app(ConfigManagerService::class)->activateVersion($this->record->tag);
                        Notification::make()
                            ->title("Activated {$this->record->tag}")
                            ->success()->send();
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
