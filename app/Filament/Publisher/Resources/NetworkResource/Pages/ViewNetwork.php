<?php

namespace App\Filament\Publisher\Resources\NetworkResource\Pages;

use App\Filament\Publisher\Resources\NetworkResource;
use App\Filament\Shared\Pages\BaseViewNetwork;
use App\Models\Screen;

class ViewNetwork extends BaseViewNetwork
{
    protected static string $resource = NetworkResource::class;

    protected function screenViewUrl(Screen $screen): string
    {
        return \App\Filament\Publisher\Resources\ScreenResource::getUrl('view', ['record' => $screen->id]);
    }
}
