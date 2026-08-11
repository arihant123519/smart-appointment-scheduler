<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AvailabilityException;
use App\Models\Provider;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        // Keep statuses honest: settle any past appointments that were never
        // finalised before we read/aggregate them. (A scheduled command does the
        // same clinic-wide; this makes the dashboard correct without a cron.)
        $settled = Appointment::settleOverdue();
        app(\App\Services\RetentionService::class)->scheduleForCompletedIds($settled['completed_ids']);
        app(\App\Services\PaymentService::class)->forfeitForNoShowIds($settled['missed_ids']);

        // Patients get a focused self-service view.
        if ($user->hasRole('patient') && ! $user->hasAnyRole(['front_desk', 'provider', 'clinic_admin', 'system_admin', 'billing'])) {
            return $this->patientDashboard($user);
        }

        $today = today();

        // Providers (unless also front desk/admin) get a dashboard scoped to
        // their own appointments/patients, not the whole clinic's — reused
        // for every stat block below, not just the "missed" section.
        $onlyOwnProvider = $user->hasRole('provider')
            && ! $user->hasAnyRole(['clinic_admin', 'system_admin', 'front_desk'])
            && $user->provider;
        $providerId = $onlyOwnProvider ? $user->provider->id : null;
        $scopeToOwnProvider = fn ($query) => $query->when($onlyOwnProvider, fn ($q) => $q->where('provider_id', $providerId));

        $stats = [
            'today' => $scopeToOwnProvider(Appointment::forDay($today)->active())->count(),
            'upcoming' => $scopeToOwnProvider(Appointment::upcoming())->count(),
            'patients' => $onlyOwnProvider
                ? Appointment::where('provider_id', $providerId)->distinct()->count('patient_id')
                : User::role('patient')->count(),
            'providers' => Provider::where('is_active', true)->count(),
        ];

        // No-show rate (last 30 days)
        $window = now()->subDays(30);
        $finished = $scopeToOwnProvider(Appointment::where('start_at', '>=', $window)
            ->whereIn('status', [Appointment::STATUS_COMPLETED, Appointment::STATUS_NO_SHOW]))
            ->count();
        $noShows = $scopeToOwnProvider(Appointment::where('start_at', '>=', $window)
            ->where('status', Appointment::STATUS_NO_SHOW))->count();
        $noShowRate = $finished > 0 ? round($noShows / $finished * 100, 1) : 0;

        // Status breakdown
        $statusBreakdown = $scopeToOwnProvider(Appointment::select('status', DB::raw('count(*) as total')))
            ->groupBy('status')->pluck('total', 'status')->toArray();

        // Channel mix (last 30 days) — where bookings are actually coming from.
        $channelMix = $scopeToOwnProvider(Appointment::where('start_at', '>=', $window)
            ->select('channel', DB::raw('count(*) as total')))
            ->groupBy('channel')->pluck('total', 'channel')->toArray();

        // Fill rate (last 30 days): booked minutes vs. actual available capacity
        // for that period (from provider working hours, minus full-day
        // exceptions/holidays) — the whole clinic's, or just this provider's.
        $fillRate = $this->fillRateStats(today()->subDays(29), today(), $providerId);

        // Completion rate (last 30 days): completed vs. everything that reached
        // a terminal outcome (completed + no-show + cancelled).
        $completed30 = $scopeToOwnProvider(Appointment::where('start_at', '>=', $window)
            ->where('status', Appointment::STATUS_COMPLETED))->count();
        $terminal30 = $scopeToOwnProvider(Appointment::where('start_at', '>=', $window)
            ->whereIn('status', [
                Appointment::STATUS_COMPLETED,
                Appointment::STATUS_NO_SHOW,
                Appointment::STATUS_CANCELLED,
            ]))->count();
        $completionRate = $terminal30 > 0 ? round($completed30 / $terminal30 * 100, 1) : 0;

        // Appointments per day (last 14 days) — total booked vs. completed so the
        // chart tells a story rather than showing a single flat line.
        $rows = $scopeToOwnProvider(Appointment::where('start_at', '>=', now()->subDays(13)->startOfDay())
            ->select(
                DB::raw('DATE(start_at) as d'),
                DB::raw('count(*) as total'),
                DB::raw("sum(case when status = '".Appointment::STATUS_COMPLETED."' then 1 else 0 end) as completed"),
                DB::raw("sum(case when status = '".Appointment::STATUS_NO_SHOW."' then 1 else 0 end) as no_show")
            ))
            ->groupBy('d')->get()->keyBy('d');

        $chartLabels = [];
        $chartData = [];      // total
        $chartCompleted = [];
        $chartNoShow = [];
        for ($i = 13; $i >= 0; $i--) {
            $date = today()->subDays($i);
            $key = $date->toDateString();
            $chartLabels[] = $date->format('M j');
            $chartData[] = (int) ($rows[$key]->total ?? 0);
            $chartCompleted[] = (int) ($rows[$key]->completed ?? 0);
            $chartNoShow[] = (int) ($rows[$key]->no_show ?? 0);
        }

        // Busiest hours (last 30 days) — drives an interactive hourly bar chart.
        // Hour extraction differs by driver (MySQL HOUR() vs. SQLite strftime).
        $hourExpr = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', start_at) AS INTEGER)"
            : 'HOUR(start_at)';
        $hourRows = $scopeToOwnProvider(Appointment::where('start_at', '>=', $window)->active())
            ->select(DB::raw("$hourExpr as h"), DB::raw('count(*) as total'))
            ->groupBy('h')->pluck('total', 'h')->toArray();

        $hourLabels = [];
        $hourData = [];
        for ($h = 7; $h <= 19; $h++) {
            $hourLabels[] = \Carbon\Carbon::createFromTime($h)->format('g A');
            $hourData[] = (int) ($hourRows[$h] ?? 0);
        }

        // Top services (last 30 days) for a quick mix breakdown.
        $topServices = $scopeToOwnProvider(Appointment::where('appointments.start_at', '>=', $window)
            ->join('services', 'services.id', '=', 'appointments.service_id'))
            ->select('services.name', DB::raw('count(*) as total'))
            ->groupBy('services.name')->orderByDesc('total')->limit(5)
            ->pluck('total', 'services.name')->toArray();

        $todaysAppointments = $scopeToOwnProvider(Appointment::with(['patient', 'provider.user', 'service'])
            ->forDay($today)->active())->orderBy('start_at')->get();

        $highRisk = $scopeToOwnProvider(Appointment::with(['patient', 'provider.user'])
            ->upcoming()->where('no_show_score', '>=', 70))
            ->orderByDesc('no_show_score')->limit(5)->get();

        // Missed appointments (past + not completed). Providers see only their
        // own; front desk / clinic admin / system admin see the whole clinic.
        $missedQuery = $scopeToOwnProvider(Appointment::missed()->with(['patient', 'provider.user', 'service']));
        $missedCount = (clone $missedQuery)->count();
        $missedAppointments = (clone $missedQuery)->orderByDesc('start_at')->limit(8)->get();

        // Today's missed appointments specifically — these drive the alert popup.
        $todaysMissed = (clone $missedQuery)->whereDate('start_at', $today)
            ->orderBy('start_at')->get();

        return view('dashboard.index', compact(
            'stats', 'noShowRate', 'completionRate', 'statusBreakdown', 'channelMix', 'fillRate',
            'chartLabels', 'chartData', 'chartCompleted', 'chartNoShow',
            'hourLabels', 'hourData', 'topServices',
            'todaysAppointments', 'highRisk',
            'missedAppointments', 'missedCount', 'todaysMissed'
        ));
    }

    /**
     * Booked minutes vs. available capacity over [$start, $end] (inclusive
     * dates). Capacity comes from each active provider's recurring weekly
     * availability windows, with full-day exceptions (holidays, leave)
     * excluded for that specific date. Computed in PHP rather than raw SQL
     * date-diff functions so it works identically on MySQL and SQLite.
     *
     * @return array{rate: float, booked_minutes: int, available_minutes: int}
     */
    public function fillRateStats(Carbon $start, Carbon $end, ?int $providerId = null): array
    {
        $providers = Provider::where('is_active', true)
            ->when($providerId, fn ($q) => $q->where('id', $providerId))
            ->with('availabilities')->get();

        if ($providers->isEmpty()) {
            return ['rate' => 0.0, 'booked_minutes' => 0, 'available_minutes' => 0];
        }

        $blockedDays = AvailabilityException::whereIn('provider_id', $providers->pluck('id'))
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->where('is_available', false)
            ->whereNull('start_time')
            ->get(['provider_id', 'date'])
            ->map(fn ($e) => $e->provider_id.'|'.$e->date->toDateString())
            ->flip();

        $availableMinutes = 0;
        foreach ($providers as $provider) {
            $minutesByDow = [];
            foreach ($provider->availabilities->where('recurring', true) as $window) {
                $dow = (int) $window->day_of_week;
                $minutesByDow[$dow] = ($minutesByDow[$dow] ?? 0)
                    + Carbon::parse($window->start_time)->diffInMinutes(Carbon::parse($window->end_time));
            }

            if (empty($minutesByDow)) {
                continue;
            }

            for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                if (! isset($minutesByDow[$date->dayOfWeek])) {
                    continue;
                }
                if (isset($blockedDays[$provider->id.'|'.$date->toDateString()])) {
                    continue;
                }
                $availableMinutes += $minutesByDow[$date->dayOfWeek];
            }
        }

        $bookedMinutes = (int) Appointment::whereBetween('start_at', [$start, $end->copy()->endOfDay()])
            ->whereNotIn('status', [Appointment::STATUS_CANCELLED])
            ->when($providerId, fn ($q) => $q->where('provider_id', $providerId))
            ->get(['start_at', 'end_at'])
            ->sum(fn ($a) => $a->start_at->diffInMinutes($a->end_at));

        $rate = $availableMinutes > 0 ? round(min($bookedMinutes / $availableMinutes, 1) * 100, 1) : 0.0;

        return ['rate' => $rate, 'booked_minutes' => $bookedMinutes, 'available_minutes' => $availableMinutes];
    }

    private function patientDashboard(User $user): View
    {
        $upcoming = $user->appointments()
            ->with(['provider.user', 'service'])
            ->upcoming()->orderBy('start_at')->get();

        $past = $user->appointments()
            ->with(['provider.user', 'service'])
            ->where('start_at', '<', now())
            ->orderByDesc('start_at')->limit(10)->get();

        // --- Personal stats -------------------------------------------------
        $all = $user->appointments();
        $stats = [
            'upcoming' => (clone $all)->upcoming()->count(),
            'completed' => (clone $all)->where('status', Appointment::STATUS_COMPLETED)->count(),
            'missed' => (clone $all)->where('status', Appointment::STATUS_NO_SHOW)->count(),
            'total' => (clone $all)->count(),
        ];
        $finished = $stats['completed'] + $stats['missed'];
        $attendanceRate = $finished > 0 ? round($stats['completed'] / $finished * 100) : 100;

        // Visits over the last 6 months for a small trend chart.
        $trendLabels = [];
        $trendData = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->startOfMonth()->subMonths($i);
            $trendLabels[] = $month->format('M');
            $trendData[] = (clone $all)
                ->whereBetween('start_at', [$month, (clone $month)->endOfMonth()])
                ->count();
        }

        // Today's appointments + today's missed → drive the popup.
        $todays = $user->appointments()->with(['provider.user', 'service'])
            ->whereDate('start_at', today())
            ->whereNotIn('status', [Appointment::STATUS_CANCELLED])
            ->orderBy('start_at')->get();
        $todaysMissed = $todays->where('status', Appointment::STATUS_NO_SHOW);

        // A satisfied patient's shareable referral link (PRD "turning
        // patients into a referral channel").
        $referral = app(\App\Services\RetentionService::class)->referralLinkFor($user);

        return view('dashboard.patient', compact(
            'user', 'upcoming', 'past', 'stats', 'attendanceRate',
            'trendLabels', 'trendData', 'todays', 'todaysMissed', 'referral'
        ));
    }
}
