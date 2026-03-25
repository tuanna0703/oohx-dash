<?php

namespace App\Http\Responses;

use Filament\Http\Responses\Auth\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): mixed
    {
        $user = auth()->user();

        $url = $user->hasRole('super_admin') ? '/admin' : '/publisher';

        return redirect()->to($url);
    }
}
