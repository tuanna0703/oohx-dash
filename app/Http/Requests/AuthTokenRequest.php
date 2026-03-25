<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AuthTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public endpoint
    }

    public function rules(): array
    {
        return [
            'client_id'     => ['required', 'string'],
            'client_secret' => ['required', 'string'],
            'grant_type'    => ['required', 'in:client_credentials'],
            'scope'         => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * Trả về scopes từ request dưới dạng array.
     * Input: "inventory booking reporting" → ["inventory", "booking", "reporting"]
     */
    public function requestedScopes(): array
    {
        $scope = $this->string('scope')->trim()->value();

        if (empty($scope)) {
            return [];
        }

        return array_filter(explode(' ', $scope));
    }
}
