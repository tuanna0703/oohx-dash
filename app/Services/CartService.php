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
            $quantity = 1;
            $cost = (float) $product->floor_price;
            $impressions = 0;
            $selectedScreenIds = null;
        } else {
            $screenIds = is_array($selectedScreenIds) ? $selectedScreenIds : [];
            $quantity = max(1, count($screenIds));
            $unitPrice = (float) ($product->individual_price ?: $product->floor_price);
            $cost = $unitPrice * $quantity;
            $impressions = 0;

            foreach ($product->screens as $screen) {
                if (in_array($screen->id, $screenIds)) {
                    $impressions += $screen->inventory?->weekly_impressions ?? 0;
                }
            }
        }

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
     * Add single screen to cart (direct screen booking).
     *
     * Supports 2 pricing models:
     * - CPM:  buyer specifies booked_cpms → cost = floor_cpm × booked_cpms
     * - I/O:  buyer specifies duration_units + screen_count → cost = io_rate × screen_count × duration_units
     */
    public function addItem(Cart $cart, string $screenId, array $data = []): CartItem
    {
        $screen = Screen::with('inventory')->findOrFail($screenId);
        $inv = $screen->inventory;
        $pricingModel = $inv?->pricing_model ?? 'io';

        $startDate = $data['start_date'] ?? now()->addDays(7)->toDateString();
        $endDate = $data['end_date'] ?? now()->addDays(37)->toDateString();
        $spotLength = $data['spot_length'] ?? $inv?->spot_length ?? 15;
        $sovPct = $data['share_of_voice_pct'] ?? 100;

        $estimated = $this->estimateCost($screen, $data);

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
                // Pricing model snapshot
                'pricing_model' => $pricingModel,
                'booked_cpms' => $estimated['booked_cpms'] ?? null,
                'screen_count' => $estimated['screen_count'] ?? 1,
                'duration_units' => $estimated['duration_units'] ?? 1,
                'duration_unit' => $estimated['duration_unit'] ?? ($inv?->io_rate_unit ?? 'month'),
                'unit_price' => $estimated['unit_price'] ?? 0,
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
     * Update cart item and recalculate cost.
     */
    public function updateItem(CartItem $item, array $data): CartItem
    {
        $screen = $item->screen()->with('inventory')->first();

        $mergedData = array_merge([
            'start_date' => $item->start_date->toDateString(),
            'end_date' => $item->end_date->toDateString(),
            'share_of_voice_pct' => $item->share_of_voice_pct,
            'booked_cpms' => $item->booked_cpms,
            'screen_count' => $item->screen_count,
            'duration_units' => $item->duration_units,
        ], array_filter($data, fn ($v) => $v !== null));

        $estimated = $this->estimateCost($screen, $mergedData);

        $item->update([
            'start_date' => $mergedData['start_date'],
            'end_date' => $mergedData['end_date'],
            'spot_length' => $data['spot_length'] ?? $item->spot_length,
            'share_of_voice_pct' => $mergedData['share_of_voice_pct'],
            'estimated_impressions' => $estimated['impressions'],
            'estimated_cost' => $estimated['cost'],
            'booked_cpms' => $estimated['booked_cpms'] ?? $item->booked_cpms,
            'screen_count' => $estimated['screen_count'] ?? $item->screen_count,
            'duration_units' => $estimated['duration_units'] ?? $item->duration_units,
            'unit_price' => $estimated['unit_price'] ?? $item->unit_price,
            'notes' => $data['notes'] ?? $item->notes,
        ]);

        return $item->fresh();
    }

    /**
     * Estimate cost for a screen booking.
     *
     * CPM model:  cost = floor_cpm × booked_cpms
     * I/O model:  cost = io_rate × screen_count × duration_units
     */
    public function estimateCost(Screen $screen, array $data = []): array
    {
        $inv = $screen->inventory;
        $pricingModel = $inv?->pricing_model ?? 'io';

        $startDate = $data['start_date'] ?? now()->addDays(7)->toDateString();
        $endDate = $data['end_date'] ?? now()->addDays(37)->toDateString();
        $sovPct = (int) ($data['share_of_voice_pct'] ?? 100);

        $start = now()->parse($startDate);
        $end = now()->parse($endDate);
        $days = max(1, $start->diffInDays($end) + 1);

        $dailyImpressions = $inv?->daily_impressions ?? 0;
        $totalImpressions = (int) round($dailyImpressions * $days * ($sovPct / 100));

        if ($pricingModel === 'cpm') {
            // ── CPM: buyer mua số CPM, cost = đơn giá CPM × số CPM ──
            $floorCpm = (float) ($inv?->floor_cpm ?? 0);

            // Buyer có thể chỉ định số CPM, hoặc tự tính từ impressions
            $bookedCpms = isset($data['booked_cpms'])
                ? (int) $data['booked_cpms']
                : (int) ceil($totalImpressions / 1000);

            $cost = round($floorCpm * $bookedCpms, 2);

            return [
                'impressions' => $totalImpressions,
                'cost' => $cost,
                'days' => $days,
                'unit_price' => $floorCpm,
                'booked_cpms' => $bookedCpms,
                'screen_count' => 1,
                'duration_units' => null,
                'duration_unit' => null,
            ];
        }

        // ── I/O: cost = io_rate × screen_count × duration_units ──
        $ioRate = (float) ($inv?->io_rate ?? 0);
        $rateUnit = $inv?->io_rate_unit ?? 'month';
        $screenCount = (int) ($data['screen_count'] ?? 1);

        // Tính duration_units từ ngày hoặc từ input
        if (isset($data['duration_units'])) {
            $durationUnits = (int) $data['duration_units'];
        } else {
            $divisor = $rateUnit === 'week' ? 7 : 30;
            $durationUnits = max(1, (int) ceil($days / $divisor));
        }

        $cost = round($ioRate * $screenCount * $durationUnits, 2);

        return [
            'impressions' => $totalImpressions,
            'cost' => $cost,
            'days' => $days,
            'unit_price' => $ioRate,
            'booked_cpms' => null,
            'screen_count' => $screenCount,
            'duration_units' => $durationUnits,
            'duration_unit' => $rateUnit,
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
