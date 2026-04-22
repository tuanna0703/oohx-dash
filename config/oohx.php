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

        // Full shell command to run ingest trên Data Engine VPS.
        // Path của Python repo có thể khác nhau tùy cách ops deploy — configure
        // qua env OOHX_INGEST_CMD. Default giả định repo ở `~/python-data-engine`.
        // Command sẽ được exec qua `ssh user@host '<command>'`.
        'ingest_cmd' => env(
            'OOHX_INGEST_CMD',
            'cd ~/python-data-engine && .venv/bin/python -m app.cli ingest-screens --file ~/inbox/screens.json'
        ),

        // Drain pending jobs queue. Max size là số jobs process tối đa / lần.
        'recompute_queue_cmd' => env(
            'OOHX_RECOMPUTE_QUEUE_CMD',
            'cd ~/apps/oohx-matrix/python-data-engine && .venv/bin/python -m app.cli recompute-pending-jobs --max 500'
        ),

        // Recompute tất cả screens trong 1 city. Template có placeholder {city}
        // sẽ được escape + replace tại runtime.
        'recompute_city_cmd' => env(
            'OOHX_RECOMPUTE_CITY_CMD',
            'cd ~/apps/oohx-matrix/python-data-engine && .venv/bin/python -m app.cli recompute-city --city {city}'
        ),

        // Phase 3.A Part 2 — remote path của health digest JSON trên DE VPS.
        // DE cron viết file theo định dạng health-digest-YYYYMMDD.json hàng ngày 08:00 UTC.
        // Laravel scp mỗi 30 phút về `storage/app/oohx-health/`.
        'health_digest_remote_dir' => env(
            'OOHX_HEALTH_REMOTE_DIR',
            '/home/oohx/logs'
        ),
    ],

];
