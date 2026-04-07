<?php

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
// Đặt sau dash để frontpage là fallback cho mọi domain khác
Route::domain($fpDomain)->group(function () {
    Route::get('/',                   [FrontpageController::class, 'index'])->name('fp.index');
    Route::get('/explore',            [FrontpageController::class, 'listing'])->name('fp.listing');
    Route::get('/explore/{screen}',   [FrontpageController::class, 'detail'])->name('fp.detail');
    Route::get('/map',                [FrontpageController::class, 'map'])->name('fp.map');
    Route::get('/booking',            [FrontpageController::class, 'booking'])->name('fp.booking');
    Route::get('/agency',             [FrontpageController::class, 'agency'])->name('fp.agency');
    Route::get('/owners',             [FrontpageController::class, 'owners'])->name('fp.owners');
    Route::get('/owners/{owner}',     [FrontpageController::class, 'ownerDetail'])->name('fp.owner-detail');

    // Proxy Nominatim geocoding — tránh CSP/CORS khi gọi từ browser
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
});

// ── Fallback: nếu không match domain nào (www.oohx.net, IP, etc.)
Route::fallback(function () {
    return redirect('https://' . config('domains.frontpage', 'oohx.net'));
});
