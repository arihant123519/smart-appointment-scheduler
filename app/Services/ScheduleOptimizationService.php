<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Provider;
use Carbon\Carbon;

/**
 * Suggests a better shape for a provider's weekly schedule template (PRD
 * "suggesting better use of your schedule") — compares each working day's
 * actual fill rate against the others. Never changes the template itself; a
 * staff member reviews and applies the suggestion, or ignores it entirely.
 */
class ScheduleOptimizationService
{
    private const LOOKBACK_DAYS = 60;
    private const MIN_GAP_PCT = 35.0;

    /** @return array<int, string> plain-language suggestions */
    public function suggestionsFor(Provider $provider): array
    {
        $fillByDay = $this->fillRateByDay($provider);
        $sampled = array_filter($fillByDay, fn ($d) => $d['available_minutes'] > 0);

        if (count($sampled) < 2) {
            return [];
        }

        $lowest = null;
        $highest = null;
        foreach ($sampled as $dow => $d) {
            if ($lowest === null || $d['rate'] < $sampled[$lowest]['rate']) {
                $lowest = $dow;
            }
            if ($highest === null || $d['rate'] > $sampled[$highest]['rate']) {
                $highest = $dow;
            }
        }

        if ($lowest === null || $highest === null || $lowest === $highest) {
            return [];
        }

        $gap = $sampled[$highest]['rate'] - $sampled[$lowest]['rate'];
        if ($gap < self::MIN_GAP_PCT) {
            return [];
        }

        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        return [
            "{$days[$lowest]}s are consistently under-booked ({$sampled[$lowest]['rate']}% filled) while {$days[$highest]}s are turning patients away or running near capacity ({$sampled[$highest]['rate']}% filled) — consider shifting an hour or two from {$days[$lowest]} to {$days[$highest]}.",
        ];
    }

    /** @return array<int, array{available_minutes: int, booked_minutes: int, rate: float}> keyed by day-of-week (0=Sun) */
    private function fillRateByDay(Provider $provider): array
    {
        $windows = $provider->availabilities()->where('recurring', true)->get();
        $window = now()->subDays(self::LOOKBACK_DAYS);

        $result = [];
        foreach (range(0, 6) as $dow) {
            $dayWindows = $windows->where('day_of_week', $dow);
            if ($dayWindows->isEmpty()) {
                continue;
            }

            $minutesPerOccurrence = $dayWindows->sum(function ($w) {
                return Carbon::parse($w->start_time)->diffInMinutes(Carbon::parse($w->end_time));
            });

            $occurrences = 0;
            for ($d = 0; $d < self::LOOKBACK_DAYS; $d++) {
                $date = today()->subDays($d);
                if ($date->dayOfWeek === $dow) {
                    $occurrences++;
                }
            }

            $availableMinutes = $minutesPerOccurrence * $occurrences;

            $bookedMinutes = Appointment::where('provider_id', $provider->id)
                ->where('start_at', '>=', $window)
                ->whereRaw($this->dowExpr().' = ?', [$dow])
                ->active()
                ->get(['start_at', 'end_at'])
                ->sum(fn ($a) => Carbon::parse($a->start_at)->diffInMinutes(Carbon::parse($a->end_at)));

            $result[$dow] = [
                'available_minutes' => $availableMinutes,
                'booked_minutes' => (int) $bookedMinutes,
                'rate' => $availableMinutes > 0 ? round(min(100, $bookedMinutes / $availableMinutes * 100), 1) : 0.0,
            ];
        }

        return $result;
    }

    private function dowExpr(): string
    {
        $driver = \Illuminate\Support\Facades\DB::connection()->getDriverName();

        return $driver === 'sqlite' ? "CAST(strftime('%w', start_at) AS INTEGER)" : 'DAYOFWEEK(start_at) - 1';
    }
}
