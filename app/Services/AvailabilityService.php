<?php

namespace App\Services;

use App\Models\BookingLine;
use App\Models\Screen;

class AvailabilityService
{
    /**
     * Get total booked SOV % for a screen in a date range.
     * Excludes cancelled/rejected lines.
     */
    public function getBookedSOV(string $screenId, string $startDate, string $endDate): int
    {
        return (int) BookingLine::where('screen_id', $screenId)
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->where(function ($q) use ($startDate, $endDate) {
                $q->where('start_date', '<=', $endDate)
                  ->where('end_date', '>=', $startDate);
            })
            ->sum('share_of_voice_pct');
    }

    /**
     * Get remaining available SOV % for a screen.
     */
    public function getRemainingSOV(string $screenId, string $startDate, string $endDate): int
    {
        $maxSOV = Screen::find($screenId)?->inventory?->share_of_voice_max_pct ?? 100;
        return max(0, $maxSOV - $this->getBookedSOV($screenId, $startDate, $endDate));
    }

    /**
     * Check if a screen is available for booking with given SOV.
     */
    public function isAvailable(string $screenId, string $startDate, string $endDate, int $sovPct = 100): bool
    {
        return $this->getRemainingSOV($screenId, $startDate, $endDate) >= $sovPct;
    }

    /**
     * Validate all booking lines in a campaign for SOV conflicts.
     * Returns array of conflicts: ['screen_id' => ..., 'available' => ..., 'requested' => ...]
     */
    public function validateCampaign(string $campaignId): array
    {
        $lines = BookingLine::where('campaign_id', $campaignId)
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->get();

        $conflicts = [];
        foreach ($lines as $line) {
            // Exclude this line's own SOV from calculation
            $otherBookedSOV = BookingLine::where('screen_id', $line->screen_id)
                ->where('id', '!=', $line->id)
                ->whereNotIn('status', ['cancelled', 'rejected'])
                ->where('start_date', '<=', $line->end_date)
                ->where('end_date', '>=', $line->start_date)
                ->sum('share_of_voice_pct');

            $maxSOV = $line->screen?->inventory?->share_of_voice_max_pct ?? 100;
            $available = max(0, $maxSOV - $otherBookedSOV);

            if ($line->share_of_voice_pct > $available) {
                $conflicts[] = [
                    'booking_line_id' => $line->id,
                    'screen_id'       => $line->screen_id,
                    'screen_name'     => $line->screen?->name,
                    'requested'       => $line->share_of_voice_pct,
                    'available'       => $available,
                    'dates'           => $line->start_date->format('d/m') . ' → ' . $line->end_date->format('d/m'),
                ];
            }
        }

        return $conflicts;
    }
}
