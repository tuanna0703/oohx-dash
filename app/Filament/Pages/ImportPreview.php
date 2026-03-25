<?php

namespace App\Filament\Pages;

use App\Services\ScreenRegistryService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class ImportPreview extends Page
{
    protected static ?string $slug = 'tools/import/preview';
    protected static bool    $shouldRegisterNavigation = false;
    protected static string  $view = 'filament.pages.import-preview';

    public string  $token      = '';
    public ?array  $preview    = null;
    public string  $ownerId    = '';
    public string  $storedPath = '';
    public bool    $expired    = false;

    public string $filterAction = 'all';
    public int    $sitePage     = 1;
    public int    $screenPage   = 1;
    public int    $perPage      = 50;

    public function mount(): void
    {
        $this->token   = request()->query('token', '');
        $session       = session()->get("import_{$this->token}");

        if (! $session) {
            $this->expired = true;
            return;
        }

        $this->preview    = $session['preview'];
        $this->ownerId    = $session['owner_id'];
        $this->storedPath = $session['stored_path'];
    }

    public function confirmImport(ScreenRegistryService $registry): void
    {
        if ($this->expired || empty($this->storedPath)) {
            Notification::make()->title('Phiên đã hết hạn')->body('Vui lòng upload lại file.')->danger()->send();
            $this->redirect('/admin/tools/import', navigate: true);
            return;
        }

        $fullPath = storage_path('app/' . $this->storedPath);

        if (! file_exists($fullPath)) {
            Notification::make()->title('File không còn tồn tại')->body('Vui lòng upload lại.')->danger()->send();
            $this->redirect('/admin/tools/import', navigate: true);
            return;
        }

        $results = $registry->importHivestackXlsx($fullPath, $this->ownerId);

        session()->forget("import_{$this->token}");
        session()->put("import_done_{$this->token}", $results);

        $errCount = count($results['errors'] ?? []);
        if ($errCount === 0) {
            Notification::make()->title('Import thành công!')->body("{$results['sites']} sites · {$results['screens']} screens đã lưu.")->success()->send();
        } else {
            Notification::make()->title("Import hoàn tất với {$errCount} lỗi")->body(implode("\n", array_slice($results['errors'], 0, 3)))->warning()->send();
        }

        $this->redirect('/admin/tools/import/confirm?token=' . $this->token, navigate: true);
    }

    public function backToUpload(): void
    {
        session()->forget("import_{$this->token}");
        $this->redirect('/admin/tools/import', navigate: true);
    }

    public function setFilter(string $action): void { $this->filterAction = $action; $this->sitePage = 1; $this->screenPage = 1; }
    public function getSummary(): array { return $this->preview['summary'] ?? ['sites_new'=>0,'sites_update'=>0,'sites_error'=>0,'screens_new'=>0,'screens_update'=>0,'screens_error'=>0]; }
    public function getFilteredSites(): array   { return $this->paginateItems($this->applyFilter($this->preview['sites']   ?? []), $this->sitePage); }
    public function getFilteredScreens(): array { return $this->paginateItems($this->applyFilter($this->preview['screens'] ?? []), $this->screenPage); }
    public function getSiteTotal(): int         { return count($this->applyFilter($this->preview['sites']   ?? [])); }
    public function getScreenTotal(): int       { return count($this->applyFilter($this->preview['screens'] ?? [])); }
    public function hasSiteNextPage(): bool     { return ($this->sitePage   * $this->perPage) < $this->getSiteTotal(); }
    public function hasScreenNextPage(): bool   { return ($this->screenPage * $this->perPage) < $this->getScreenTotal(); }
    private function applyFilter(array $items): array { if ($this->filterAction === 'all') return $items; return array_values(array_filter($items, fn($i) => $i['action'] === $this->filterAction)); }
    private function paginateItems(array $items, int $page): array { return array_slice($items, ($page - 1) * $this->perPage, $this->perPage); }
}
