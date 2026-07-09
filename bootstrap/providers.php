<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\Filament\PublisherPanelProvider::class,
    App\Providers\Filament\BuyerPanelProvider::class,
];
