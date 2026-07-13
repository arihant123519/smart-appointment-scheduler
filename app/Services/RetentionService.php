<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\RecallNotice;
use App\Models\Referral;
use App\Models\User;
use App\Services\Messaging\MessageService;
use App\Support\WhatsappTemplate;
use Illuminate\Support\Str;

/**
 * The retention loop (PRD "Keeping patients coming back"): recall campaigns
 * for overdue follow-ups, care-gap outreach tied to an ongoing treatment
 * plan's cadence, a follow-through nudge on what was actually discussed,
 * post-visit review requests, and referral tracking through to completion.
 *
 * Follows the same shape as AppointmentNotificationService: inject
 * MessageService, guard on the patient existing, dispatch WhatsApp via an
 * approved template when configured (falling back to plain email/WhatsApp
 * text otherwise), log every send through MessageService.
 */
class RetentionService
{
    public function __construct(private MessageService $messages)
    {
    }

    // --- Scheduling (called when a visit completes) ------------------------

    /**
     * If the visit's service has a recall window, schedule a one-off
     * "book your follow-up" nudge — unless the patient already has a future
     * booking for that same service.
     */
    public function scheduleFollowUpRecall(Appointment $appointment): ?RecallNotice
    {
        $appointment->loadMissing('service');
        $service = $appointment->service;

        if (! $service || ! $service->recall_window_days) {
            return null;
        }

        if ($this->hasFutureBooking($appointment->patient_id, $service->id)) {
            return null;
        }

        return RecallNotice::create([
            'patient_id' => $appointment->patient_id,
            'service_id' => $service->id,
            'appointment_id' => $appointment->id,
            'type' => RecallNotice::TYPE_RECALL,
            'due_at' => now()->addDays($service->recall_window_days),
            'status' => 'pending',
        ]);
    }

    /**
     * If the visit's service carries an ongoing treatment-plan cadence,
     * schedule a care-gap check for when the patient is expected back.
     */
    public function scheduleCareGapCheck(Appointment $appointment): ?RecallNotice
    {
        $appointment->loadMissing('service');
        $service = $appointment->service;

        if (! $service || ! $service->recall_cadence_days) {
            return null;
        }

        if ($this->hasFutureBooking($appointment->patient_id, $service->id)) {
            return null;
        }

        return RecallNotice::create([
            'patient_id' => $appointment->patient_id,
            'service_id' => $service->id,
            'appointment_id' => $appointment->id,
            'type' => RecallNotice::TYPE_CARE_GAP,
            'due_at' => now()->addDays($service->recall_cadence_days),
            'status' => 'pending',
        ]);
    }

    /**
     * Schedule recall/care-gap outreach for appointments that were just
     * bulk-completed (settleOverdue()'s query-builder update bypasses the
     * model event that normally triggers this). Shared by the settle command
     * and anywhere else that calls settleOverdue() directly.
     *
     * @param  iterable<int>  $appointmentIds
     */
    public function scheduleForCompletedIds(iterable $appointmentIds): void
    {
        $ids = collect($appointmentIds);
        if ($ids->isEmpty()) {
            return;
        }

        Appointment::whereIn('id', $ids)->with('service')->get()->each(function (Appointment $appointment) {
            $this->scheduleFollowUpRecall($appointment);
            $this->scheduleCareGapCheck($appointment);
        });
    }

    private function hasFutureBooking(int $patientId, int $serviceId): bool
    {
        return Appointment::where('patient_id', $patientId)
            ->where('service_id', $serviceId)
            ->where('start_at', '>=', now())
            ->whereNotIn('status', [Appointment::STATUS_CANCELLED, Appointment::STATUS_NO_SHOW])
            ->exists();
    }

    // --- Dispatch (called by scheduled commands) ----------------------------

    /** Send every due recall / care-gap notice. Returns the count sent. */
    public function sendDueNotices(): int
    {
        $sent = 0;

        RecallNotice::due()->with('patient', 'service', 'appointment.provider', 'appointment.clinic')->get()
            ->each(function (RecallNotice $notice) use (&$sent) {
                // Re-check: patient may have already booked since this was scheduled.
                if ($notice->service_id && $this->hasFutureBooking($notice->patient_id, $notice->service_id)) {
                    $notice->update(['status' => 'booked']);
                    return;
                }

                $ok = $notice->type === RecallNotice::TYPE_CARE_GAP
                    ? $this->sendCareGap($notice)
                    : $this->sendRecall($notice);

                $notice->update(['status' => $ok ? 'sent' : 'skipped', 'sent_at' => now()]);
                if ($ok) {
                    $sent++;
                }
            });

        return $sent;
    }

    private function sendRecall(RecallNotice $notice): bool
    {
        $patient = $notice->patient;
        if (! $patient) {
            return false;
        }

        $context = $this->noticeContext($notice);
        $channel = $patient->preferred_channel ?? 'email';

        if ($channel === 'whatsapp' && $patient->clinic_id) {
            $section = WhatsappTemplate::forEvent($patient->clinic_id, 'recall', false);
            if ($section && $section['template_id']) {
                $params = WhatsappTemplate::resolveParams($section['variables'], $context);

                return $this->messages->sendWhatsappTemplate(
                    $patient, $section['template_id'], $params, null,
                    $notice->appointment, 'retention', 'recall',
                );
            }
        }

        return $this->messages->send(
            $patient,
            'Time for your follow-up?',
            "Hi {$patient->name},\n\nIt's been a little while since your {$context['service_name']} visit at {$context['clinic_name']}. Your provider recommended a follow-up — book it whenever works for you: ".route('booking.create'),
            $channel, $notice->appointment, 'retention', 'recall',
        );
    }

    private function sendCareGap(RecallNotice $notice): bool
    {
        $patient = $notice->patient;
        if (! $patient) {
            return false;
        }

        $context = $this->noticeContext($notice);
        $channel = $patient->preferred_channel ?? 'email';

        if ($channel === 'whatsapp' && $patient->clinic_id) {
            $section = WhatsappTemplate::forEvent($patient->clinic_id, 'care_gap', false);
            if ($section && $section['template_id']) {
                $params = WhatsappTemplate::resolveParams($section['variables'], $context);

                return $this->messages->sendWhatsappTemplate(
                    $patient, $section['template_id'], $params, null,
                    $notice->appointment, 'retention', 'care_gap',
                );
            }
        }

        return $this->messages->send(
            $patient,
            "Checking in on your {$context['service_name']} plan",
            "Hi {$patient->name},\n\nYour {$context['service_name']} plan at {$context['clinic_name']} calls for regular visits, and it looks like you're due for one. Let's get you back on track: ".route('booking.create'),
            $channel, $notice->appointment, 'retention', 'care_gap',
        );
    }

    /** @return array<string, string> */
    private function noticeContext(RecallNotice $notice): array
    {
        return [
            'patient_name' => (string) ($notice->patient->name ?? ''),
            'service_name' => (string) ($notice->service->name ?? 'your'),
            'clinic_name' => (string) ($notice->appointment?->clinic->name ?? config('app.name')),
        ];
    }

    /**
     * A single check-in tied to exactly what was recommended, sent partway
     * through the recall window. Reuses the appointment's existing `notes`
     * field (the plain-language record of what was discussed) rather than a
     * new recap feature — nothing to nudge on if staff never recorded notes.
     */
    public function sendFollowThroughNudge(Appointment $appointment): bool
    {
        $appointment->loadMissing('patient');
        $patient = $appointment->patient;

        if (! $patient || ! trim((string) $appointment->notes)) {
            return false;
        }

        return $this->messages->send(
            $patient,
            'How\'s it going?',
            "Hi {$patient->name},\n\nJust checking in on what we discussed at your last visit:\n\"{$appointment->notes}\"\n\nHow's it going so far?",
            $patient->preferred_channel ?? 'email', $appointment, 'retention', 'follow_through',
        );
    }

    // --- Post-visit review requests ------------------------------------------

    /** Send review requests for every completed visit due one. Returns count sent. */
    public function sendReviewRequests(): int
    {
        $sent = 0;

        Appointment::dueForReviewRequest()->with('patient', 'clinic')->get()
            ->each(function (Appointment $appointment) use (&$sent) {
                $ok = $this->sendReviewRequest($appointment);
                $appointment->update(['review_requested_at' => now()]);
                if ($ok) {
                    $sent++;
                }
            });

        return $sent;
    }

    private function sendReviewRequest(Appointment $appointment): bool
    {
        $patient = $appointment->patient;
        if (! $patient) {
            return false;
        }

        $clinicName = (string) ($appointment->clinic->name ?? config('app.name'));
        $channel = $patient->preferred_channel ?? 'email';

        if ($channel === 'whatsapp' && $appointment->clinic_id) {
            $section = WhatsappTemplate::forEvent($appointment->clinic_id, 'review_request', false);
            if ($section && $section['template_id']) {
                $params = WhatsappTemplate::resolveParams(
                    $section['variables'],
                    ['patient_name' => $patient->name, 'clinic_name' => $clinicName],
                );

                return $this->messages->sendWhatsappTemplate(
                    $patient, $section['template_id'], $params, null,
                    $appointment, 'retention', 'review_request',
                );
            }
        }

        return $this->messages->send(
            $patient,
            'How was your visit?',
            "Hi {$patient->name},\n\nThanks for visiting {$clinicName}! If you have a moment, we'd really appreciate a quick review: ".route('reviews.create', $appointment),
            $channel, $appointment, 'retention', 'review_request',
        );
    }

    // --- Referrals ------------------------------------------------------------

    /** Get (or lazily create) a patient's shareable referral link. */
    public function referralLinkFor(User $patient): Referral
    {
        $referral = Referral::where('referrer_patient_id', $patient->id)
            ->where('status', 'pending')
            ->first();

        if ($referral) {
            return $referral;
        }

        return Referral::create([
            'clinic_id' => $patient->clinic_id,
            'referrer_patient_id' => $patient->id,
            'token' => Str::random(32),
            'status' => 'pending',
        ]);
    }

    /** Redeem a referral token once the referred person's booking succeeds. */
    public function redeem(string $token, Appointment $appointment): ?Referral
    {
        $referral = Referral::where('token', $token)->where('status', 'pending')->first();

        if (! $referral) {
            return null;
        }

        $referral->update([
            'status' => 'booked',
            'appointment_id' => $appointment->id,
            'redeemed_at' => now(),
        ]);

        return $referral;
    }
}
