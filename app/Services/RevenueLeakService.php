<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Service;
use Illuminate\Support\Facades\DB;

/**
 * Surfaces plain-language observations about patterns that are quietly
 * costing the clinic revenue — never changes pricing, availability, or
 * policy on its own (PRD constraint); every flag is for a person to review
 * and decide what to do about.
 */
class RevenueLeakService
{
    private const LOOKBACK_DAYS = 30;
    private const MIN_SAMPLE = 3;

    /** @return array<int, array{title: string, detail: string}> */
    public function detect(): array
    {
        $flags = [];
        $flags = array_merge($flags, $this->highCancellationSlots());
        $flags = array_merge($flags, $this->underbookedServices());
        $flags = array_merge($flags, $this->highNoShowChannels());

        return $flags;
    }

    /** Day/hour buckets cancelling meaningfully more than the clinic's own average. */
    private function highCancellationSlots(): array
    {
        $window = now()->subDays(self::LOOKBACK_DAYS);
        $driver = DB::connection()->getDriverName();
        $dowExpr = $driver === 'sqlite' ? "CAST(strftime('%w', start_at) AS INTEGER)" : 'DAYOFWEEK(start_at) - 1';
        $hourExpr = $driver === 'sqlite' ? "CAST(strftime('%H', start_at) AS INTEGER)" : 'HOUR(start_at)';

        $rows = Appointment::where('start_at', '>=', $window)
            ->selectRaw("$dowExpr as dow, $hourExpr as hour, count(*) as total,
                sum(case when status = 'cancelled' then 1 else 0 end) as cancelled")
            ->groupBy('dow', 'hour')
            ->havingRaw('count(*) >= ?', [self::MIN_SAMPLE])
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $overallRate = $rows->sum('cancelled') / max(1, $rows->sum('total'));
        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        $flags = [];
        foreach ($rows as $row) {
            $rate = $row->cancelled / $row->total;
            if ($rate >= max(0.5, $overallRate * 2) && $row->cancelled >= self::MIN_SAMPLE) {
                $label = $days[$row->dow].' '.\Carbon\Carbon::createFromTime((int) $row->hour)->format('g A');
                $flags[] = [
                    'title' => "{$label} slots have been cancelled ".round($rate * 100)."% of the time this month",
                    'detail' => "{$row->cancelled} of {$row->total} bookings in this slot were cancelled — well above the clinic's overall {$this->pct($overallRate)} cancellation rate. Worth investigating why this specific time keeps falling through.",
                ];
            }
        }

        return $flags;
    }

    /** Active services whose booking volume is well below the clinic's own average. */
    private function underbookedServices(): array
    {
        $window = now()->subDays(self::LOOKBACK_DAYS);
        $services = Service::where('is_active', true)->withCount([
            'appointments' => fn ($q) => $q->where('start_at', '>=', $window),
        ])->get();

        if ($services->count() < 2) {
            return [];
        }

        $avg = $services->avg('appointments_count');
        if ($avg <= 0) {
            return [];
        }

        $flags = [];
        foreach ($services as $service) {
            if ($service->appointments_count > 0 && $service->appointments_count <= $avg * 0.3) {
                $flags[] = [
                    'title' => "\"{$service->name}\" is significantly under-booked",
                    'detail' => "Only {$service->appointments_count} booking(s) in the last ".self::LOOKBACK_DAYS." days, vs. an average of ".round($avg, 1)." across your other services. Might be worth a targeted promotion or checking if it's easy to find when booking.",
                ];
            }
        }

        return $flags;
    }

    /** Booking channels with a meaningfully higher no-show rate than the clinic average. */
    private function highNoShowChannels(): array
    {
        $window = now()->subDays(self::LOOKBACK_DAYS);

        $rows = Appointment::where('start_at', '>=', $window)
            ->whereIn('status', [Appointment::STATUS_COMPLETED, Appointment::STATUS_NO_SHOW])
            ->selectRaw('channel, count(*) as total, sum(case when status = ? then 1 else 0 end) as no_shows', [Appointment::STATUS_NO_SHOW])
            ->groupBy('channel')
            ->havingRaw('count(*) >= ?', [self::MIN_SAMPLE])
            ->get();

        if ($rows->count() < 2) {
            return [];
        }

        $overallRate = $rows->sum('no_shows') / max(1, $rows->sum('total'));

        $flags = [];
        foreach ($rows as $row) {
            $rate = $row->no_shows / $row->total;
            if ($rate >= max(0.4, $overallRate * 1.75)) {
                $flags[] = [
                    'title' => ucfirst($row->channel)." bookings no-show ".round($rate * 100)."% of the time",
                    'detail' => "vs. the clinic's overall {$this->pct($overallRate)} no-show rate. Consider an extra reminder or a deposit for bookings from this channel.",
                ];
            }
        }

        return $flags;
    }

    private function pct(float $rate): string
    {
        return round($rate * 100, 1).'%';
    }
}
