<?php

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;

class SendDueReminders extends Command
{
    protected $signature = 'reminders:send';

    protected $description = 'Dispatch all due appointment reminders / confirmation requests';

    public function handle(ReminderService $reminders): int
    {
        $count = $reminders->dispatchDue();
        $this->info("Dispatched {$count} reminder(s).");

        return self::SUCCESS;
    }
}
