<?php

namespace App\Filament\Publisher\Pages;

use Filament\Pages\Page;

class ImportConfirm extends Page
{
    protected static ?string $slug = 'tools/import/confirm';
    protected static bool    $shouldRegisterNavigation = false;
    protected static string  $view = 'filament.publisher.pages.import-confirm';

    public ?array $results = null;
    public string $token   = '';

    public function mount(): void
    {
        $this->token   = request()->query('token', '');
        $this->results = session()->pull("import_done_{$this->token}");
        if (! $this->results) {
            $this->redirect('/publisher/tools/import', navigate: true);
        }
    }

    public function importAnother(): void
    {
        $this->redirect('/publisher/tools/import', navigate: true);
    }
}
