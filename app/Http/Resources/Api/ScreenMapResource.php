<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScreenMapResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $spec      = $this->spec;
        $inventory = $this->inventory;
        $site      = $this->site;

        return [
            'screen_id'  => $this->external_id ?? $this->uuid,
            'name'       => $this->name,
            'venue_type' => $inventory?->venue_type,
            'screen_type'=> $this->resolveScreenType($spec, $inventory),
            'size'       => $this->resolveSize($spec),
            'location'   => [
                'address' => $site?->address,
                'lat'     => $site ? (float) $site->lat : null,
                'lng'     => $site ? (float) $site->lon : null,
            ],
        ];
    }

    private function resolveScreenType($spec, $inventory): string
    {
        $widthCm  = $spec?->width_cm ?? 0;
        $heightCm = $spec?->height_cm ?? 0;

        if ($widthCm >= 300 || $heightCm >= 300) {
            return 'billboard';
        }

        if ($inventory?->venue_type === 'outdoor') {
            return 'led';
        }

        return 'lcd';
    }

    private function resolveSize($spec): ?string
    {
        if (!$spec?->width_cm || !$spec?->height_cm) {
            return null;
        }

        $w = round($spec->width_cm / 100, 1);
        $h = round($spec->height_cm / 100, 1);

        return "{$w}×{$h}m";
    }
}
