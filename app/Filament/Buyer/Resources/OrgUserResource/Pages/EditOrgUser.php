<?php

namespace App\Filament\Buyer\Resources\OrgUserResource\Pages;

use App\Filament\Buyer\Resources\OrgUserResource;
use Filament\Resources\Pages\EditRecord;

class EditOrgUser extends EditRecord
{
    protected static string $resource = OrgUserResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
