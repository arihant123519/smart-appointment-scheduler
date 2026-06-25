<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Dispatch due appointment reminders / confirmation requests every 5 minutes.
// (Run `php artisan schedule:work` locally, or a cron entry calling
//  `php artisan schedule:run` every minute in production.)
Schedule::command('reminders:send')->everyFiveMinutes()->withoutOverlapping();

// Notify patients & providers of their appointments each morning.
Schedule::command('appointments:notify-today')->dailyAt('07:00');

// Continuous learning: re-score upcoming appointments from the latest history.
Schedule::command('ai:recompute-no-show')->dailyAt('02:00');
