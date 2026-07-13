<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Clinic;

/**
 * Anonymous benchmarking against other clinics on this same installation —
 * no other clinic's specific data is ever shown, only an aggregate average.
 * Honest about the cold-start problem: with too few other clinics to
 * compare against, this says so plainly rather than fabricating a number.
 */
class BenchmarkingService
{
    private const LOOKBACK_DAYS = 30;
    private const MIN_OTHER_CLINICS = 2;

    /** @return array{available: bool, reason?: string, clinic: array, others: array} */
    public function compare(Clinic $clinic): array
    {
        $otherClinicIds = Clinic::where('id', '!=', $clinic->id)->where('is_active', true)->pluck('id');

        if ($otherClinicIds->count() < self::MIN_OTHER_CLINICS) {
            return [
                'available' => false,
                'reason' => 'Not enough other active clinics on this installation yet to benchmark against ('.$otherClinicIds->count().' found, need at least '.self::MIN_OTHER_CLINICS.'). This becomes meaningful as more clinics come on board.',
                'clinic' => $this->ratesFor([$clinic->id]),
                'others' => ['no_show_rate' => null, 'completion_rate' => null],
            ];
        }

        return [
            'available' => true,
            'clinic' => $this->ratesFor([$clinic->id]),
            'others' => $this->ratesFor($otherClinicIds->all()),
        ];
    }

    /** @param array<int, int> $clinicIds */
    private function ratesFor(array $clinicIds): array
    {
        $window = now()->subDays(self::LOOKBACK_DAYS);

        $finished = Appointment::whereIn('clinic_id', $clinicIds)
            ->where('start_at', '>=', $window)
            ->whereIn('status', [Appointment::STATUS_COMPLETED, Appointment::STATUS_NO_SHOW, Appointment::STATUS_CANCELLED])
            ->count();
        $completed = Appointment::whereIn('clinic_id', $clinicIds)
            ->where('start_at', '>=', $window)->where('status', Appointment::STATUS_COMPLETED)->count();
        $noShows = Appointment::whereIn('clinic_id', $clinicIds)
            ->where('start_at', '>=', $window)->where('status', Appointment::STATUS_NO_SHOW)->count();

        return [
            'no_show_rate' => $finished > 0 ? round($noShows / $finished * 100, 1) : 0,
            'completion_rate' => $finished > 0 ? round($completed / $finished * 100, 1) : 0,
        ];
    }
}
