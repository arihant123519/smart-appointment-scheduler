<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentNotification;
use App\Models\WhatsappFlow;
use App\Services\Flows\FlowEngine;
use App\Services\Messaging\MessageService;
use App\Support\AppointmentNotifications;
use App\Support\WhatsappTemplate;

/**
 * Appointment Notifications — the email + WhatsApp layer that sits alongside the
 * legacy email-only `reminders:send` cadence.
 *
 *  - Lead-time reminders: for Booked / Confirmed appointments, schedule sends at
 *    the configured offsets before start, on the configured channels. Dispatched
 *    by the `appointments:notify` command.
 *  - Status-change notifications: an immediate email + WhatsApp whenever an
 *    appointment's status changes.
 */
class AppointmentNotificationService
{
    /** Safety cap on how many "repeat every N hours" occurrences we schedule. */
    private const MAX_REPEAT_OCCURRENCES = 48;

    public function __construct(private MessageService $messages)
    {
    }

    /**
     * (Re)build the scheduled lead-time notifications for an appointment from
     * the current configuration. Only Booked / Confirmed appointments get them;
     * any other status clears the pending rows.
     *
     * Two sources are combined and de-duplicated (so the same channel never
     * fires twice at the same moment):
     *  - discrete lead times (e.g. 24h, 2h before)
     *  - an optional "repeat every N hours until the appointment" rule
     */
    public function syncLeadTimes(Appointment $appointment): void
    {
        if (! in_array($appointment->status, [Appointment::STATUS_BOOKED, Appointment::STATUS_CONFIRMED], true)) {
            $this->cancelLeadTimes($appointment);

            return;
        }

        // Drop still-pending rows and re-derive from the current start time + config.
        $appointment->notifications()->where('status', 'scheduled')->delete();

        $start = $appointment->start_at;
        if (! $start) {
            return;
        }

        // Collect intended sends keyed by "channel|timestamp" to de-duplicate.
        $planned = [];
        $add = function (string $channel, int $hours, $sendAt) use (&$planned) {
            if (! $sendAt->isFuture()) {
                return;
            }
            $key = $channel.'|'.$sendAt->toDateTimeString();
            $planned[$key] ??= [
                'channel' => $channel,
                'hours' => $hours,
                'send_at' => $sendAt->copy(),
            ];
        };

        // 1. Discrete lead times.
        foreach (AppointmentNotifications::leadTimes() as $lt) {
            $sendAt = $start->copy()->subHours($lt['hours']);
            if ($lt['email']) {
                $add('email', $lt['hours'], $sendAt);
            }
            if ($lt['whatsapp']) {
                $add('whatsapp', $lt['hours'], $sendAt);
            }
        }

        // 2. Repeat every N hours until the appointment (start-N, start-2N, …),
        //    keeping only the future occurrences, newest-to-appointment first.
        $repeat = AppointmentNotifications::repeat();
        if ($repeat['enabled'] && $repeat['hours'] > 0 && ($repeat['email'] || $repeat['whatsapp'])) {
            for ($k = 1; $k <= self::MAX_REPEAT_OCCURRENCES; $k++) {
                $offset = $repeat['hours'] * $k;
                $sendAt = $start->copy()->subHours($offset);
                if (! $sendAt->isFuture()) {
                    break; // gone past "now" — no earlier occurrence can be in the future
                }
                if ($repeat['email']) {
                    $add('email', $offset, $sendAt);
                }
                if ($repeat['whatsapp']) {
                    $add('whatsapp', $offset, $sendAt);
                }
            }
        }

        foreach ($planned as $p) {
            AppointmentNotification::create([
                'appointment_id' => $appointment->id,
                'channel' => $p['channel'],
                'hours_before' => $p['hours'],
                'send_at' => $p['send_at'],
                'status' => 'scheduled',
            ]);
        }
    }

    /** Cancel any still-pending lead-time notifications for an appointment. */
    public function cancelLeadTimes(Appointment $appointment): void
    {
        $appointment->notifications()->where('status', 'scheduled')->update(['status' => 'cancelled']);
    }

    /**
     * Send the immediate email + WhatsApp message for a status change.
     *
     * @return array{email: bool, whatsapp: bool}
     */
    public function notifyStatusChange(Appointment $appointment): array
    {
        $result = ['email' => false, 'whatsapp' => false];

        $cfg = AppointmentNotifications::statusNotify();
        if (! $cfg['enabled']) {
            return $result;
        }

        $appointment->loadMissing('patient', 'provider.user', 'clinic', 'service');
        $patient = $appointment->patient;
        if (! $patient) {
            return $result;
        }

        if ($cfg['email']) {
            $result['email'] = $this->messages->send(
                $patient,
                'Appointment '.$appointment->status_label,
                $this->statusEmailBody($appointment),
                'email',
                $appointment,
                'appointment_notification',
                'status_update',
            );
        }

        if ($cfg['whatsapp']) {
            // Status messages need their own approved template (do not fall back
            // to the reminder template — its variables differ).
            $section = WhatsappTemplate::forEvent($appointment->clinic_id, 'status_update', false);
            if ($section && $section['template_id']) {
                $params = WhatsappTemplate::resolveParams(
                    $section['variables'],
                    WhatsappTemplate::contextFromAppointment($appointment),
                );
                $result['whatsapp'] = $this->messages->sendWhatsappTemplate(
                    $patient, $section['template_id'], $params, null,
                    $appointment, 'appointment_notification', 'status_update',
                );
            }
        }

        return $result;
    }

    /**
     * Send the immediate email + WhatsApp message when an appointment is
     * rescheduled. Uses the same enable/channel toggles as status changes.
     *
     * $viaFlow must be true when this is called FROM a running WhatsApp flow's
     * own "reschedule" action (App\Services\Flows\FlowEngine) — it skips
     * re-starting a flow, which would otherwise loop forever (the flow
     * rescheduling the appointment would immediately trigger itself again).
     *
     * @return array{email: bool, whatsapp: bool}
     */
    public function notifyRescheduled(Appointment $appointment, bool $viaFlow = false): array
    {
        $result = ['email' => false, 'whatsapp' => false];

        $cfg = AppointmentNotifications::statusNotify();
        if (! $cfg['enabled']) {
            return $result;
        }

        $appointment->loadMissing('patient', 'provider.user', 'clinic', 'service');
        $patient = $appointment->patient;
        if (! $patient) {
            return $result;
        }

        if ($cfg['email']) {
            $result['email'] = $this->messages->send(
                $patient,
                'Appointment rescheduled',
                $this->rescheduleEmailBody($appointment),
                'email',
                $appointment,
                'appointment_notification',
                'reschedule',
            );
        }

        if ($cfg['whatsapp']) {
            // If a WhatsApp conversation flow is active for "reschedule", let it
            // drive the interactive back-and-forth (ask if the new time works,
            // capture a preferred time otherwise, etc.) instead of the static
            // one-way template. Falls back to the static template exactly as
            // before when no flow is configured — fully additive.
            $flow = $viaFlow ? null : WhatsappFlow::activeFor('reschedule', $appointment->clinic_id);
            if ($flow) {
                $result['whatsapp'] = (bool) app(FlowEngine::class)->start($flow, $appointment);
            } else {
                // Reschedule has its own approved template (do not fall back).
                $section = WhatsappTemplate::forEvent($appointment->clinic_id, 'reschedule', false);
                if ($section && $section['template_id']) {
                    $params = WhatsappTemplate::resolveParams(
                        $section['variables'],
                        WhatsappTemplate::contextFromAppointment($appointment),
                    );
                    $result['whatsapp'] = $this->messages->sendWhatsappTemplate(
                        $patient, $section['template_id'], $params, null,
                        $appointment, 'appointment_notification', 'reschedule',
                    );
                }
            }
        }

        return $result;
    }

    /**
     * Send the "you missed your appointment" message (email + WhatsApp) for an
     * appointment whose time passed without being completed.
     *
     * @return array{email: bool, whatsapp: bool}
     */
    public function notifyMissed(Appointment $appointment): array
    {
        $result = ['email' => false, 'whatsapp' => false];

        $appointment->loadMissing('patient', 'provider.user', 'clinic', 'service');
        $patient = $appointment->patient;
        if (! $patient) {
            return $result;
        }

        $result['email'] = $this->messages->send(
            $patient,
            'Missed appointment',
            $this->missedEmailBody($appointment),
            'email',
            $appointment,
            'appointment_notification',
            'missed',
        );

        // WhatsApp uses the approved "missed" template (no fallback).
        $section = WhatsappTemplate::forEvent($appointment->clinic_id, 'missed', false);
        if ($section && $section['template_id']) {
            $params = WhatsappTemplate::resolveParams(
                $section['variables'],
                WhatsappTemplate::contextFromAppointment($appointment),
            );
            $result['whatsapp'] = $this->messages->sendWhatsappTemplate(
                $patient, $section['template_id'], $params, null,
                $appointment, 'appointment_notification', 'missed',
            );
        }

        return $result;
    }

    /** Dispatch every due lead-time notification. Returns the count sent. */
    public function dispatchDue(): int
    {
        $count = 0;

        AppointmentNotification::due()
            ->with('appointment.patient', 'appointment.provider.user', 'appointment.clinic', 'appointment.service')
            ->get()
            ->each(function (AppointmentNotification $notification) use (&$count) {
                $appointment = $notification->appointment;

                // Only Booked / Confirmed appointments that are still in the
                // future warrant a reminder. Never remind about an appointment
                // that has already started — that produces a confusing message
                // (e.g. a "29 Jun" reminder delivered on the 30th).
                if (! $appointment
                    || ! in_array($appointment->status, [Appointment::STATUS_BOOKED, Appointment::STATUS_CONFIRMED], true)
                    || ! $appointment->patient
                    || ! $appointment->start_at
                    || $appointment->start_at->isPast()) {
                    $notification->update(['status' => 'cancelled']);

                    return;
                }

                $ok = $notification->channel === 'whatsapp'
                    ? $this->sendWhatsappReminder($appointment)
                    : $this->messages->send(
                        $appointment->patient,
                        'Appointment reminder',
                        $this->reminderEmailBody($appointment),
                        'email',
                        $appointment,
                        'appointment_notification',
                        'appointment',
                    );

                $notification->update(['status' => $ok ? 'sent' : 'failed', 'sent_at' => now()]);
                $count++;
            });

        return $count;
    }

    private function sendWhatsappReminder(Appointment $appointment): bool
    {
        $section = WhatsappTemplate::forEvent($appointment->clinic_id, 'appointment');
        if (! $section || ! $section['template_id']) {
            return false;
        }

        $params = WhatsappTemplate::resolveParams(
            $section['variables'],
            WhatsappTemplate::contextFromAppointment($appointment),
        );

        return $this->messages->sendWhatsappTemplate(
            $appointment->patient, $section['template_id'], $params, null,
            $appointment, 'appointment_notification', 'appointment',
        );
    }

    /** " (for Dad)" when this booking was made on behalf of someone else, else "". */
    private function forSuffix(Appointment $appointment): string
    {
        return $appointment->booked_for_name ? ' (for '.$appointment->booked_for_name.')' : '';
    }

    private function statusEmailBody(Appointment $appointment): string
    {
        return implode("\n", [
            'Hi '.$appointment->patient->name.',',
            '',
            'Your appointment'.$this->forSuffix($appointment).' with '.$appointment->provider->name
                .' on '.$appointment->start_at->format('l, F j, Y \a\t g:i A')
                .' is now: '.$appointment->status_label.'.',
            '',
            'Thank you.',
        ]);
    }

    private function rescheduleEmailBody(Appointment $appointment): string
    {
        return implode("\n", [
            'Hi '.$appointment->patient->name.',',
            '',
            'Your appointment'.$this->forSuffix($appointment)
                .($appointment->clinic ? ' at '.$appointment->clinic->name : '')
                .' has been rescheduled. New date: '
                .$appointment->start_at->format('l, F j, Y \a\t g:i A')
                .' with '.$appointment->provider->name.'.',
            '',
            'Thank you for your understanding.',
        ]);
    }

    private function missedEmailBody(Appointment $appointment): string
    {
        return implode("\n", [
            'Hi '.$appointment->patient->name.',',
            '',
            'Seems like'.($appointment->booked_for_name ? ' '.$appointment->booked_for_name : ' you').' didn\'t come in for the appointed time on '
                .$appointment->start_at->format('l, F j, Y \a\t g:i A')
                .($appointment->provider ? ' with '.$appointment->provider->name : '').'.',
            '',
            'Please contact us to reschedule.',
        ]);
    }

    private function reminderEmailBody(Appointment $appointment): string
    {
        return implode("\n", [
            'Hi '.$appointment->patient->name.',',
            '',
            'This is a reminder for'.($appointment->booked_for_name ? " {$appointment->booked_for_name}'s appointment" : ' your appointment')
                .($appointment->clinic ? ' at '.$appointment->clinic->name : '')
                .' with '.$appointment->provider->name
                .' on '.$appointment->start_at->format('l, F j, Y \a\t g:i A').'.',
            '',
            'We look forward to seeing you.',
        ]);
    }
}
