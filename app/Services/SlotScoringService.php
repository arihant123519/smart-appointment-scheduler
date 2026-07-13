<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Service;
use Carbon\Carbon;

/**
 * Rule-based slot-value scoring (0-100) — same shape as NoShowPredictor:
 * additive weighted factors from real demand patterns, clamped to [0,100].
 * A high score means this day/hour/service combination is historically in
 * high demand (fills reliably, low cancellation) — informs which slots get
 * prioritized for waitlist offers or deposit requirements, never a decision
 * made silently (see Service::deposit_required / overbooking_enabled, which
 * a human always configures explicitly).
 */
class SlotScoringService
{
    private const LOOKBACK_DAYS = 90;

    /** @return array{score: int, reason: string} */
    public function scoreWithReason(Service $service, Carbon $dateTime): array
    {
        $score = 30; // baseline
        $reasons = [];

        $sameSlot = $this->historicalOutcomes($service, $dateTime->dayOfWeek, $dateTime->hour);
        $total = $sameSlot['completed'] + $sameSlot['no_show'] + $sameSlot['cancelled'];

        if ($total > 0) {
            $fillPoints = min($sameSlot['completed'] * 6, 40);
            $score += $fillPoints;

            $noShowRate = $total > 0 ? $sameSlot['no_show'] / $total : 0;
            $score -= (int) round($noShowRate * 30);

            $reasons[] = "{$sameSlot['completed']}/{$total} kept over the last ".self::LOOKBACK_DAYS.' days at this day/time';
        } else {
            $reasons[] = 'no history yet for this day/time';
        }

        // Weekday business hours are the highest-demand window for most clinics.
        $isWeekday = ! in_array($dateTime->dayOfWeek, [Carbon::SUNDAY, Carbon::SATURDAY], true);
        $isBusinessHour = $dateTime->hour >= 9 && $dateTime->hour < 17;
        if ($isWeekday && $isBusinessHour) {
            $score += 10;
        }

        $score = max(0, min(100, $score));

        return ['score' => $score, 'reason' => ucfirst(implode(', ', $reasons)).'.'];
    }

    public function score(Service $service, Carbon $dateTime): int
    {
        return $this->scoreWithReason($service, $dateTime)['score'];
    }

    /**
     * The historical no-show rate for this service at this specific
     * day-of-week + hour bucket — the signal controlled overbooking uses to
     * decide whether a slot is eligible (Service::overbooking_enabled must
     * also be explicitly turned on; this never applies on its own).
     */
    public function noShowRateFor(Service $service, Carbon $dateTime): float
    {
        $outcomes = $this->historicalOutcomes($service, $dateTime->dayOfWeek, $dateTime->hour);
        $finished = $outcomes['completed'] + $outcomes['no_show'];

        return $finished > 0 ? $outcomes['no_show'] / $finished : 0.0;
    }

    /** @return array{completed: int, no_show: int, cancelled: int} */
    private function historicalOutcomes(Service $service, int $dayOfWeek, int $hour): array
    {
        $window = now()->subDays(self::LOOKBACK_DAYS);

        // Day-of-week/hour extraction differs by driver, same pattern as
        // DashboardController's busiest-hours chart.
        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();
        $dowExpr = $driver === 'sqlite' ? "CAST(strftime('%w', start_at) AS INTEGER)" : 'DAYOFWEEK(start_at) - 1';
        $hourExpr = $driver === 'sqlite' ? "CAST(strftime('%H', start_at) AS INTEGER)" : 'HOUR(start_at)';

        $rows = Appointment::where('service_id', $service->id)
            ->where('start_at', '>=', $window)
            ->whereRaw("$dowExpr = ?", [$dayOfWeek])
            ->whereRaw("$hourExpr = ?", [$hour])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'completed' => (int) ($rows[Appointment::STATUS_COMPLETED] ?? 0),
            'no_show' => (int) ($rows[Appointment::STATUS_NO_SHOW] ?? 0),
            'cancelled' => (int) ($rows[Appointment::STATUS_CANCELLED] ?? 0),
        ];
    }
}
