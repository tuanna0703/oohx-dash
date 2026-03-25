<?php

namespace App\Filament\Publisher\Pages;

use App\Filament\Concerns\HasImportPreview;
use Filament\Pages\Page;

class Import extends Page
{
    use HasImportPreview;

    protected static ?string $slug            = 'tools/import';
    protected static ?string $navigationIcon  = null;
    protected static ?string $navigationGroup = 'Tools';
    protected static ?string $navigationLabel = 'Import Screens';
    protected static ?int    $navigationSort  = 20;
    protected static string  $view            = 'filament.publisher.pages.import';

    public function mount(): void
    {
        $this->mountHasImportPreview();
    }

    protected function resolveOwnerId(): string
    {
        return (string) (auth()->user()->current_owner_id ?? '');
    }

    protected function previewUrl(): string
    {
        return '/publisher/tools/import/preview';
    }

    protected function showOwnerSelect(): bool
    {
        return false;
    }
}
