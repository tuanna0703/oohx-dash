<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Phase 1 Feature Flags
    |--------------------------------------------------------------------------
    |
    | Phase 1 focuses on marketplace listing/booking.
    | AdOps fields (SSP/programmatic) are hidden by default.
    | Set to true to reveal advanced SSP sections in Filament forms.
    |
    */

    'show_adops_fields' => (bool) env('OOHX_SHOW_ADOPS', false),

    /*
    |--------------------------------------------------------------------------
    | Data Engine Integration (outbound sync)
    |--------------------------------------------------------------------------
    |
    | Laravel rsync screens JSON payload lên Data Engine VPS (Python + PostGIS)
    | và trigger `ingest-screens`. Inbound (read estimates) xem connection `oohx`
    | trong config/database.php.
    |
    */

    'data_engine' => [
        'remote_host'  => env('OOHX_REMOTE_HOST',  '139.162.20.95'),
        'remote_user'  => env('OOHX_REMOTE_USER',  'oohx'),
        'ssh_key'      => env('OOHX_SSH_KEY',      '/root/.ssh/oohx_sync'),
        'remote_inbox' => env('OOHX_REMOTE_INBOX', '/home/oohx/inbox'),

        // Cột dùng làm `external_id` khi push screens → Data Engine.
        // Team chốt `uuid` (canonical, ổn định, khác với auto-increment id).
        'external_id_column' => env('OOHX_EXTERNAL_ID_COLUMN', 'uuid'),
    ],

];
