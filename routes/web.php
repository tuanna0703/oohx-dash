<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

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
        'limit'  => 1,
        'q'      => $q,
    ]);

    return response()->json($response->json());
});
