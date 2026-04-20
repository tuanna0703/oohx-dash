<?php

namespace App\Filament\Resources\OohxConfig\AuditLogResource\Pages;

use App\Filament\Resources\OohxConfig\AuditLogResource;
use Filament\Resources\Pages\ListRecords;

class ListAuditLog extends ListRecords
{
    protected static string $resource = AuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return []; // append-only, no manual create
    }
}
