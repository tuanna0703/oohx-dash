<?php

namespace App\Filament\Publisher\Pages;

use App\Services\SiteImportService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Wizard 4 bước import Sites từ file Excel.
 *
 * Step 1 — Upload   : Chọn file & validate format
 * Step 2 — Preview  : Xem trước dữ liệu, so sánh với DB
 * Step 3 — Importing: Tự động trigger import khi render
 * Step 4 — Report   : Kết quả chi tiết
 */
class ImportSites extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug            = 'sites/import';
    protected static ?string $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Import Sites';
    protected static ?int    $navigationSort  = 2;
    protected static bool    $shouldRegisterNavigation = false; // Ẩn khỏi nav, truy cập qua button
    protected static string  $view            = 'filament.publisher.pages.import-sites';

    // ── Wizard state ─────────────────────────────────────────────────────────
    /** 1=upload | 2=preview | 3=importing | 4=report */
    public int $step = 1;

    // ── Step 1 — form ────────────────────────────────────────────────────────
    public ?array $data = [];

    // ── Step 2/3 — data ──────────────────────────────────────────────────────
    public ?string $storedPath = null;
    public ?array  $preview    = null;

    // ── Step 4 — results ─────────────────────────────────────────────────────
    public ?array $results = null;

    // ── Preview pagination & filter (Sites) ──────────────────────────────────
    public string $filterAction = 'all';
    public int    $sitePage     = 1;
    public int    $perPage      = 100;

    // ── Preview pagination & filter (Screens) ────────────────────────────────
    public string $screenFilterAction = 'all';
    public int    $screenPage         = 1;

    // ─────────────────────────────────────────────────────────────────────────

    public function mount(): void
    {
        $this->form->fill();
    }

    protected function resolveOwnerId(): string
    {
        return (string) (auth()->user()?->current_owner_id ?? '');
    }

    // ── Form (Step 1) ────────────────────────────────────────────────────────

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\FileUpload::make('file')
                    ->label('File Excel (.xlsx)')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/octet-stream',
                    ])
                    ->disk('local')
                    ->directory('imports/sites')  // Lưu thẳng vào local disk → tránh metadata mismatch
                    ->maxSize(20480) // 20MB
                    ->required()
                    ->helperText('Dùng OOHX Import Template — 2 sheets: Sites + Screens. Tải template tại /storage/templates/oohx-import-template.xlsx'),
            ])
            ->statePath('data');
    }

    // ── Step 1 → Step 2: Upload & Preview ────────────────────────────────────

    public function uploadAndPreview(SiteImportService $svc): void
    {
        set_time_limit(120); // Đọc file + build preview + Livewire serialize có thể > 30s

        $formData = $this->form->getState();
        $raw      = $formData['file'] ?? null;

        // Filament đôi khi trả về array ngay cả với single-file upload
        if (is_array($raw)) {
            $raw = array_values(array_filter($raw))[0] ?? null;
        }

        if (! $raw) {
            Notification::make()->title('Vui lòng chọn file')->warning()->send();
            return;
        }

        $storedPath = $this->resolveUploadedPath($raw);

        if (! $storedPath) {
            $debugInfo = is_string($raw)
                ? "path='{$raw}' | local_exists=" . (\Storage::disk('local')->exists(ltrim($raw, '/')) ? 'yes' : 'no')
                  . " | public_exists=" . (\Storage::disk('public')->exists(ltrim($raw, '/')) ? 'yes' : 'no')
                : 'type=' . gettype($raw);

            Notification::make()
                ->title('Không tìm thấy file upload')
                ->body("Vui lòng thử lại.\n({$debugInfo})")
                ->danger()
                ->persistent()
                ->send();
            return;
        }

        $this->storedPath = $storedPath;
        // Dùng Storage::path() thay vì tự nối chuỗi — tránh lỗi double-slash hay sai root
        $fullPath = \Storage::disk('local')->path($storedPath);

        try {
            $this->preview = $svc->readPreview($fullPath, $this->resolveOwnerId());
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Lỗi đọc file')
                ->body('Kiểm tra lại định dạng file. ' . $e->getMessage())
                ->danger()
                ->send();
            \Storage::disk('local')->delete($storedPath);
            $this->storedPath = null;
            return;
        }

        $this->filterAction = 'all';
        $this->sitePage     = 1;
        $this->step         = 2;
    }

    // ── Step 2 → Step 3: Xác nhận import ─────────────────────────────────────

    public function confirmImport(): void
    {
        $this->step = 3;
        // View step 3 sẽ gọi $wire.runImport() qua Alpine x-init
    }

    // ── Step 3: Thực thi import (gọi từ Alpine.js) ───────────────────────────

    public function runImport(SiteImportService $svc): void
    {
        set_time_limit(120); // Import nhiều rows có thể mất nhiều thời gian

        if (! $this->storedPath) {
            $this->step = 1;
            return;
        }

        $fullPath = \Storage::disk('local')->path($this->storedPath);

        if (! file_exists($fullPath)) {
            Notification::make()->title('File không còn tồn tại')->body('Vui lòng upload lại.')->danger()->send();
            $this->step = 1;
            return;
        }

        try {
            $this->results = $svc->import($fullPath, $this->resolveOwnerId());
        } catch (\Throwable $e) {
            Notification::make()->title('Lỗi import')->body($e->getMessage())->danger()->send();
            $this->step = 2;
            return;
        }

        // Xoá file tạm sau khi import xong
        \Storage::disk('local')->delete($this->storedPath);
        $this->storedPath = null;

        $this->step = 4;
    }

    // ── Navigation helpers ────────────────────────────────────────────────────

    public function backToUpload(): void
    {
        if ($this->storedPath) {
            \Storage::disk('local')->delete($this->storedPath);
        }

        $this->step               = 1;
        $this->preview            = null;
        $this->results            = null;
        $this->storedPath         = null;
        $this->filterAction       = 'all';
        $this->sitePage           = 1;
        $this->screenFilterAction = 'all';
        $this->screenPage         = 1;
        $this->form->fill();
    }

    public function importAnother(): void
    {
        $this->backToUpload();
    }

    public function goToSitesList(): void
    {
        $this->redirect(
            \App\Filament\Publisher\Resources\SiteResource::getUrl('index'),
            navigate: true
        );
    }

    // ── Preview filter & pagination ──────────────────────────────────────────

    public function setFilter(string $action): void
    {
        $this->filterAction = $action;
        $this->sitePage     = 1;
    }

    public function setScreenFilter(string $action): void
    {
        $this->screenFilterAction = $action;
        $this->screenPage         = 1;
    }

    public function getSummary(): array
    {
        $s = $this->preview['summary'] ?? [];

        // New format from SiteImportService (sites_new / screens_new / …)
        if (isset($s['sites_new'])) {
            $s['sites_total']   = ($s['sites_new']   ?? 0) + ($s['sites_update']   ?? 0) + ($s['sites_error']   ?? 0);
            $s['screens_total'] = ($s['screens_new'] ?? 0) + ($s['screens_update'] ?? 0) + ($s['screens_error'] ?? 0);
            return $s;
        }

        // Legacy / fallback
        return [
            'sites_new'      => $s['new']    ?? 0,
            'sites_update'   => $s['update'] ?? 0,
            'sites_error'    => $s['error']  ?? 0,
            'sites_skip'     => $s['skip']   ?? 0,
            'sites_total'    => ($s['new'] ?? 0) + ($s['update'] ?? 0) + ($s['error'] ?? 0),
            'screens_new'    => 0,
            'screens_update' => 0,
            'screens_error'  => 0,
            'screens_skip'   => 0,
            'screens_total'  => 0,
        ];
    }

    // ── Sites ─────────────────────────────────────────────────────────────────

    public function getFilteredSites(): array
    {
        return array_slice($this->applyFilter(), ($this->sitePage - 1) * $this->perPage, $this->perPage);
    }

    public function getSiteTotal(): int
    {
        return count($this->applyFilter());
    }

    public function hasNextPage(): bool
    {
        return ($this->sitePage * $this->perPage) < $this->getSiteTotal();
    }

    private function applyFilter(): array
    {
        $sites = $this->preview['sites'] ?? [];
        if ($this->filterAction === 'all') {
            return $sites;
        }
        return array_values(array_filter($sites, fn($s) => $s['action'] === $this->filterAction));
    }

    // ── Screens ───────────────────────────────────────────────────────────────

    public function getFilteredScreens(): array
    {
        return array_slice($this->applyScreenFilter(), ($this->screenPage - 1) * $this->perPage, $this->perPage);
    }

    public function getScreenTotal(): int
    {
        return count($this->applyScreenFilter());
    }

    public function hasScreenNextPage(): bool
    {
        return ($this->screenPage * $this->perPage) < $this->getScreenTotal();
    }

    private function applyScreenFilter(): array
    {
        $screens = $this->preview['screens'] ?? [];
        if ($this->screenFilterAction === 'all') {
            return $screens;
        }
        return array_values(array_filter($screens, fn($s) => $s['action'] === $this->screenFilterAction));
    }

    // ─────────────────────────────────────────────────────────────────────────

    protected function getFormActions(): array
    {
        return [];
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Tìm file upload và copy vào storage/app/private/imports/sites/ để xử lý.
     *
     * Laravel 11 thay đổi disk layout:
     *   local  disk → storage/app/private/   (private, không serve qua URL)
     *   public disk → storage/app/public/    (serve qua /storage symlink)
     *
     * Livewire temp upload có thể nằm ở NHIỀU nơi tuỳ cấu hình server:
     *   - storage/app/private/livewire-tmp/  (khi default disk = local)
     *   - storage/app/public/livewire-tmp/   (khi default disk = public / FILESYSTEM_DISK=public)
     *   - storage/app/public/                (khi livewire tmp directory = null/root)
     *
     * Strategy: tìm file trên cả 3 disk (local, public, default), với nhiều path format,
     * rồi copy vào local disk imports/sites/ để xử lý an toàn.
     */
    private function resolveUploadedPath(mixed $file): ?string
    {
        // ── Case 1: TemporaryUploadedFile object (chưa serialize) ────────────
        if ($file instanceof TemporaryUploadedFile) {
            $path = $file->storeAs('imports/sites', Str::ulid() . '.xlsx', 'local');
            return $path ?: null;
        }

        if (! is_string($file) || $file === '') {
            return null;
        }

        $cleanFile = ltrim($file, '/\\');

        // ── Case 2: Filament đã storeFiles() vào local disk (happy path) ─────
        // Với ->disk('local')->directory('imports/sites')->storeFiles(), Filament
        // lưu file tại 'imports/sites/xxx.xlsx' trên local disk và trả về path đó.
        if (\Storage::disk('local')->exists($cleanFile)) {
            return $cleanFile;
        }
        $basename  = basename($cleanFile);

        // Đích lưu cuối cùng (luôn trên local disk để không expose ra web)
        $dest = 'imports/sites/' . Str::ulid() . '.xlsx';

        // Livewire config
        $livewireTmpDir = rtrim(
            config('livewire.temporary_file_upload.directory', 'livewire-tmp') ?? 'livewire-tmp',
            '/'
        );

        // ── Danh sách disk sẽ thử (theo thứ tự ưu tiên) ─────────────────────
        // 1. local  → storage/app/private/
        // 2. public → storage/app/public/
        // 3. default disk (từ FILESYSTEM_DISK env)
        $disksToSearch = array_unique(array_filter([
            'local',
            'public',
            config('filesystems.default', 'local'),
            config('livewire.temporary_file_upload.disk') ?: null,
        ]));

        // ── Danh sách path sẽ thử trên mỗi disk ─────────────────────────────
        $pathCandidates = array_unique([
            $cleanFile,                                    // Path Filament trả về (có thể đã có dir)
            $basename,                                     // Chỉ filename
            $livewireTmpDir . '/' . $cleanFile,            // livewire-tmp + full path
            $livewireTmpDir . '/' . $basename,             // livewire-tmp + filename
        ]);

        foreach ($disksToSearch as $diskName) {
            $disk = \Storage::disk($diskName);

            foreach ($pathCandidates as $candidate) {
                try {
                    if ($disk->exists($candidate)) {
                        \Storage::disk('local')->put($dest, $disk->get($candidate));
                        return $dest;
                    }
                } catch (\Throwable) {
                    // Disk không hỗ trợ candidate này (metadata error, permission…) → thử tiếp
                    continue;
                }
            }
        }

        // ── Fallback: absolute path trên filesystem ───────────────────────────
        $absolutePaths = array_unique([
            $file,
            storage_path('app/public/' . $cleanFile),
            storage_path('app/private/' . $cleanFile),
            storage_path('app/' . $cleanFile),
            storage_path('app/public/' . $basename),
            storage_path('app/private/' . $basename),
            storage_path('app/public/' . $livewireTmpDir . '/' . $basename),
            storage_path('app/private/' . $livewireTmpDir . '/' . $basename),
        ]);

        foreach ($absolutePaths as $absPath) {
            if (file_exists($absPath) && is_file($absPath)) {
                \Storage::disk('local')->put($dest, file_get_contents($absPath));
                return $dest;
            }
        }

        return null;
    }
}
