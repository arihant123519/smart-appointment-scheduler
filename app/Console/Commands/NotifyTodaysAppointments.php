<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Notifications\GenericNotification;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

class NotifyTodaysAppointments extends Command
{
    protected $signature = 'appointments:notify-today';

    protected $description = 'Create an in-app notification for each patient/provider with an appointment today';

    public function handle(): int
    {
        // A "your appointment is today" reminder is only true for one day —
        // once it rolls over, expire any that are still unread so a stale
        // (and now literally false) "today" notice doesn't sit at the top of
        // the bell indefinitely.
        DatabaseNotification::where('type', GenericNotification::class)
            ->whereNull('read_at')
            ->whereDate('created_at', '<', today())
            ->where(fn ($q) => $q->where('data->key', 'like', 'patient-today-%')
                ->orWhere('data->key', 'like', 'provider-today-%'))
            ->update(['read_at' => now()]);

        $appointments = Appointment::with(['patient', 'provider.user', 'service'])
            ->whereDate('start_at', today())
            ->active()
            ->get();

        $count = 0;

        foreach ($appointments as $appointment) {
            $time = $appointment->start_at->format('g:i A');

            if ($appointment->patient) {
                $count += $this->notifyOnce(
                    $appointment->patient,
                    'patient-today-'.$appointment->id,
                    'You have an appointment today',
                    "Today at {$time} with {$appointment->provider->name} ({$appointment->service?->name}).",
                    route('dashboard', [], false),
                );
            }

            if ($appointment->provider?->user) {
                $count += $this->notifyOnce(
                    $appointment->provider->user,
                    'provider-today-'.$appointment->id,
                    'Appointment today',
                    "Today at {$time}: {$appointment->patient?->name} ({$appointment->service?->name}).",
                    route('appointments.show', $appointment, false),
                );
            }
        }

        $this->info("Created {$count} today-appointment notification(s).");

        return self::SUCCESS;
    }

    /** Create the notification only if one with the same key was not already made today. */
    private function notifyOnce($notifiable, string $key, string $title, string $body, string $url): int
    {
        $exists = $notifiable->notifications()
            ->whereDate('created_at', today())
            ->where('data', 'like', '%"key":"'.$key.'"%')
            ->exists();

        if ($exists) {
            return 0;
        }

        $notifiable->notify(new GenericNotification($title, $body, $url, 'fi-rr-calendar-clock', $key));

        return 1;
    }
}
