<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;

class ApiClient extends Model
{
    use HasFactory, HasApiTokens;

    protected $fillable = [
        'client_id',
        'client_secret',
        'name',
        'scopes',
        'active',
    ];

    protected $hidden = ['client_secret'];

    protected $casts = [
        'scopes' => 'array',
        'active' => 'boolean',
    ];

    // ── Helpers ────────────────────────────────────────────

    public function validateSecret(string $plain): bool
    {
        return Hash::check($plain, $this->client_secret);
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes ?? []);
    }

    /**
     * Revoke all existing tokens for this client.
     */
    public function revokeAllTokens(): void
    {
        $this->tokens()->delete();
    }

    /**
     * Scopes that this client can actually request
     * (intersection of requested vs granted).
     */
    public function allowedScopes(array $requested): array
    {
        if (empty($requested)) {
            return $this->scopes;
        }

        return array_values(array_intersect($requested, $this->scopes));
    }
}
