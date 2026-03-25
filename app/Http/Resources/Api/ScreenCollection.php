<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ScreenCollection extends ResourceCollection
{
    public $collects = ScreenResource::class;

    public function toArray(Request $request): array
    {
        return [
            'total' => $this->total(),
            'page'  => $this->currentPage(),
            'limit' => $this->perPage(),
            'data'  => $this->collection,
        ];
    }
}
