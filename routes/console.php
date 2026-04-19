<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ── OOHX Data Engine: sync screens every 30 minutes ──
// Non-overlapping: nếu export/rsync lần trước chưa xong thì skip lần này.
// Output append vào log riêng để debug nếu cần.
Schedule::command('oohx:sync-to-engine')
    ->everyThirtyMinutes()
    ->withoutOverlapping(15)   // lock 15 phút — đủ cho export vài ngàn screens
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/oohx-sync.log'));
