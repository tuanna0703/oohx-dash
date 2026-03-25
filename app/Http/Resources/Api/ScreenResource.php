<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScreenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $spec      = $this->spec;
        $inventory = $this->inventory;
        $site      = $this->site;
        $owner     = $this->owner;

        return [
            'screen_id'         => $this->external_id ?? $this->uuid,
            'name'              => $this->name,
            'owner_id'          => 'OWN-' . $this->owner_id,
            'owner_name'        => $owner?->name,
            'screen_type'       => $this->resolveScreenType($spec, $inventory),
            'venue_type'        => $inventory?->venue_type,
            'location'          => [
                'address'  => $site?->address,
                'city'     => $site?->city,
                'district' => $site?->region,
                'lat'      => $site ? (float) $site->lat : null,
                'lng'      => $site ? (float) $site->lon : null,
            ],
            'specs'             => [
                'width_px'    => $spec?->width_px,
                'height_px'   => $spec?->height_px,
                'width_m'     => $spec?->width_cm ? round($spec->width_cm / 100, 2) : null,
                'height_m'    => $spec?->height_cm ? round($spec->height_cm / 100, 2) : null,
                'orientation' => $spec?->orientation,
                'resolution'  => $spec?->resolution_preset,
            ],
            'slot_duration_sec' => $inventory?->spot_length ?? 15,
            'slots_per_loop'    => $this->resolveSlotsPerLoop($inventory),
            'operating_hours'   => $this->resolveOperatingHours($inventory),
            'price_per_slot_vnd'=> $inventory?->floor_cpm ? (int) $inventory->floor_cpm : 0,
            'min_booking_days'  => 7,
            'photos'            => $spec?->photo_url ? [$spec->photo_url] : [],
            'status'            => $this->active ? 'active' : 'inactive',
            'updated_at'        => $this->updated_at?->toIso8601String(),
        ];
    }

    // ── Helpers ─────────────────────────────────────────────

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

    private function resolveSlotsPerLoop($inventory): int
    {
        if (! $inventory) return 8;

        $loop = $inventory->loop_length;
        $spot = $inventory->spot_length;

        if ($loop && $spot > 0) {
            return (int) round($loop / $spot);
        }

        return 8;
    }

    /**
     * Transform operating_hours từ DB format → TapON format.
     *
     * DB:   { "mon": {"open":"08:00","close":"22:00"}, "tue": "closed", ... }
     * TapON: { "open": "08:00", "close": "22:00", "days": ["mon","tue",...] }
     */
    private function resolveOperatingHours($inventory): array
    {
        $hours = $inventory?->operating_hours;

        if (empty($hours)) {
            return ['open' => '08:00', 'close' => '22:00', 'days' => ['mon','tue','wed','thu','fri','sat','sun']];
        }

        $openTime  = '08:00';
        $closeTime = '22:00';
        $activeDays = [];

        foreach ($hours as $day => $value) {
            if ($value === 'closed' || empty($value)) continue;

            $activeDays[] = $day;

            if (is_array($value) && isset($value['open'], $value['close'])) {
                $openTime  = $value['open'];
                $closeTime = $value['close'];
            }
        }

        return [
            'open'  => $openTime,
            'close' => $closeTime,
            'days'  => $activeDays,
        ];
    }
}
