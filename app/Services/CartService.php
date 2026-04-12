<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Screen;
use App\Models\User;

class CartService
{
    /**
     * Get or create active cart for user.
     */
    public function getOrCreateCart(User $user): Cart
    {
        return Cart::firstOrCreate(
            ['user_id' => $user->id, 'status' => 'active'],
            [
                'organization_id' => $user->current_organization_id,
                'name' => 'My Plan',
            ]
        );
    }

    /**
     * Add screen to cart. Returns the cart item.
     */
    public function addItem(Cart $cart, string $screenId, array $data = []): CartItem
    {
        $screen = Screen::with('inventory')->findOrFail($screenId);

        $startDate = $data['start_date'] ?? now()->addDays(7)->toDateString();
        $endDate = $data['end_date'] ?? now()->addDays(37)->toDateString(); // ~1 month
        $spotLength = $data['spot_length'] ?? $screen->inventory?->spot_length ?? 15;
        $sovPct = $data['share_of_voice_pct'] ?? 100;

        $estimated = $this->estimateCost($screen, $startDate, $endDate, $sovPct);

        return CartItem::updateOrCreate(
            ['cart_id' => $cart->id, 'screen_id' => $screenId],
            [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'spot_length' => $spotLength,
                'share_of_voice_pct' => $sovPct,
                'estimated_impressions' => $estimated['impressions'],
                'estimated_cost' => $estimated['cost'],
                'notes' => $data['notes'] ?? null,
            ]
        );
    }

    /**
     * Remove item from cart.
     */
    public function removeItem(CartItem $item): void
    {
        $item->delete();
    }

    /**
     * Update cart item dates/SOV and recalculate cost.
     */
    public function updateItem(CartItem $item, array $data): CartItem
    {
        $screen = $item->screen()->with('inventory')->first();

        $startDate = $data['start_date'] ?? $item->start_date->toDateString();
        $endDate = $data['end_date'] ?? $item->end_date->toDateString();
        $sovPct = $data['share_of_voice_pct'] ?? $item->share_of_voice_pct;

        $estimated = $this->estimateCost($screen, $startDate, $endDate, $sovPct);

        $item->update([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'spot_length' => $data['spot_length'] ?? $item->spot_length,
            'share_of_voice_pct' => $sovPct,
            'estimated_impressions' => $estimated['impressions'],
            'estimated_cost' => $estimated['cost'],
            'notes' => $data['notes'] ?? $item->notes,
        ]);

        return $item->fresh();
    }

    /**
     * Estimate cost for a screen booking.
     */
    public function estimateCost(Screen $screen, string $startDate, string $endDate, int $sovPct = 100): array
    {
        $cpm = $screen->inventory?->floor_cpm ?? 0;
        $dailyImpressions = $screen->inventory?->daily_impressions ?? 0;

        $days = max(1, now()->parse($startDate)->diffInDays(now()->parse($endDate)) + 1);
        $totalImpressions = (int) round($dailyImpressions * $days * ($sovPct / 100));
        $cost = round($cpm * $days * ($sovPct / 100), 2);

        return [
            'impressions' => $totalImpressions,
            'cost' => $cost,
            'days' => $days,
            'cpm' => $cpm,
        ];
    }

    /**
     * Get cart item count for user (for badge).
     */
    public function getItemCount(User $user): int
    {
        $cart = Cart::where('user_id', $user->id)->where('status', 'active')->first();
        return $cart ? $cart->items()->count() : 0;
    }
}
