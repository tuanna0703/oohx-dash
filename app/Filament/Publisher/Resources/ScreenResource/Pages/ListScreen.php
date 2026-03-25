<?php

namespace App\Filament\Publisher\Resources\ScreenResource\Pages;

use App\Filament\Publisher\Resources\ScreenResource;
use App\Filament\Publisher\Widgets\ScreenStatsWidget;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListScreen extends ListRecords
{
    protected static string $resource = ScreenResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            ScreenStatsWidget::class,
        ];
    }
}
