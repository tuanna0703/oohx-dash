<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasImportPreview;
use Filament\Pages\Page;

class Import extends Page
{
    use HasImportPreview;

    protected static ?string $slug            = 'tools/import';
    protected static ?string $navigationIcon  = null;
    protected static ?string $navigationGroup = 'Tools';
    protected static ?string $navigationLabel = 'Import Inventory';
    protected static ?int    $navigationSort  = 10;
    protected static string  $view            = 'filament.pages.import';

    public function mount(): void
    {
        $this->mountHasImportPreview();
    }

    protected function resolveOwnerId(): string
    {
        return $this->data['owner_id'] ?? '';
    }

    protected function previewUrl(): string
    {
        return '/admin/tools/import/preview';
    }

    protected function showOwnerSelect(): bool
    {
        return true;
    }
}
