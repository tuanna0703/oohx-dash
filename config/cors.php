<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CORS — Cross-Origin Resource Sharing
    |--------------------------------------------------------------------------
    | Cho phép oohx.net frontend gọi API từ browser.
    */

    'paths' => ['api/*', 'livewire/*', 'storage/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'https://oohx.net',
        'https://www.oohx.net',
        'https://dash.oohx.net',
        'http://oohx.test',
        'http://dash.oohx.test',
        'http://localhost:3000',
        'http://localhost:5173',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'Accept'],

    'exposed_headers' => [],

    'max_age' => 86400, // cache preflight 24h

    'supports_credentials' => false,

];
