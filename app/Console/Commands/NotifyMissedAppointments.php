<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\AppointmentNotificationService;
use Illuminate\Console\Command;

class NotifyMissedAppointments extends Command
{
    protected $signature = 'appointments:notify-missed';

    protected $description = 'Send a "you missed your appointment" message for past appointments not completed';

    public function handle(AppointmentNotificationService $notifications): int
    {
        $missed = Appointment::missed()
            ->whereNull('missed_notified_at')
            ->with(['patient', 'provider.user', 'clinic', 'service'])
            ->get();

        foreach ($missed as $appointment) {
            $notifications->notifyMissed($appointment);
            $appointment->update(['missed_notified_at' => now()]);
        }

        $this->info("Notified {$missed->count()} missed appointment(s).");

        return self::SUCCESS;
    }
}
