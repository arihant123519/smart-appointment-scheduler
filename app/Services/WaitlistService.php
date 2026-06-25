<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\WaitlistEntry;
use App\Services\Messaging\MessageService;

/**
 * Smart waitlist auto-fill (PRD §5.2 / §6 stage 5).
 *
 * When a slot frees up (cancellation / no-show), find the best-matched waiting
 * patient and auto-offer the freed slot, ranked by priority then wait time.
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
            ->orderBy('priority')        // 1 = highest
            ->orderBy('created_at')      // then longest-waiting
            ->first();

        if (! $candidate) {
            return null;
        }

        $candidate->update(['status' => 'offered']);

        $this->messages->send(
            $candidate->patient,
            'A sooner appointment just opened up',
            implode("\n", [
                "Hi {$candidate->patient->name},",
                '',
                'A slot matching your waitlist request just became available:',
                '  • '.$appointment->start_at->format('l, F j, Y \a\t g:i A'),
                '  • with '.$appointment->provider->name,
                '',
                'Log in and book it here: '.route('booking.create'),
            ]),
            $candidate->patient->preferred_channel,
        );

        return $candidate;
    }
}
