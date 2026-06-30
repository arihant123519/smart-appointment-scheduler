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

        // Appointments per day (last 14 days) for the chart
        $trend = Appointment::where('start_at', '>=', now()->subDays(13)->startOfDay())
            ->select(DB::raw('DATE(start_at) as d'), DB::raw('count(*) as total'))
            ->groupBy('d')->pluck('total', 'd')->toArray();

        $chartLabels = [];
        $chartData = [];
        for ($i = 13; $i >= 0; $i--) {
            $date = today()->subDays($i);
            $chartLabels[] = $date->format('M j');
            $chartData[] = $trend[$date->toDateString()] ?? 0;
        }

        $todaysAppointments = Appointment::with(['patient', 'provider.user', 'service'])
            ->forDay($today)->active()->orderBy('start_at')->get();

        $highRisk = Appointment::with(['patient', 'provider.user'])
            ->upcoming()->where('no_show_score', '>=', 70)
            ->orderByDesc('no_show_score')->limit(5)->get();

        // Missed appointments (past + not completed). Providers see only their
        // own; front desk / clinic admin / system admin see the whole clinic.
        $missedQuery = Appointment::missed()->with(['patient', 'provider.user', 'service']);
        if ($user->hasRole('provider') && ! $user->hasAnyRole(['clinic_admin', 'system_admin', 'front_desk']) && $user->provider) {
            $missedQuery->where('provider_id', $user->provider->id);
        }
        $missedCount = (clone $missedQuery)->count();
        $missedAppointments = $missedQuery->orderByDesc('start_at')->limit(8)->get();

        return view('dashboard.index', compact(
            'stats', 'noShowRate', 'statusBreakdown',
            'chartLabels', 'chartData', 'todaysAppointments', 'highRisk',
            'missedAppointments', 'missedCount'
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

        return view('dashboard.patient', compact('user', 'upcoming', 'past'));
    }
}
