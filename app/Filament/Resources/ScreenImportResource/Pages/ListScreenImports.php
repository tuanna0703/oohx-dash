<?php

namespace App\Filament\Resources\ScreenImportResource\Pages;

use App\Filament\Resources\ScreenImportResource;
use App\Models\ScreenImport;
use App\Models\Owner;
use App\Services\ScreenImport\ScreenImportService;
use App\Services\ScreenImport\TemplateGenerator;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ListScreenImports extends ListRecords
{
    protected static string $resource = ScreenImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('downloadTemplate')
                ->label('Download template')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(function () {
                    try {
                        $path = app(TemplateGenerator::class)->generate();
                        return response()->download($path, 'screen-import-template.xlsx')->deleteFileAfterSend();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Template generation failed')
                            ->body($e->getMessage())
                            ->danger()->send();
                        return null;
                    }
                }),

            Actions\Action::make('upload')
                ->label('Upload Excel / CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->modalHeading('Upload screen import file')
                ->modalDescription('Hệ thống sẽ phân tích file và AI sẽ đề xuất mapping cột → field.')
                ->modalSubmitActionLabel('Upload & Analyze')
                ->form([
                    Forms\Components\Select::make('owner_id')
                        ->label('Media Owner')
                        ->required()
                        ->options(fn () => Owner::orderBy('name')->pluck('name', 'id')->all())
                        ->default(fn () => auth()->user()?->current_owner_id)
                        ->searchable()
                        ->helperText('Screens sẽ thuộc owner này. Admin cần chọn rõ; publisher auto-fill.'),

                    Forms\Components\FileUpload::make('file')
                        ->label('Excel / CSV file')
                        ->required()
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // xlsx
                            'application/vnd.ms-excel',  // xls
                            'text/csv',
                            'text/plain', // some csv come as text/plain
                        ])
                        ->maxSize(10 * 1024) // 10 MB (KB units in Filament)
                        ->disk('local')
                        ->directory('screen-imports/' . now()->format('Y-m'))
                        ->preserveFilenames(false)
                        ->helperText('Tối đa 10MB, 5000 dòng. Row 1 phải là header.'),

                    Forms\Components\Select::make('upsert_mode')
                        ->label('Xử lý screen đã tồn tại')
                        ->options([
                            'skip'   => 'Bỏ qua (chỉ tạo mới)',
                            'update' => 'Cập nhật (upsert theo external_id)',
                        ])
                        ->default('skip')
                        ->required()
                        ->helperText('Áp dụng khi external_id đã có trong DB.'),
                ])
                ->action(function (array $data) {
                    $filePath = $data['file'];
                    $originalName = basename($filePath);

                    $import = ScreenImport::create([
                        'id'                => (string) Str::uuid(),
                        'owner_id'          => $data['owner_id'],
                        'uploaded_by'       => auth()->id(),
                        'original_filename' => $originalName,
                        'file_path'         => $filePath,
                        'status'            => 'uploaded',
                        'upsert_mode'       => $data['upsert_mode'] ?? 'skip',
                    ]);

                    try {
                        $service = app(ScreenImportService::class);
                        $service->analyze($import);

                        if ($import->fresh()->status === 'failed') {
                            Notification::make()
                                ->title('File không hợp lệ')
                                ->body($import->error_summary)
                                ->danger()->send();
                            return;
                        }

                        $service->proposeMapping($import);

                        Notification::make()
                            ->title('File đã được phân tích')
                            ->body("AI đã đề xuất mapping cho {$import->total_rows} rows. Xem & chỉnh sửa mapping.")
                            ->success()->send();

                        $this->redirect(
                            ScreenImportResource::getUrl('view', ['record' => $import->id])
                        );
                    } catch (\Throwable $e) {
                        $import->update([
                            'status'        => 'failed',
                            'error_summary' => $e->getMessage(),
                        ]);
                        // Keep the uploaded file for debugging
                        Notification::make()
                            ->title('Upload failed')
                            ->body($e->getMessage())
                            ->danger()->persistent()->send();
                    }
                }),
        ];
    }
}
