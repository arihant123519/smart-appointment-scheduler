<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\User;
use App\Notifications\GenericNotification;

/**
 * Notifies a clinic's front-desk staff about activity they should watch in real
 * time — currently, appointment status changes made by anyone in their clinic.
 */
class ClinicDeskNotifier
{
    public function appointmentStatusChanged(Appointment $appointment, User $actor): void
    {
        if (! $appointment->clinic_id) {
            return;
        }

        // Front-desk users of the same clinic, minus whoever made the change.
        $recipients = User::role('front_desk')
            ->where('clinic_id', $appointment->clinic_id)
            ->where('id', '!=', $actor->id)
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $appointment->loadMissing(['patient', 'provider.user']);
        $patient = $appointment->patient->name ?? 'A patient';
        $when = $appointment->start_at?->format('M j, g:i A');

        $notification = new GenericNotification(
            title: 'Appointment status updated',
            body: "{$patient}'s appointment".($when ? " on {$when}" : '')
                ." is now {$appointment->status_label} (by {$actor->name}).",
            url: route('appointments.show', $appointment),
            icon: 'fi-rr-refresh',
            key: 'appt_status_'.$appointment->id,
        );

        foreach ($recipients as $recipient) {
            $recipient->notify($notification);
        }
    }
}
