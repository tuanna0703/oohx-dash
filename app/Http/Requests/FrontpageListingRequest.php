<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FrontpageListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q'             => 'nullable|string|max:100',
            'city'          => 'nullable|array|max:10',
            'city.*'        => 'string|max:50',
            'province'      => 'nullable|array|max:10',
            'province.*'    => 'string|max:50',
            'venue_type'    => 'nullable|array|max:10',
            'venue_type.*'  => 'string|max:100',
            'screen_type'   => 'nullable|array|max:5',
            'screen_type.*' => 'in:billboard,led,lcd',
            'orientation'   => 'nullable|array|max:3',
            'orientation.*' => 'in:landscape,portrait,square',
            'network'       => 'nullable|array|max:10',
            'network.*'     => 'string|max:100',
            'owner'         => 'nullable|array|max:10',
            'owner.*'       => 'string|max:100',
            'district'      => 'nullable|array|max:20',
            'district.*'    => 'string|max:50',
            'region'        => 'nullable|array|max:3',
            'region.*'      => 'in:north,central,south',
            'sort'          => 'nullable|in:price_asc,price_desc,newest',
            'page'          => 'nullable|integer|min:1',
        ];
    }
}
