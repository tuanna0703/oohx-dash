<?php

namespace App\Providers;

use App\Models\OrganizationUser;
use App\Models\Owner;
use App\Models\OwnerUser;
use App\Models\Screen;
use App\Observers\ScreenObserver;
use App\Policies\OrganizationUserPolicy;
use App\Policies\OwnerPolicy;
use App\Policies\OwnerUserPolicy;
use Filament\Http\Responses\Auth\Contracts\LoginResponse as LoginResponseContract;
use App\Http\Responses\LoginResponse;
use Illuminate\Support\Facades\Gate;
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

        Gate::policy(Owner::class, OwnerPolicy::class);
        Gate::policy(OwnerUser::class, OwnerUserPolicy::class);
        Gate::policy(OrganizationUser::class, OrganizationUserPolicy::class);

        // Fix Livewire upload CORS: set APP_URL to match current request domain
        // so Livewire uploads go to the same origin (oohx.test or dash.oohx.test)
        if ($this->app->runningInConsole() === false && request()->getHost()) {
            $scheme = request()->getScheme();
            $host = request()->getHost();
            $port = request()->getPort();
            $url = $scheme . '://' . $host;
            if ($port && $port !== 80 && $port !== 443) {
                $url .= ':' . $port;
            }
            config([
                'app.url' => $url,
                'filesystems.disks.public.url' => $url . '/storage',
            ]);
            url()->forceRootUrl($url);

            if ($scheme === 'https') {
                \Illuminate\Support\Facades\URL::forceScheme('https');
            }
        }
    }
}
