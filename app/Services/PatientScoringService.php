<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Referral;
use App\Models\User;

/**
 * Rule-based patient-value scoring (0-100) — same shape as NoShowPredictor:
 * additive weighted factors, clamped to [0,100]. Never shown to the patient
 * and never used to deny or delay care (PRD constraint) — it only shapes
 * waitlist priority and how a recall message is worded. Every score comes
 * with a plain-language reason so staff can always see why, and can always
 * override it manually (see WaitlistController).
 */
class PatientScoringService
{
    public function score(User $patient): int
    {
        return $this->scoreWithReason($patient)['score'];
    }

    /** @return array{score: int, reason: string} */
    public function scoreWithReason(User $patient): array
    {
        $score = 20; // baseline
        $reasons = [];

        $history = Appointment::where('patient_id', $patient->id);
        $completed = (clone $history)->where('status', Appointment::STATUS_COMPLETED)->count();
        $noShows = (clone $history)->where('status', Appointment::STATUS_NO_SHOW)->count();
        $finished = $completed + $noShows;

        // Visit frequency: a long-time regular is worth more than a first-timer.
        if ($completed > 0) {
            $freqPoints = min($completed * 4, 40);
            $score += $freqPoints;
            $reasons[] = "{$completed} completed visit".($completed === 1 ? '' : 's');
        }

        // Attendance reliability: someone who reliably shows up is statistically
        // far more likely to actually take and keep a reopened slot.
        if ($finished > 0) {
            $noShowRate = $noShows / $finished;
            $reliabilityPoints = (int) round((1 - $noShowRate) * 25);
            $score += $reliabilityPoints;
            if ($noShowRate === 0.0) {
                $reasons[] = 'no no-shows on record';
            } elseif ($noShowRate > 0.3) {
                $reasons[] = 'a history of missed visits';
            }
        }

        // Referral activity: patients who bring in other patients are worth investing in.
        $referrals = Referral::where('referrer_patient_id', $patient->id)->where('status', 'booked')->count();
        if ($referrals > 0) {
            $score += min($referrals * 8, 20);
            $reasons[] = "referred {$referrals} patient".($referrals === 1 ? '' : 's');
        }

        $score = max(0, min(100, $score));

        if (empty($reasons)) {
            $reasons[] = 'no visit history yet';
        }

        return ['score' => $score, 'reason' => 'Based on '.implode(', ', $reasons).'.'];
    }
}
