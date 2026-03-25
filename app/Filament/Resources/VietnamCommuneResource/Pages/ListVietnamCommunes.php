<?php

namespace App\Filament\Resources\VietnamCommuneResource\Pages;

use App\Filament\Resources\VietnamCommuneResource;
use App\Filament\Resources\VietnamCommuneResource\Widgets\CommuneStatsWidget;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListVietnamCommunes extends ListRecords
{
    protected static string $resource = VietnamCommuneResource::class;

    protected function getHeaderWidgets(): array
    {
        return [CommuneStatsWidget::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
