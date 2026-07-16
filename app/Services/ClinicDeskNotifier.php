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
            url: route('appointments.show', $appointment, false),
            icon: 'fi-rr-refresh',
            key: 'appt_status_'.$appointment->id,
        );

        foreach ($recipients as $recipient) {
            $recipient->notify($notification);
        }
    }

    /**
     * Notify a clinic's front desk + clinic admins that a WhatsApp conversation
     * flow needs a human — either it hit an unresolvable step (e.g. an
     * unparsable requested time) or it went stale waiting for a reply. There's
     * no "actor" to exclude here; this is a system-triggered alert.
     */
    public function flowEscalated(Appointment $appointment, string $reason): void
    {
        if (! $appointment->clinic_id) {
            return;
        }

        $recipients = User::role(['front_desk', 'clinic_admin'])
            ->where('clinic_id', $appointment->clinic_id)
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        $appointment->loadMissing(['patient', 'provider.user']);
        $patient = $appointment->patient->name ?? 'A patient';
        $when = $appointment->start_at?->format('M j, g:i A');

        $notification = new GenericNotification(
            title: 'WhatsApp conversation needs attention',
            body: "{$patient}'s appointment".($when ? " on {$when}" : '')." — {$reason}.",
            url: route('appointments.show', $appointment, false),
            icon: 'fi-rr-comment-alt',
            key: 'flow_escalated_'.$appointment->id.'_'.now()->timestamp,
        );

        foreach ($recipients as $recipient) {
            $recipient->notify($notification);
        }
    }
}
