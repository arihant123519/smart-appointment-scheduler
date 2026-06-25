<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Provider;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class SchedulingService
{
    /**
     * Generate bookable slots for a provider on a given date for a service.
     *
     * @return array<int, array{start: string, end: string, label: string}>
     */
    public function availableSlots(Provider $provider, Service $service, Carbon $date): array
    {
        $dow = (int) $date->dayOfWeek; // 0 = Sunday

        // Provider's recurring working hours for that weekday.
        $windows = $provider->availabilities()
            ->where('day_of_week', $dow)
            ->get();

        // Day blocked entirely by an exception?
        $blockingException = $provider->exceptions()
            ->whereDate('date', $date->toDateString())
            ->where('is_available', false)
            ->whereNull('start_time')
            ->exists();

        if ($blockingException || $windows->isEmpty()) {
            return [];
        }

        $duration = $service->duration ?: $provider->default_slot_minutes;
        $buffer = $service->buffer ?: 0;
        $step = $duration + $buffer;

        // Existing appointments that day (to exclude overlaps).
        $taken = $provider->appointments()
            ->whereDate('start_at', $date->toDateString())
            ->active()
            ->get(['start_at', 'end_at']);

        $slots = [];
        foreach ($windows as $window) {
            $cursor = $date->copy()->setTimeFromTimeString($window->start_time);
            $windowEnd = $date->copy()->setTimeFromTimeString($window->end_time);

            while ($cursor->copy()->addMinutes($duration)->lte($windowEnd)) {
                $slotStart = $cursor->copy();
                $slotEnd = $cursor->copy()->addMinutes($duration);

                $overlaps = $taken->first(function ($appt) use ($slotStart, $slotEnd) {
                    return $slotStart->lt($appt->end_at) && $slotEnd->gt($appt->start_at);
                });

                $inPast = $slotStart->isPast();

                if (! $overlaps && ! $inPast) {
                    $slots[] = [
                        'start' => $slotStart->toIso8601String(),
                        'end' => $slotEnd->toIso8601String(),
                        'label' => $slotStart->format('g:i A'),
                    ];
                }

                $cursor->addMinutes($step);
            }
        }

        return $slots;
    }

    /**
     * Atomically book an appointment, guarding against double-booking with a
     * row-locking transaction + overlap check.
     *
     * @throws RuntimeException when the slot is no longer available.
     */
    public function book(array $data): Appointment
    {
        return DB::transaction(function () use ($data) {
            $start = Carbon::parse($data['start_at']);

            $service = isset($data['service_id']) ? Service::find($data['service_id']) : null;
            $provider = Provider::findOrFail($data['provider_id']);
            $duration = $service?->duration ?? $provider->default_slot_minutes;
            $end = isset($data['end_at']) ? Carbon::parse($data['end_at']) : $start->copy()->addMinutes($duration);

            // Lock this provider's overlapping rows for the duration of the tx.
            $conflict = Appointment::where('provider_id', $provider->id)
                ->active()
                ->where('start_at', '<', $end)
                ->where('end_at', '>', $start)
                ->lockForUpdate()
                ->exists();

            if ($conflict) {
                throw new RuntimeException('This time slot is no longer available for the selected provider.');
            }

            // Optional resource conflict check.
            if (! empty($data['resource_id'])) {
                $resourceConflict = Appointment::where('resource_id', $data['resource_id'])
                    ->active()
                    ->where('start_at', '<', $end)
                    ->where('end_at', '>', $start)
                    ->lockForUpdate()
                    ->exists();

                if ($resourceConflict) {
                    throw new RuntimeException('The selected room/resource is already booked for this time.');
                }
            }

            $isTelehealth = $data['is_telehealth'] ?? ($service?->telehealth ?? false);

            $appointment = Appointment::create([
                'patient_id' => $data['patient_id'],
                'provider_id' => $provider->id,
                'clinic_id' => $data['clinic_id'] ?? $provider->clinic_id,
                'service_id' => $service?->id,
                'resource_id' => $data['resource_id'] ?? null,
                'start_at' => $start,
                'end_at' => $end,
                'status' => $data['status'] ?? Appointment::STATUS_BOOKED,
                'channel' => $data['channel'] ?? 'web',
                'recurring_group' => $data['recurring_group'] ?? null,
                'is_telehealth' => $isTelehealth,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
                'no_show_score' => $data['no_show_score'] ?? app(NoShowPredictor::class)->score($data),
                'created_by' => auth()->id(),
            ]);

            if ($isTelehealth) {
                $appointment->update(['telehealth_link' => app(TelehealthService::class)->linkFor($appointment)]);
            }

            return $appointment;
        });
    }

    /**
     * Book a recurring series (e.g. weekly therapy). Conflicting occurrences are
     * skipped and reported back rather than aborting the whole series.
     *
     * @return array{booked: Appointment[], skipped: string[]}
     */
    public function bookRecurring(array $data, string $frequency, int $occurrences): array
    {
        $group = (string) Str::uuid();
        $start = Carbon::parse($data['start_at']);
        $booked = [];
        $skipped = [];

        for ($i = 0; $i < $occurrences; $i++) {
            $occurrence = match ($frequency) {
                'daily' => $start->copy()->addDays($i),
                'biweekly' => $start->copy()->addWeeks($i * 2),
                'monthly' => $start->copy()->addMonths($i),
                default => $start->copy()->addWeeks($i), // weekly
            };

            try {
                $booked[] = $this->book(array_merge($data, [
                    'start_at' => $occurrence->toDateTimeString(),
                    'recurring_group' => $group,
                ]));
            } catch (RuntimeException $e) {
                $skipped[] = $occurrence->format('M j, Y g:i A');
            }
        }

        return ['booked' => $booked, 'skipped' => $skipped];
    }

    /**
     * Reschedule with the same conflict-safety guarantees.
     */
    public function reschedule(Appointment $appointment, Carbon $start, ?Carbon $end = null): Appointment
    {
        return DB::transaction(function () use ($appointment, $start, $end) {
            $duration = $appointment->service?->duration
                ?? $appointment->start_at->diffInMinutes($appointment->end_at);
            $end ??= $start->copy()->addMinutes($duration);

            $conflict = Appointment::where('provider_id', $appointment->provider_id)
                ->where('id', '!=', $appointment->id)
                ->active()
                ->where('start_at', '<', $end)
                ->where('end_at', '>', $start)
                ->lockForUpdate()
                ->exists();

            if ($conflict) {
                throw new RuntimeException('This time slot is no longer available for the selected provider.');
            }

            $appointment->update([
                'start_at' => $start,
                'end_at' => $end,
                'status' => Appointment::STATUS_BOOKED,
            ]);

            return $appointment;
        });
    }
}
