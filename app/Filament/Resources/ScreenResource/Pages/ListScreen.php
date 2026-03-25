<?php
namespace App\Filament\Resources\ScreenResource\Pages;
use App\Filament\Resources\ScreenResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
class ListScreen extends ListRecords {
    protected static string $resource = ScreenResource::class;
    protected function getHeaderActions(): array { return [Actions\CreateAction::make()]; }
}
