<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTokenAbility
{
    /**
     * Kiểm tra token có đủ ability (scope) không.
     * Dùng như: Route::middleware('ability:inventory')
     */
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $token = $request->user()?->currentAccessToken();

        if (! $token) {
            return response()->json([
                'error'   => 'unauthorized',
                'message' => 'Token not found',
            ], 401);
        }

        foreach ($abilities as $ability) {
            if (! $token->can($ability)) {
                return response()->json([
                    'error'   => 'forbidden',
                    'message' => "Token missing required scope: {$ability}",
                ], 403);
            }
        }

        return $next($request);
    }
}
