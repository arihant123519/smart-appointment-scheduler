<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\WaitlistEntry;
use App\Services\Messaging\MessageService;
use App\Support\WhatsappTemplate;

/**
 * Smart waitlist auto-fill (PRD §5.2 / §6 stage 5).
 *
 * When a slot frees up (cancellation / no-show), find the best-matched waiting
 * patient and auto-offer the freed slot, ranked by priority then wait time.
 * `priority` defaults to a computed patient-value score (higher = more
 * likely to actually take and keep the slot — see PatientScoringService) but
 * staff can always override it manually when adding someone to the waitlist.
 */
class WaitlistService
{
    public function __construct(private MessageService $messages)
    {
    }

    public function offerFreedSlot(Appointment $appointment): ?WaitlistEntry
    {
        $candidate = WaitlistEntry::query()
            ->where('status', 'waiting')
            ->where('clinic_id', $appointment->clinic_id)
            ->where(function ($q) use ($appointment) {
                $q->whereNull('service_id')->orWhere('service_id', $appointment->service_id);
            })
            ->where(function ($q) use ($appointment) {
                $q->whereNull('provider_id')->orWhere('provider_id', $appointment->provider_id);
            })
            ->where(function ($q) use ($appointment) {
                $q->whereNull('earliest_date')->orWhereDate('earliest_date', '<=', $appointment->start_at);
            })
            ->where(function ($q) use ($appointment) {
                $q->whereNull('latest_date')->orWhereDate('latest_date', '>=', $appointment->start_at);
            })
            ->with('patient')
            ->orderByDesc('priority')    // higher computed score = highest
            ->orderBy('created_at')      // then longest-waiting
            ->first();

        if (! $candidate) {
            return null;
        }

        $candidate->update(['status' => 'offered']);

        $channel = $candidate->patient->preferred_channel ?? 'email';
        $start = $appointment->start_at;
        $section = $appointment->clinic_id ? WhatsappTemplate::forEvent($appointment->clinic_id, 'waitlist') : null;

        if ($channel === 'whatsapp' && $section && $section['template_id']) {
            // WhatsApp business-initiated messages need an approved template.
            // Variables are filled in their configured order; the appointment
            // here is the freed slot being offered to the waiting patient.
            $context = WhatsappTemplate::contextFromAppointment($appointment);
            $context['patient_name'] = (string) ($candidate->patient->name ?? '');
            $params = WhatsappTemplate::resolveParams($section['variables'], $context);
            $this->messages->sendWhatsappTemplate($candidate->patient, $section['template_id'], $params, null, $appointment, 'waitlist', 'waitlist');
        } else {
            $this->messages->send(
                $candidate->patient,
                'A sooner appointment just opened up',
                implode("\n", [
                    "Hi {$candidate->patient->name},",
                    '',
                    'A slot matching your waitlist request just became available:',
                    '  • '.$start->format('l, F j, Y \a\t g:i A'),
                    '  • with '.$appointment->provider->name,
                    '',
                    'Log in and book it here: '.route('booking.create'),
                ]),
                $channel,
                $appointment,
                'waitlist',
                'waitlist',
            );
        }

        return $candidate;
    }
}
