<?php

namespace App\Filament\Publisher\Resources\BookingInboxResource\Pages;

use App\Filament\Publisher\Resources\BookingInboxResource;
use Filament\Resources\Pages\ListRecords;

class ListBookingInbox extends ListRecords
{
    protected static string $resource = BookingInboxResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
