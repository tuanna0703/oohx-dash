<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'ability' => \App\Http\Middleware\CheckTokenAbility::class,
        ]);
        $middleware->encryptCookies(except: ['oohx_city']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API routes luôn trả JSON 401 thay vì redirect về route('login')
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error'   => 'unauthorized',
                    'message' => 'Token không hợp lệ hoặc đã hết hạn',
                ], 401);
            }
        });
    })->create();
