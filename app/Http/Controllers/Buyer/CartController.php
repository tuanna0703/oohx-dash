<?php

namespace App\Http\Controllers\Buyer;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(private CartService $cartService) {}

    /**
     * GET /cart — cart page
     */
    public function index(Request $request): View
    {
        $cart = $this->cartService->getOrCreateCart($request->user());
        $items = $cart->items()
            ->with(['screen.spec', 'screen.inventory', 'screen.owner', 'screen.site.network'])
            ->get();

        return view('buyer.cart', [
            'cart' => $cart,
            'items' => $items,
        ]);
    }

    /**
     * POST /cart/add — add screen to cart (AJAX or redirect)
     */
    public function add(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'screen_id' => ['required', 'string', 'exists:screens,id'],
            'start_date' => ['nullable', 'date', 'after_or_equal:today'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
        ]);

        $cart = $this->cartService->getOrCreateCart($request->user());
        $item = $this->cartService->addItem($cart, $data['screen_id'], $data);
        $count = $cart->items()->count();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'item_id' => $item->id,
                'count' => $count,
                'message' => 'Đã thêm vào plan',
            ]);
        }

        return redirect()->route('buyer.cart')->with('success', 'Đã thêm vào plan');
    }

    /**
     * PUT /cart/{item} — update cart item
     */
    public function update(Request $request, CartItem $item): JsonResponse|RedirectResponse
    {
        // Ensure item belongs to user's cart
        abort_unless($item->cart->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'share_of_voice_pct' => ['nullable', 'integer', 'min:1', 'max:100'],
            'spot_length' => ['nullable', 'integer', 'min:5', 'max:60'],
        ]);

        $item = $this->cartService->updateItem($item, $data);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'estimated_cost' => $item->estimated_cost,
                'estimated_impressions' => $item->estimated_impressions,
            ]);
        }

        return redirect()->route('buyer.cart');
    }

    /**
     * DELETE /cart/{item} — remove from cart
     */
    public function remove(Request $request, CartItem $item): JsonResponse|RedirectResponse
    {
        abort_unless($item->cart->user_id === $request->user()->id, 403);

        $this->cartService->removeItem($item);

        if ($request->wantsJson()) {
            $count = $item->cart->items()->count();
            return response()->json(['success' => true, 'count' => $count]);
        }

        return redirect()->route('buyer.cart');
    }

    /**
     * GET /cart/count — AJAX badge count
     */
    public function count(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $this->cartService->getItemCount($request->user()),
        ]);
    }
}
