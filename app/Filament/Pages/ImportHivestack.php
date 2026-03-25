<?php
namespace App\Filament\Pages;

use App\Models\Owner;
use App\Services\ScreenRegistryService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ImportHivestack extends Page
{

    protected static ?string $navigationGroup = 'Tools';
    protected static ?string $navigationLabel = 'Import Hivestack';
    protected static ?int $navigationSort = 10;
    protected static string $view = 'filament.pages.import-hivestack';

    public ?array $data = [];
    public ?array $results = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Import Hivestack Bulk Sheet')
                ->description('Upload file xlsx theo format Hivestack (TrueView_Update_New_Inventory.xlsx)')
                ->schema([
                    Forms\Components\Select::make('owner_id')
                        ->label('Media Owner')
                        ->options(Owner::active()->pluck('name', 'id'))
                        ->searchable()->required(),
                    Forms\Components\FileUpload::make('file')
                        ->label('Hivestack Bulk Sheet (.xlsx)')
                        ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                        ->maxSize(10240)
                        ->required()
                        ->helperText('Sheets cần có: 1b. Site List, 2b. Screen List, Impression Multipliers'),
                ]),
        ])->statePath('data');
    }

    public function import(ScreenRegistryService $registry): void
    {
        $data = $this->form->getState();

        /** @var TemporaryUploadedFile $file */
        $file    = $data['file'];
        $path    = $file->store('imports/hivestack');
        $results = $registry->importHivestackXlsx(storage_path('app/' . $path), $data['owner_id']);

        $this->results = $results;

        $errCount = count($results['errors'] ?? []);
        if ($errCount === 0) {
            Notification::make()
                ->title("Import thành công!")
                ->body("{$results['sites']} sites, {$results['screens']} screens đã được import.")
                ->success()->send();
        } else {
            Notification::make()
                ->title("Import hoàn tất với {$errCount} lỗi")
                ->body(implode("\n", array_slice($results['errors'], 0, 5)))
                ->warning()->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('import')
                ->label('Import Now')
                ->submit('import')
                ->color('primary'),
        ];
    }
}
