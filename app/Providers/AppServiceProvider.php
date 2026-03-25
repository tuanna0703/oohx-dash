<?php

namespace App\Providers;

use App\Models\Screen;
use App\Observers\ScreenObserver;
use Filament\Http\Responses\Auth\Contracts\LoginResponse as LoginResponseContract;
use App\Http\Responses\LoginResponse;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LoginResponseContract::class, LoginResponse::class);
    }

    public function boot(): void
    {
        Screen::observe(ScreenObserver::class);
    }
}
