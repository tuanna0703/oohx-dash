<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuthTokenRequest;
use App\Models\ApiClient;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function token(AuthTokenRequest $request): JsonResponse
    {
        $client = ApiClient::where('client_id', $request->client_id)->first();

        // Validate client exists, secret matches, and client is active
        if (! $client || ! $client->validateSecret($request->client_secret) || ! $client->active) {
            return response()->json([
                'error'             => 'invalid_client',
                'error_description' => 'Client authentication failed',
            ], 401);
        }

        // Resolve final scopes: intersection of requested vs granted
        $scopes = $client->allowedScopes($request->requestedScopes());

        // Revoke old tokens to avoid accumulation
        $client->revokeAllTokens();

        $ttl     = config('tapon.token_ttl_minutes', 60);
        $token   = $client->createToken('api', $scopes, now()->addMinutes($ttl));

        return response()->json([
            'access_token' => $token->plainTextToken,
            'token_type'   => 'Bearer',
            'expires_in'   => $ttl * 60,
            'scope'        => implode(' ', $scopes),
        ]);
    }
}
