<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Token TTL
    |--------------------------------------------------------------------------
    | Số phút token còn hiệu lực. Phải khớp với sanctum.expiration.
    */
    'token_ttl_minutes' => (int) env('TAPON_TOKEN_TTL', 60),

    /*
    |--------------------------------------------------------------------------
    | Allowed Scopes
    |--------------------------------------------------------------------------
    | Danh sách scope hợp lệ mà api_clients có thể được cấp.
    */
    'allowed_scopes' => ['inventory', 'booking', 'reporting'],

];
