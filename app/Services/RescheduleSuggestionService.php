<?php

namespace App\Services;

use App\Models\Appointment;
use Carbon\Carbon;

/**
 * Personalized rescheduling suggestions (PRD "smarter rescheduling
 * suggestions"): rather than just the next chronologically available slot,
 * suggest times that match the patient's own booking pattern — someone who
 * always books mornings is offered morning alternatives first. Falls back to
 * plain next-available when there's no clear pattern yet.
 */
class RescheduleSuggestionService
{
    private const SEARCH_DAYS = 14;

    private const SUGGESTION_COUNT = 3;

    public function __construct(private SchedulingService $scheduling)
    {
    }

    /** @return array<int, array{start: string, end: string, label: string}> */
    public function suggestFor(Appointment $appointment): array
    {
        $appointment->loadMissing('patient', 'provider', 'service');
        if (! $appointment->provider || ! $appointment->service) {
            return [];
        }

        $preferredHour = $this->preferredHour($appointment);
        $allSlots = [];

        for ($d = 1; $d <= self::SEARCH_DAYS && count($allSlots) < 30; $d++) {
            $date = today()->addDays($d);
            foreach ($this->scheduling->availableSlots($appointment->provider, $appointment->service, $date) as $slot) {
                $allSlots[] = $slot;
            }
        }

        if (empty($allSlots)) {
            return [];
        }

        if ($preferredHour !== null) {
            usort($allSlots, function ($a, $b) use ($preferredHour) {
                $diffA = abs(Carbon::parse($a['start'])->hour - $preferredHour);
                $diffB = abs(Carbon::parse($b['start'])->hour - $preferredHour);

                return $diffA <=> $diffB;
            });
        }

        $top = array_slice($allSlots, 0, self::SUGGESTION_COUNT);

        // availableSlots()' label is time-only (it's normally shown next to a
        // date picker that already supplies the date) — these suggestions are
        // shown without that context, so re-label with the date included.
        return array_map(fn ($slot) => [
            'start' => $slot['start'],
            'end' => $slot['end'],
            'label' => Carbon::parse($slot['start'])->format('D, M j \a\t g:i A'),
        ], $top);
    }

    /**
     * The patient's most common appointment hour from their own history — the
     * clearest available evidence of their real preference. Null if there's
     * no history yet (falls back to plain next-available ordering).
     */
    private function preferredHour(Appointment $appointment): ?int
    {
        if (! $appointment->patient_id) {
            return null;
        }

        $hours = Appointment::where('patient_id', $appointment->patient_id)
            ->where('id', '!=', $appointment->id)
            ->whereIn('status', [Appointment::STATUS_COMPLETED, Appointment::STATUS_CONFIRMED, Appointment::STATUS_BOOKED])
            ->pluck('start_at')
            ->map(fn ($dt) => Carbon::parse($dt)->hour);

        if ($hours->isEmpty()) {
            return null;
        }

        return $hours->countBy()->sortDesc()->keys()->first();
    }
}
