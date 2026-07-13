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
// `reminders:send` is email-only; `appointments:notify` sends the configurable
// lead-time reminders (email + WhatsApp) for Booked / Confirmed appointments.
Schedule::command('reminders:send')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('appointments:notify')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('announcements:dispatch')->everyFiveMinutes()->withoutOverlapping();
// Auto-settle overdue statuses (booked/confirmed → no-show, checked-in →
// completed) so nothing lingers as "Booked" after its time has passed. Runs
// just before the missed-appointment follow-ups pick up the new no-shows.
Schedule::command('appointments:settle')->everyTenMinutes()->withoutOverlapping();
// "You missed your appointment" follow-ups for past, uncompleted appointments.
Schedule::command('appointments:notify-missed')->everyFifteenMinutes()->withoutOverlapping();

// Notify patients & providers of their appointments each morning.
Schedule::command('appointments:notify-today')->dailyAt('07:00');

// Continuous learning: re-score upcoming appointments from the latest history.
Schedule::command('ai:recompute-no-show')->dailyAt('02:00');

// Time out WhatsApp conversation flows that have gone quiet, and alert the front desk.
Schedule::command('flows:sweep-timeouts')->everyFifteenMinutes()->withoutOverlapping();

// Retention loop: overdue-follow-up recalls, care-gap outreach, and the
// post-visit review request (only after a visit is genuinely completed).
Schedule::command('recall:dispatch')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('reviews:request-dispatch')->everyFifteenMinutes()->withoutOverlapping();

// Free a held slot if its required deposit was never completed (10-minute hold).
Schedule::command('payments:release-abandoned')->everyFiveMinutes()->withoutOverlapping();
