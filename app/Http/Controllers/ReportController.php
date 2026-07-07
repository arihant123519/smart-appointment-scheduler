<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Provider;
use App\Models\WhatsappConversation;
use App\Services\AI\AiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        $m = $this->metrics();

        return view('reports.index', [
            'noShowRate' => $m['no_show_rate_pct'],
            'noShows' => $m['no_shows'],
            'finished' => $m['finished'],
            'byProvider' => $m['by_provider'],
            'byChannel' => $m['by_channel'],
            'utilization' => $m['utilization'],
            'funnel' => $this->funnel(),
            'flow' => $this->flowMetrics(),
        ]);
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

        $answer = $ai->answerReportQuery($data['question'], $this->metricsForAi());

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
