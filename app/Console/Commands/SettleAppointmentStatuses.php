<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\PaymentService;
use App\Services\RetentionService;
use Illuminate\Console\Command;

class SettleAppointmentStatuses extends Command
{
    protected $signature = 'appointments:settle';

    protected $description = 'Auto-update past appointments that were never completed (booked/confirmed → no-show, checked-in → completed)';

    public function handle(RetentionService $retention, PaymentService $payments): int
    {
        $result = Appointment::settleOverdue();
        $retention->scheduleForCompletedIds($result['completed_ids']);
        $payments->forfeitForNoShowIds($result['missed_ids']);

        $this->info("Marked {$result['missed']} appointment(s) as no-show and {$result['completed']} as completed.");

        return self::SUCCESS;
    }
}
