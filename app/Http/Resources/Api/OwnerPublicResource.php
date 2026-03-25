<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OwnerPublicResource extends JsonResource
{
    private bool $includeAbout = false;

    /** Dùng cho ownerDetail() — thêm field `about` */
    public function withAbout(): array
    {
        $this->includeAbout = true;
        return $this->toArray(request());
    }

    public function toArray(Request $request): array
    {
        $data = [
            'owner_id'     => $this->slug,
            'name'         => $this->name,
            'tagline'      => $this->tagline,
            'logo_url'     => $this->logo_url,
            'cover_url'    => $this->cover_url,
            'screen_count' => (int) ($this->screen_count ?? 0),
            'city_count'   => (int) ($this->city_count ?? 0),
            'venue_types'  => $this->venue_types_list ?? [],
            'website'      => $this->website,
            'email'        => $this->email,
            'phone'        => $this->phone,
            'founded'      => $this->founded,
            'featured'     => (bool) $this->featured,
            'headquarters' => $this->headquarters_lat ? [
                'lat' => (float) $this->headquarters_lat,
                'lng' => (float) $this->headquarters_lng,
            ] : null,
        ];

        if ($this->includeAbout) {
            $data['about'] = $this->about;
        }

        return $data;
    }
}
