<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ScreenMapCollection extends ResourceCollection
{
    public $collects = ScreenMapResource::class;

    public function toArray(Request $request): array
    {
        return [
            'total' => $this->collection->count(),
            'data'  => $this->collection,
        ];
    }
}
