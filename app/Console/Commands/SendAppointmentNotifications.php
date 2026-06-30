<?php

namespace App\Console\Commands;

use App\Services\AppointmentNotificationService;
use Illuminate\Console\Command;

class SendAppointmentNotifications extends Command
{
    protected $signature = 'appointments:notify';

    protected $description = 'Dispatch due appointment lead-time notifications (email + WhatsApp)';

    public function handle(AppointmentNotificationService $notifications): int
    {
        $count = $notifications->dispatchDue();
        $this->info("Dispatched {$count} appointment notification(s).");

        return self::SUCCESS;
    }
}
