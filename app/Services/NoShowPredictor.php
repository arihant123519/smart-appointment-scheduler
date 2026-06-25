<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\User;
use Carbon\Carbon;

/**
 * Rule-based no-show risk scoring (0-100).
 *
 * Per the PRD, AI is assistive only; this rule-based engine is the dependable
 * fallback that keeps scheduling working when the AI layer is unavailable. An
 * AI provider can later override/refine these scores.
 */
class NoShowPredictor
{
    public function score(array $data): int
    {
        $patient = isset($data['patient_id']) ? User::find($data['patient_id']) : null;
        $start = isset($data['start_at']) ? Carbon::parse($data['start_at']) : now();

        $score = 10; // baseline

        if ($patient) {
            $history = Appointment::where('patient_id', $patient->id);
            $total = (clone $history)->whereIn('status', [
                Appointment::STATUS_COMPLETED,
                Appointment::STATUS_NO_SHOW,
            ])->count();
            $noShows = (clone $history)->where('status', Appointment::STATUS_NO_SHOW)->count();

            if ($total > 0) {
                $rate = $noShows / $total;
                $score += (int) round($rate * 50); // up to +50 for history
            }

            // Prior reschedules signal risk.
            $reschedules = (clone $history)->whereNotNull('cancelled_at')->count();
            $score += min($reschedules * 5, 15);
        }

        // Lead time: appointments booked far ahead are missed more often.
        $leadDays = now()->diffInDays($start, false);
        if ($leadDays > 14) {
            $score += 15;
        } elseif ($leadDays > 7) {
            $score += 8;
        }

        // Time of day: early-morning and late-evening run higher risk.
        $hour = $start->hour;
        if ($hour < 9 || $hour >= 17) {
            $score += 8;
        }

        // Monday mornings & Fridays trend higher.
        if (in_array($start->dayOfWeek, [Carbon::MONDAY, Carbon::FRIDAY], true)) {
            $score += 5;
        }

        return max(0, min(100, $score));
    }
}
