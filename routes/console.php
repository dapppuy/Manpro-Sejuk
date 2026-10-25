<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('orders:expire-responses')->everyMinute()->withoutOverlapping();
Schedule::command('orders:send-reminders')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('payments:sync')->everyMinute()->withoutOverlapping();
Schedule::command('orders:auto-complete')->everyFiveMinutes()->withoutOverlapping();
