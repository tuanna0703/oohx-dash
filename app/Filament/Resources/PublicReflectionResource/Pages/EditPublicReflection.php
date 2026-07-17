<?php

namespace App\Filament\Resources\PublicReflectionResource\Pages;

use App\Filament\Resources\PublicReflectionResource;
use Filament\Resources\Pages\EditRecord;

class EditPublicReflection extends EditRecord
{
    protected static string $resource = PublicReflectionResource::class;

    /**
     * Ghi lại ai là người xử lý. Cần cho việc trả lời cơ quan quản lý về quy trình
     * tiếp nhận phản ánh, và `handled_by_user_id` không có cách nào tự điền.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['handled_by_user_id'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
