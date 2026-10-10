<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// P2P syncs and reminders.
Schedule::command('p2p:sync-daily-purchase-orders')
    ->dailyAt('08:00')
    ->timezone('Asia/Manila')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('p2p:send-overdue-warnings')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->onOneServer();
