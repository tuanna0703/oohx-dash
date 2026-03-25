<?php

namespace App\Filament\Concerns;

use App\Models\Owner;
use App\Services\ScreenRegistryService;
use Filament\Forms;
use Filament\Forms\Form;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Shared trait cho Admin và Publisher ImportHivestack upload page.
 * Mỗi Page implement 3 abstract methods bên dưới.
 */
trait HasImportPreview
{
    public ?array $data = [];

    public function mountHasImportPreview(): void
    {
        $this->form->fill();
    }

    // ── Abstract: implement trong từng Page ───────────────────────────────────

    /** Admin: owner_id từ form select. Publisher: user->current_owner_id */
    abstract protected function resolveOwnerId(): string;

    /** URL redirect sang trang Preview: '/admin/import/preview' */
    abstract protected function previewUrl(): string;

    /** Admin = true (hiện Select owner). Publisher = false */
    abstract protected function showOwnerSelect(): bool;

    // ── Form ─────────────────────────────────────────────────────────────────

    public function form(Form $form): Form
    {
        $fields = [];

        if ($this->showOwnerSelect()) {
            $fields[] = Forms\Components\Select::make('owner_id')
                ->label('Media Owner')
                ->options(Owner::active()->pluck('name', 'id'))
                ->searchable()
                ->required();
        }

        $fields[] = Forms\Components\FileUpload::make('file')
            ->label('File Excel (.xlsx)')
            ->acceptedFileTypes([
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])
            ->disk('local')
            ->directory('imports/hivestack')
            ->maxSize(10240)
            ->required()
            ->storeFiles()
            ->helperText('Hỗ trợ Hivestack format và AdTRUE SSP template');

        return $form
            ->schema([
                Forms\Components\Section::make('Upload Inventory File')
                    ->description('File sẽ được phân tích và so sánh với database — chưa lưu gì cho đến khi bạn xác nhận.')
                    ->schema($fields),
            ])
            ->statePath('data');
    }

    // ── Submit: parse → session → redirect ───────────────────────────────────

    public function runPreview(ScreenRegistryService $registry): void
    {
        $formData = $this->form->getState();
        $file     = $formData['file'];

        if ($file instanceof TemporaryUploadedFile) {
            // Upload trực tiếp — Livewire chưa serialize lại
            $storedPath = $file->store('imports/hivestack');
        } elseif (is_string($file) && \Storage::disk('local')->exists($file)) {
            // storeFiles() đã lưu sẵn, $file là path string
            $storedPath = $file;
        } else {
            // Livewire tmp string — move thủ công sang storage chính thức
            $tmpDisk = config('livewire.temporary_file_upload.disk', 'local');
            $tmpDir  = rtrim(config('livewire.temporary_file_upload.directory', 'livewire-tmp'), '/');
            $tmpPath = $tmpDir . '/' . ltrim((string) $file, '/');

            $storedPath = 'imports/hivestack/' . \Str::ulid() . '.xlsx';

            if (\Storage::disk($tmpDisk)->exists($tmpPath)) {
                \Storage::disk('local')->put($storedPath, \Storage::disk($tmpDisk)->get($tmpPath));
            } else {
                throw new \RuntimeException('Không tìm thấy file upload. Vui lòng thử lại.');
            }
        }

        $ownerId = $this->showOwnerSelect()
            ? $formData['owner_id']
            : $this->resolveOwnerId();

        $preview = $registry->previewImport(
            storage_path('app/' . $storedPath),
            $ownerId
        );

        $token = \Str::random(24);

        session()->put("import_{$token}", [
            'stored_path' => $storedPath,
            'owner_id'    => $ownerId,
            'preview'     => $preview,
        ]);

        $this->redirect($this->previewUrl() . '?token=' . $token, navigate: true);
    }

    protected function getFormActions(): array
    {
        return [];
    }
}
