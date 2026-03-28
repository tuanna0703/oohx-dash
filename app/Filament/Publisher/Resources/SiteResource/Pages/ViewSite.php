<?php

namespace App\Filament\Publisher\Resources\SiteResource\Pages;

use App\Filament\Publisher\Resources\SiteResource;
use App\Filament\Shared\Pages\BaseViewSite;
use App\Models\Screen;

class ViewSite extends BaseViewSite
{
    protected static string $resource = SiteResource::class;

    protected function screenViewUrl(Screen $screen): string
    {
        return \App\Filament\Publisher\Resources\ScreenResource::getUrl('view', ['record' => $screen->id]);
    }
}
