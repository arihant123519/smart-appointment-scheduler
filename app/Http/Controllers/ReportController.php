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
    public function index(RevenueLeakService $revenueLeaks, BenchmarkingService $benchmarking, ExperimentService $experiments): View
    {
        $m = $this->metrics();
        $clinic = auth()->user()->clinic ?? Clinic::first();

        return view('reports.index', [
            'noShowRate' => $m['no_show_rate_pct'],
            'noShows' => $m['no_shows'],
            'finished' => $m['finished'],
            'byProvider' => $m['by_provider'],
            'byChannel' => $m['by_channel'],
            'utilization' => $m['utilization'],
            'experiments' => $experiments->allResults(),
            'funnel' => $this->funnel(),
            'flow' => $this->flowMetrics(),
            'revenueLeaks' => $revenueLeaks->detect(),
            'heatmap' => $this->providerHeatmap(),
            'benchmark' => $clinic ? $benchmarking->compare($clinic) : null,
        ]);
    }

    /**
     * Provider utilization by day-of-week × hour (30 days) — a finer-grained
     * companion to the flat per-provider bar chart below.
     *
     * @return array{providers: array<int,string>, days: array<int,string>, hours: array<int,int>, cells: array<string,int>}
     */
    private function providerHeatmap(): array
    {
        $window = now()->subDays(30);
        $driver = DB::connection()->getDriverName();
        $dowExpr = $driver === 'sqlite' ? "CAST(strftime('%w', start_at) AS INTEGER)" : 'DAYOFWEEK(start_at) - 1';
        $hourExpr = $driver === 'sqlite' ? "CAST(strftime('%H', start_at) AS INTEGER)" : 'HOUR(start_at)';

        $rows = Appointment::where('start_at', '>=', $window)
            ->active()
            ->selectRaw("provider_id, $dowExpr as dow, $hourExpr as hour, count(*) as total")
            ->groupBy('provider_id', 'dow', 'hour')
            ->get();

        $providers = Provider::with('user')->where('is_active', true)->get()->keyBy('id');
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

    /** Appointment status funnel over the last 30 days (booked → completed vs. lost). */
    private function funnel(): array
    {
        $window = now()->subDays(30);

        $counts = Appointment::where('start_at', '>=', $window)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $order = [
            Appointment::STATUS_BOOKED, Appointment::STATUS_CONFIRMED, Appointment::STATUS_CHECKED_IN,
            Appointment::STATUS_COMPLETED, Appointment::STATUS_CANCELLED, Appointment::STATUS_NO_SHOW,
        ];

        return collect($order)->mapWithKeys(fn ($status) => [
            (Appointment::STATUSES[$status] ?? $status) => (int) ($counts[$status] ?? 0),
        ])->all();
    }

    /**
     * WhatsApp conversation flow performance over the last 30 days — the
     * headline metric is `rescued`: conversations whose linked appointment
     * survived (isn't cancelled/no-show), i.e. the flow likely kept the visit.
     */
    private function flowMetrics(): array
    {
        $window = now()->subDays(30);
        $base = WhatsappConversation::where('started_at', '>=', $window);

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
    private function metrics(): array
    {
        $window = now()->subDays(30);

        $finished = Appointment::where('start_at', '>=', $window)
            ->whereIn('status', [Appointment::STATUS_COMPLETED, Appointment::STATUS_NO_SHOW])->count();
        $noShows = Appointment::where('start_at', '>=', $window)
            ->where('status', Appointment::STATUS_NO_SHOW)->count();
        $noShowRate = $finished > 0 ? round($noShows / $finished * 100, 1) : 0;

        $byProvider = Provider::with('user')->get()->map(function ($p) use ($window) {
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

        $byChannel = Appointment::select('channel', DB::raw('count(*) as total'))
            ->groupBy('channel')->pluck('total', 'channel')->toArray();

        $utilization = Provider::with('user')->withCount(['appointments' => fn ($q) => $q->where('start_at', '>=', $window)])
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
