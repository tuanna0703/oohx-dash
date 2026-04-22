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

// ── Phase 3.A Part 2: fetch DE health digest via scp ──
// DE cron daily 08:00 UTC; Laravel polls 30 phút để cover delay + retry.
// Non-overlapping + background → không chặn queue worker.
Schedule::command('oohx:fetch-health')
    ->everyThirtyMinutes()
    ->withoutOverlapping(5)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/oohx-health.log'));
