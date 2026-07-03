<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
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
        Appointment::settleOverdue();

        // Patients get a focused self-service view.
        if ($user->hasRole('patient') && ! $user->hasAnyRole(['front_desk', 'provider', 'clinic_admin', 'system_admin', 'billing'])) {
            return $this->patientDashboard($user);
        }

        $today = today();

        $stats = [
            'today' => Appointment::forDay($today)->active()->count(),
            'upcoming' => Appointment::upcoming()->count(),
            'patients' => User::role('patient')->count(),
            'providers' => Provider::where('is_active', true)->count(),
        ];

        // No-show rate (last 30 days)
        $window = now()->subDays(30);
        $finished = Appointment::where('start_at', '>=', $window)
            ->whereIn('status', [Appointment::STATUS_COMPLETED, Appointment::STATUS_NO_SHOW])
            ->count();
        $noShows = Appointment::where('start_at', '>=', $window)
            ->where('status', Appointment::STATUS_NO_SHOW)->count();
        $noShowRate = $finished > 0 ? round($noShows / $finished * 100, 1) : 0;

        // Status breakdown
        $statusBreakdown = Appointment::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')->pluck('total', 'status')->toArray();

        // Completion rate (last 30 days): completed vs. everything that reached
        // a terminal outcome (completed + no-show + cancelled).
        $completed30 = Appointment::where('start_at', '>=', $window)
            ->where('status', Appointment::STATUS_COMPLETED)->count();
        $terminal30 = Appointment::where('start_at', '>=', $window)
            ->whereIn('status', [
                Appointment::STATUS_COMPLETED,
                Appointment::STATUS_NO_SHOW,
                Appointment::STATUS_CANCELLED,
            ])->count();
        $completionRate = $terminal30 > 0 ? round($completed30 / $terminal30 * 100, 1) : 0;

        // Appointments per day (last 14 days) — total booked vs. completed so the
        // chart tells a story rather than showing a single flat line.
        $rows = Appointment::where('start_at', '>=', now()->subDays(13)->startOfDay())
            ->select(
                DB::raw('DATE(start_at) as d'),
                DB::raw('count(*) as total'),
                DB::raw("sum(case when status = '".Appointment::STATUS_COMPLETED."' then 1 else 0 end) as completed"),
                DB::raw("sum(case when status = '".Appointment::STATUS_NO_SHOW."' then 1 else 0 end) as no_show")
            )
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
        $hourRows = Appointment::where('start_at', '>=', $window)
            ->active()
            ->select(DB::raw("$hourExpr as h"), DB::raw('count(*) as total'))
            ->groupBy('h')->pluck('total', 'h')->toArray();

        $hourLabels = [];
        $hourData = [];
        for ($h = 7; $h <= 19; $h++) {
            $hourLabels[] = \Carbon\Carbon::createFromTime($h)->format('g A');
            $hourData[] = (int) ($hourRows[$h] ?? 0);
        }

        // Top services (last 30 days) for a quick mix breakdown.
        $topServices = Appointment::where('appointments.start_at', '>=', $window)
            ->join('services', 'services.id', '=', 'appointments.service_id')
            ->select('services.name', DB::raw('count(*) as total'))
            ->groupBy('services.name')->orderByDesc('total')->limit(5)
            ->pluck('total', 'services.name')->toArray();

        $todaysAppointments = Appointment::with(['patient', 'provider.user', 'service'])
            ->forDay($today)->active()->orderBy('start_at')->get();

        $highRisk = Appointment::with(['patient', 'provider.user'])
            ->upcoming()->where('no_show_score', '>=', 70)
            ->orderByDesc('no_show_score')->limit(5)->get();

        // Missed appointments (past + not completed). Providers see only their
        // own; front desk / clinic admin / system admin see the whole clinic.
        $onlyOwnProvider = $user->hasRole('provider')
            && ! $user->hasAnyRole(['clinic_admin', 'system_admin', 'front_desk'])
            && $user->provider;

        $missedQuery = Appointment::missed()->with(['patient', 'provider.user', 'service']);
        if ($onlyOwnProvider) {
            $missedQuery->where('provider_id', $user->provider->id);
        }
        $missedCount = (clone $missedQuery)->count();
        $missedAppointments = (clone $missedQuery)->orderByDesc('start_at')->limit(8)->get();

        // Today's missed appointments specifically — these drive the alert popup.
        $todaysMissed = (clone $missedQuery)->whereDate('start_at', $today)
            ->orderBy('start_at')->get();

        return view('dashboard.index', compact(
            'stats', 'noShowRate', 'completionRate', 'statusBreakdown',
            'chartLabels', 'chartData', 'chartCompleted', 'chartNoShow',
            'hourLabels', 'hourData', 'topServices',
            'todaysAppointments', 'highRisk',
            'missedAppointments', 'missedCount', 'todaysMissed'
        ));
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

        return view('dashboard.patient', compact(
            'user', 'upcoming', 'past', 'stats', 'attendanceRate',
            'trendLabels', 'trendData', 'todays', 'todaysMissed'
        ));
    }
}
