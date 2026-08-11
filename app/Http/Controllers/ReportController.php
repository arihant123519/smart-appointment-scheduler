<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Provider;
use App\Models\WhatsappConversation;
use App\Services\AI\AiService;
use App\Services\BenchmarkingService;
use App\Services\ExperimentService;
use App\Services\RevenueLeakService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request, RevenueLeakService $revenueLeaks, BenchmarkingService $benchmarking, ExperimentService $experiments): View
    {
        $isSystemAdmin = auth()->user()->hasRole('system_admin');
        $clinicId = $isSystemAdmin ? $request->integer('clinic_id') ?: null : auth()->user()->clinic_id;

        $m = $this->metrics($clinicId);
        $clinic = $clinicId ? Clinic::find($clinicId) : (auth()->user()->clinic ?? Clinic::first());

        return view('reports.index', [
            'noShowRate' => $m['no_show_rate_pct'],
            'noShows' => $m['no_shows'],
            'finished' => $m['finished'],
            'byProvider' => $m['by_provider'],
            'byChannel' => $m['by_channel'],
            'utilization' => $m['utilization'],
            'experiments' => $experiments->allResults(),
            'funnel' => $this->funnel($clinicId),
            'flow' => $this->flowMetrics($clinicId),
            'revenueLeaks' => $revenueLeaks->detect(),
            'heatmap' => $this->providerHeatmap($clinicId),
            'benchmark' => $clinic ? $benchmarking->compare($clinic) : null,
            'clinics' => $isSystemAdmin ? Clinic::orderBy('name')->get() : collect(),
            'selectedClinicId' => $clinicId,
        ]);
    }

    /**
     * Provider utilization by day-of-week × hour (30 days) — a finer-grained
     * companion to the flat per-provider bar chart below.
     *
     * @return array{providers: array<int,string>, days: array<int,string>, hours: array<int,int>, cells: array<string,int>}
     */
    private function providerHeatmap(?int $clinicId = null): array
    {
        $window = now()->subDays(30);
        $driver = DB::connection()->getDriverName();
        $dowExpr = $driver === 'sqlite' ? "CAST(strftime('%w', start_at) AS INTEGER)" : 'DAYOFWEEK(start_at) - 1';
        $hourExpr = $driver === 'sqlite' ? "CAST(strftime('%H', start_at) AS INTEGER)" : 'HOUR(start_at)';

        $rows = Appointment::where('start_at', '>=', $window)
            ->active()
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->forCurrentClinic()
            ->selectRaw("provider_id, $dowExpr as dow, $hourExpr as hour, count(*) as total")
            ->groupBy('provider_id', 'dow', 'hour')
            ->get();

        $providers = Provider::with('user')->where('is_active', true)
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->forCurrentClinic()
            ->get()->keyBy('id');
        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $hours = range(7, 19);

        $cells = [];
        foreach ($rows as $row) {
            if (! $providers->has($row->provider_id)) {
                continue;
            }
            $cells[$row->provider_id.'-'.$row->dow.'-'.$row->hour] = (int) $row->total;
        }

        return [
            'providers' => $providers->map(fn ($p) => $p->name)->all(),
            'days' => $days,
            'hours' => $hours,
            'cells' => $cells,
        ];
    }

    /**
     * Appointment status funnel over the last 30 days (booked → completed
     * vs. lost). Booked/Confirmed/Checked In/Completed are cumulative —
     * each counts appointments that reached at least that stage — so the
     * funnel narrows monotonically and every percentage stays <= 100% of
     * the Booked total. Cancelled/No Show are the two lost-from-the-funnel
     * outcomes, each counted out of that same Booked total.
     */
    private function funnel(?int $clinicId = null): array
    {
        $window = now()->subDays(30);

        $base = Appointment::where('start_at', '>=', $window)
            ->when($clinicId, fn ($q) => $q->where('clinic_id', $clinicId))
            ->forCurrentClinic();

        return [
            Appointment::STATUSES[Appointment::STATUS_BOOKED] => (clone $base)->count(),
            Appointment::STATUSES[Appointment::STATUS_CONFIRMED] => (clone $base)->whereIn('status', [
                Appointment::STATUS_CONFIRMED, Appointment::STATUS_CHECKED_IN, Appointment::STATUS_COMPLETED,
            ])->count(),
            Appointment::STATUSES[Appointment::STATUS_CHECKED_IN] => (clone $base)->whereIn('status', [
                Appointment::STATUS_CHECKED_IN, Appointment::STATUS_COMPLETED,
            ])->count(),
            Appointment::STATUSES[Appointment::STATUS_COMPLETED] => (clone $base)->where('status', Appointment::STATUS_COMPLETED)->count(),
            Appointment::STATUSES[Appointment::STATUS_CANCELLED] => (clone $base)->where('status', Appointment::STATUS_CANCELLED)->count(),
            Appointment::STATUSES[Appointment::STATUS_NO_SHOW] => (clone $base)->where('status', Appointment::STATUS_NO_SHOW)->count(),
        ];
    }

    /**
     * WhatsApp conversation flow performance over the last 30 days — the
     * headline metric is `rescued`: conversations whose linked appointment
     * survived (isn't cancelled/no-show), i.e. the flow likely kept the visit.
     */
    private function flowMetrics(?int $clinicId = null): array
    {
        $window = now()->subDays(30);
        $base = WhatsappConversation::where('started_at', '>=', $window)
            ->whereHas('appointment', fn ($q) => $q
                ->when($clinicId, fn ($qq) => $qq->where('clinic_id', $clinicId))
                ->forCurrentClinic());

        $started = (clone $base)->count();
        $completed = (clone $base)->where('status', 'completed')->count();
        $timedOut = (clone $base)->where('status', 'timed_out')->count();
        $escalated = (clone $base)->where('status', 'escalated')->count();
        $rescued = (clone $base)->whereHas('appointment', fn ($q) => $q->whereNotIn('status', [
            Appointment::STATUS_CANCELLED, Appointment::STATUS_NO_SHOW,
        ]))->count();

        return [
            'started' => $started,
            'completed' => $completed,
            'timed_out' => $timedOut,
            'escalated' => $escalated,
            'rescued' => $rescued,
            'lost' => max(0, $started - $rescued),
        ];
    }

    /**
     * Natural-language report queries (PRD §5.2). The admin asks a question in
     * plain English; we hand the question + the computed metrics (no PHI) to the
     * AI layer and return a plain-language answer.
     */
    public function ask(Request $request, AiService $ai): JsonResponse
    {
        $data = $request->validate(['question' => ['required', 'string', 'max:300']]);

        $answer = $ai->answerReportQuery($data['question'], $this->metricsForAi(), auth()->user()?->clinic_id);

        return response()->json(['answer' => $answer]);
    }

    /** Full metrics used to render the dashboard. */
    private function metrics(?int $clinicId = null): array
    {
        $window = now()->subDays(30);
        $scopeAppt = fn ($q) => $q->when($clinicId, fn ($qq) => $qq->where('clinic_id', $clinicId))->forCurrentClinic();
        $scopeProvider = fn ($q) => $q->when($clinicId, fn ($qq) => $qq->where('clinic_id', $clinicId))->forCurrentClinic();

        $finished = Appointment::where('start_at', '>=', $window)->tap($scopeAppt)
            ->whereIn('status', [Appointment::STATUS_COMPLETED, Appointment::STATUS_NO_SHOW])->count();
        $noShows = Appointment::where('start_at', '>=', $window)->tap($scopeAppt)
            ->where('status', Appointment::STATUS_NO_SHOW)->count();
        $noShowRate = $finished > 0 ? round($noShows / $finished * 100, 1) : 0;

        $byProvider = Provider::with('user')->tap($scopeProvider)->get()->map(function ($p) use ($window) {
            $fin = Appointment::where('provider_id', $p->id)->where('start_at', '>=', $window)
                ->whereIn('status', [Appointment::STATUS_COMPLETED, Appointment::STATUS_NO_SHOW])->count();
            $ns = Appointment::where('provider_id', $p->id)->where('start_at', '>=', $window)
                ->where('status', Appointment::STATUS_NO_SHOW)->count();

            return [
                'name' => $p->name,
                'total' => $fin,
                'no_shows' => $ns,
                'rate' => $fin > 0 ? round($ns / $fin * 100, 1) : 0,
            ];
        });

        $byChannel = Appointment::select('channel', DB::raw('count(*) as total'))->tap($scopeAppt)
            ->groupBy('channel')->pluck('total', 'channel')->toArray();

        $utilization = Provider::with('user')->tap($scopeProvider)
            ->withCount(['appointments' => fn ($q) => $q->where('start_at', '>=', $window)])
            ->get()->map(fn ($p) => ['name' => $p->name, 'count' => $p->appointments_count]);

        return [
            'finished' => $finished,
            'no_shows' => $noShows,
            'no_show_rate_pct' => $noShowRate,
            'by_provider' => $byProvider,
            'by_channel' => $byChannel,
            'utilization' => $utilization,
        ];
    }

    /** Aggregate, PHI-free metrics safe to send to the AI provider. */
    private function metricsForAi(): array
    {
        $m = $this->metrics();

        return [
            'window' => 'last 30 days',
            'appointments_finished' => $m['finished'],
            'no_shows' => $m['no_shows'],
            'no_show_rate_pct' => $m['no_show_rate_pct'],
            'no_show_rate_by_provider' => $m['by_provider']->map(fn ($r) => [
                'provider' => $r['name'], 'rate_pct' => $r['rate'], 'no_shows' => $r['no_shows'], 'total' => $r['total'],
            ])->all(),
            'bookings_by_channel' => $m['by_channel'],
            'appointments_by_provider' => $m['utilization']->map(fn ($r) => [
                'provider' => $r['name'], 'count' => $r['count'],
            ])->all(),
        ];
    }
}
