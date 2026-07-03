<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use Illuminate\Console\Command;

class SettleAppointmentStatuses extends Command
{
    protected $signature = 'appointments:settle';

    protected $description = 'Auto-update past appointments that were never completed (booked/confirmed → no-show, checked-in → completed)';

    public function handle(): int
    {
        $result = Appointment::settleOverdue();

        $this->info("Marked {$result['missed']} appointment(s) as no-show and {$result['completed']} as completed.");

        return self::SUCCESS;
    }
}
