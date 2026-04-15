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
     * Add product to cart. Supports 3 listing modes:
     * - package_only: mua cả gói (no screen selection)
     * - individual_only: chọn từng screen
     * - both: chọn gói hoặc chọn lẻ
     */
    public function addProduct(Cart $cart, string $productId, array $data = []): CartItem
    {
        $product = \App\Models\Product::with('screens.inventory')->findOrFail($productId);

        $selectedScreenIds = $data['selected_screen_ids'] ?? null;
        $buyMode = $data['buy_mode'] ?? ($product->listing_mode === 'package_only' ? 'package' : 'individual');
        $startDate = $data['start_date'] ?? now()->addDays(7)->toDateString();
        $endDate = $data['end_date'] ?? now()->addDays(37)->toDateString();
        $sovPct = $data['share_of_voice_pct'] ?? 100;

        if ($buyMode === 'package') {
            // Mua cả gói
            $quantity = 1;
            $cost = (float) $product->floor_price;
            $impressions = 0;
            $selectedScreenIds = null;
        } else {
            // Mua lẻ — dùng individual_price nếu có
            $screenIds = is_array($selectedScreenIds) ? $selectedScreenIds : [];
            $quantity = max(1, count($screenIds));
            $unitPrice = (float) ($product->individual_price ?: $product->floor_price);
            $cost = $unitPrice * $quantity;
            $impressions = 0;

            // Tính impressions nếu có
            foreach ($product->screens as $screen) {
                if (in_array($screen->id, $screenIds)) {
                    $impressions += $screen->inventory?->weekly_impressions ?? 0;
                }
            }
        }

        // Use product_id as unique key (1 product = 1 cart item)
        return CartItem::updateOrCreate(
            ['cart_id' => $cart->id, 'product_id' => $productId],
            [
                'screen_id' => $product->screens->first()?->id,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'spot_length' => $data['spot_length'] ?? 15,
                'quantity' => $quantity,
                'selected_screen_ids' => $selectedScreenIds,
                'selected_region' => $data['selected_region'] ?? null,
                'share_of_voice_pct' => $sovPct,
                'estimated_impressions' => $impressions,
                'estimated_cost' => $cost,
                'notes' => $data['notes'] ?? null,
            ]
        );
    }

    /**
     * Add single screen to cart (legacy / direct screen booking).
     */
    public function addItem(Cart $cart, string $screenId, array $data = []): CartItem
    {
        $screen = Screen::with('inventory')->findOrFail($screenId);

        $startDate = $data['start_date'] ?? now()->addDays(7)->toDateString();
        $endDate = $data['end_date'] ?? now()->addDays(37)->toDateString();
        $spotLength = $data['spot_length'] ?? $screen->inventory?->spot_length ?? 15;
        $sovPct = $data['share_of_voice_pct'] ?? 100;

        $estimated = $this->estimateCost($screen, $startDate, $endDate, $sovPct);

        return CartItem::updateOrCreate(
            ['cart_id' => $cart->id, 'screen_id' => $screenId],
            [
                'product_id' => $data['product_id'] ?? null,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'spot_length' => $spotLength,
                'quantity' => (int) ($data['quantity'] ?? 1),
                'selected_screen_ids' => $data['selected_screen_ids'] ?? null,
                'selected_region' => $data['selected_region'] ?? null,
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
     *
     * floor_cpm is a MONTHLY rate (displayed as "₫/tháng" on frontpage).
     * Convert days → months for pricing. Impressions still use daily calc.
     */
    public function estimateCost(Screen $screen, string $startDate, string $endDate, int $sovPct = 100): array
    {
        $monthlyRate = $screen->inventory?->floor_cpm ?? 0;
        $dailyImpressions = $screen->inventory?->daily_impressions ?? 0;

        $start = now()->parse($startDate);
        $end = now()->parse($endDate);
        $days = max(1, $start->diffInDays($end) + 1);

        // Convert days to months (30-day basis) for pricing
        $months = $days / 30;

        $totalImpressions = (int) round($dailyImpressions * $days * ($sovPct / 100));
        $cost = round($monthlyRate * $months * ($sovPct / 100), 2);

        return [
            'impressions' => $totalImpressions,
            'cost' => $cost,
            'days' => $days,
            'months' => round($months, 2),
            'monthly_rate' => $monthlyRate,
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
