<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-backup harian: cleanup backup lama (02:00 WIB) → bikin backup baru (02:05 WIB)
// Health check + monitor jam 09:00 WIB tiap hari.
Schedule::command('backup:clean')
    ->daily()
    ->at('02:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('backup:run --only-db')
    ->daily()
    ->at('02:05')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('backup:run')
    ->daily()
    ->at('02:30')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('backup:monitor')
    ->daily()
    ->at('09:00')
    ->timezone('Asia/Jakarta');

// Polling Eqris setiap menit (Eqris tidak ada webhook, harus polling /api/mutasi-orkut-v2).
Schedule::command('eqris:poll-pending')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

// Expire membership overdue tiap jam.
Schedule::command('membership:expire-overdue')
    ->hourly()
    ->withoutOverlapping();
