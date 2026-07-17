<?php

namespace App\Filament\Resources\OwnerReviewResource\Pages;

use App\Filament\Resources\OwnerReviewResource;
use Filament\Resources\Pages\EditRecord;

class EditOwnerReview extends EditRecord
{
    protected static string $resource = OwnerReviewResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
