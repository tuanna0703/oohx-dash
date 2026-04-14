<?php

use App\Http\Controllers\Buyer\BookingController;
use App\Http\Controllers\Buyer\BuyerAuthController;
use App\Http\Controllers\Buyer\BuyerCampaignController;
use App\Http\Controllers\Buyer\BuyerDashboardController;
use App\Http\Controllers\Buyer\BuyerReportController;
use App\Http\Controllers\Buyer\BuyerSettingsController;
use App\Http\Controllers\Buyer\CartController;
use App\Http\Controllers\Buyer\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\FrontpageController;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

$fpDomain   = config('domains.frontpage', 'oohx.net');
$dashDomain = config('domains.dash', 'dash.oohx.net');

// ── Dashboard: dash.oohx.net ───────────────────────────
Route::domain($dashDomain)->group(function () {
    Route::get('/', fn () => redirect('/admin'));
});

// ── Frontpage: oohx.net ────────────────────────────────
Route::domain($fpDomain)->group(function () {

    // Public pages
    Route::get('/',                   [FrontpageController::class, 'index'])->name('fp.index');
    Route::get('/explore',            [FrontpageController::class, 'listing'])->name('fp.listing');
    Route::get('/explore/{screen}',   [FrontpageController::class, 'detail'])->name('fp.detail');
    Route::get('/map',                [FrontpageController::class, 'map'])->name('fp.map');
    Route::get('/agency',             [FrontpageController::class, 'agency'])->name('fp.agency');
    Route::get('/owners',             [FrontpageController::class, 'owners'])->name('fp.owners');
    Route::get('/owners/{owner}',     [FrontpageController::class, 'ownerDetail'])->name('fp.owner-detail');

    // Products (new marketplace listing)
    Route::get('/products',           [ProductController::class, 'index'])->name('fp.products');
    Route::get('/products/{slug}',    [ProductController::class, 'show'])->name('fp.product-detail');

    // ── Buyer auth (no guest middleware — accessible always) ──
    Route::get('/login',              [BuyerAuthController::class, 'showLogin'])->name('login');
    Route::post('/login',             [BuyerAuthController::class, 'login']);
    Route::get('/register',           [BuyerAuthController::class, 'showRegister'])->name('buyer.register');
    Route::post('/register',          [BuyerAuthController::class, 'register']);
    Route::post('/logout',            [BuyerAuthController::class, 'logout'])->name('buyer.logout')->middleware('auth');

    // ── Cart (auth required, no org needed) ──
    Route::middleware(['auth'])->group(function () {
        Route::get('/cart',           [CartController::class, 'index'])->name('buyer.cart');
        Route::post('/cart/add',      [CartController::class, 'add'])->name('buyer.cart.add');
        Route::put('/cart/{item}',    [CartController::class, 'update'])->name('buyer.cart.update');
        Route::delete('/cart/{item}', [CartController::class, 'remove'])->name('buyer.cart.remove');
        Route::get('/cart/count',     [CartController::class, 'count'])->name('buyer.cart.count');
    });

    // ── Booking wizard (auth required) ──
    Route::middleware(['auth'])->group(function () {
        Route::get('/booking/create',                  [BookingController::class, 'create'])->name('buyer.booking.create');
        Route::post('/booking/create',                 [BookingController::class, 'store'])->name('buyer.booking.store');
        Route::get('/booking/{campaign}/creative',     [BookingController::class, 'creative'])->name('buyer.booking.creative');
        Route::post('/booking/{campaign}/creative',    [BookingController::class, 'uploadCreative'])->name('buyer.booking.creative.upload');
        Route::get('/booking/{campaign}/review',       [BookingController::class, 'review'])->name('buyer.booking.review');
        Route::post('/booking/{campaign}/submit',      [BookingController::class, 'submit'])->name('buyer.booking.submit');
        Route::get('/booking/{campaign}/payment',      [PaymentController::class, 'show'])->name('buyer.payment');
        Route::post('/booking/{campaign}/payment',     [PaymentController::class, 'process'])->name('buyer.payment.process');
        Route::get('/booking/{campaign}/payment/success', [PaymentController::class, 'success'])->name('buyer.payment.success');
    });

    // ── Buyer dashboard (auth + buyer middleware) ──
    Route::middleware(['buyer'])->prefix('my')->name('buyer.')->group(function () {
        Route::get('/',                                [BuyerDashboardController::class, 'index'])->name('dashboard');
        Route::get('/campaigns',                       [BuyerCampaignController::class, 'index'])->name('campaigns');
        Route::get('/campaigns/{campaign}',            [BuyerCampaignController::class, 'show'])->name('campaigns.show');
        Route::get('/campaigns/{campaign}/report',   [BuyerReportController::class, 'show'])->name('campaigns.report');
        Route::get('/settings',                        [BuyerSettingsController::class, 'index'])->name('settings');
        Route::put('/settings/profile',                [BuyerSettingsController::class, 'updateProfile'])->name('settings.profile');
        Route::put('/settings/password',               [BuyerSettingsController::class, 'updatePassword'])->name('settings.password');
        Route::put('/settings/organization',           [BuyerSettingsController::class, 'updateOrganization'])->name('settings.organization');
    });

});

// ── Geocode proxy (accessible from all domains — admin, publisher, frontpage) ──
Route::get('/geocode/search', function () {
    $q = request()->input('q', '');
    if (! $q) {
        return response()->json([]);
    }

    $response = Http::withHeaders([
        'User-Agent' => 'AdTRUE-SSP/1.0',
        'Accept-Language' => 'vi,en',
    ])->get('https://nominatim.openstreetmap.org/search', [
        'format' => 'json',
        'limit'  => 5,
        'q'      => $q,
    ]);

    return response()->json($response->json());
});

// ── Fallback: nếu không match domain nào (www.oohx.net, IP, etc.)
Route::fallback(function () {
    return redirect('https://' . config('domains.frontpage', 'oohx.net'));
});
