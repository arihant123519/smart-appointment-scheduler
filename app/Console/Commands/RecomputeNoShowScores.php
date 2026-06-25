<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\NoShowPredictor;
use Illuminate\Console\Command;

/**
 * Continuous-learning step for no-show prediction (PRD §6.2 "Continuous
 * learning"). The predictor scores from each patient's accumulated kept-vs-missed
 * history; this job periodically re-scores upcoming appointments so their risk
 * reflects the latest outcomes, and reports the clinic's realized no-show rate
 * as a learned baseline.
 */
class RecomputeNoShowScores extends Command
{
    protected $signature = 'ai:recompute-no-show {--days=60 : How far ahead to re-score}';

    protected $description = 'Recompute no-show risk scores for upcoming appointments from the latest history';

    public function handle(NoShowPredictor $predictor): int
    {
        $until = now()->addDays((int) $this->option('days'));

        $upcoming = Appointment::query()
            ->whereIn('status', [Appointment::STATUS_BOOKED, Appointment::STATUS_CONFIRMED])
            ->whereBetween('start_at', [now(), $until])
            ->get();

        $changed = 0;
        foreach ($upcoming as $appointment) {
            $score = $predictor->score([
                'patient_id' => $appointment->patient_id,
                'start_at' => $appointment->start_at,
                'service_id' => $appointment->service_id,
            ]);

            if ($score !== $appointment->no_show_score) {
                $appointment->update(['no_show_score' => $score]);
                $changed++;
            }
        }

        // Learned baseline: realized no-show rate over the trailing 90 days.
        $window = now()->subDays(90);
        $finished = Appointment::where('start_at', '>=', $window)
            ->whereIn('status', [Appointment::STATUS_COMPLETED, Appointment::STATUS_NO_SHOW])->count();
        $noShows = Appointment::where('start_at', '>=', $window)
            ->where('status', Appointment::STATUS_NO_SHOW)->count();
        $rate = $finished > 0 ? round($noShows / $finished * 100, 1) : 0;

        $this->info("Re-scored {$upcoming->count()} upcoming appointment(s); {$changed} updated.");
        $this->info("Learned 90-day no-show baseline: {$rate}% ({$noShows}/{$finished}).");

        return self::SUCCESS;
    }
}
