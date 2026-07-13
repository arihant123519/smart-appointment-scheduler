<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Provider;
use App\Models\Service;
use App\Models\WalkInQueue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WalkInQueueController extends Controller
{
    public function index(): View
    {
        $entries = WalkInQueue::with(['patient', 'provider.user', 'service'])
            ->forCurrentClinic()
            ->whereIn('status', ['waiting', 'serving'])
            ->orderByRaw("status = 'serving' desc")
            ->orderBy('joined_at')
            ->get();

        $completedToday = WalkInQueue::with(['patient', 'provider.user', 'service'])
            ->forCurrentClinic()
            ->whereIn('status', ['done', 'left'])
            ->whereDate('completed_at', today())
            ->orderByDesc('completed_at')
            ->get();

        $stats = $this->queueStats($entries);

        $providers = Provider::with('user')->forCurrentClinic()->where('is_active', true)->get();
        $services = Service::forCurrentClinic()->where('is_active', true)->orderBy('name')->get();

        return view('walkins.index', compact('entries', 'completedToday', 'stats', 'providers', 'services'));
    }

    /**
     * Front-desk KPIs for the queue: today's throughput, how long people are
     * actually waiting, and a couple of longer-range rollups so a slow week
     * doesn't just vanish once the day's entries scroll off the table.
     *
     * @param  \Illuminate\Support\Collection<int, WalkInQueue>  $activeEntries  the currently waiting/serving entries, already loaded — reused to avoid an extra query
     * @return array<string, int|float|null>
     */
    private function queueStats($activeEntries): array
    {
        $base = WalkInQueue::forCurrentClinic();

        $doneToday = (clone $base)->where('status', 'done')->whereDate('completed_at', today())->count();
        $leftToday = (clone $base)->where('status', 'left')->whereDate('completed_at', today())->count();
        $doneThisWeek = (clone $base)->where('status', 'done')->where('completed_at', '>=', now()->subDays(6)->startOfDay())->count();
        $doneThisMonth = (clone $base)->where('status', 'done')->where('completed_at', '>=', now()->subDays(29)->startOfDay())->count();

        // Average wait (joined -> called in) for everyone actually called in
        // today — the clearest read on how long today's walk-ins are sitting
        // before being seen.
        $avgWaitMinutes = (clone $base)
            ->whereNotNull('called_at')
            ->whereDate('called_at', today())
            ->get(['joined_at', 'called_at'])
            ->avg(fn (WalkInQueue $e) => $e->joined_at->diffInMinutes($e->called_at, true));

        $processedToday = $doneToday + $leftToday;

        return [
            'waiting' => $activeEntries->where('status', 'waiting')->count(),
            'serving' => $activeEntries->where('status', 'serving')->count(),
            'done_today' => $doneToday,
            'left_today' => $leftToday,
            'avg_wait_minutes' => $avgWaitMinutes ? (int) round($avgWaitMinutes) : null,
            // Left/no-show as a share of everyone processed today — a rising
            // rate usually means the wait is too long, not a staffing fluke.
            'left_rate' => $processedToday > 0 ? round($leftToday / $processedToday * 100) : null,
            'done_this_week' => $doneThisWeek,
            'done_this_month' => $doneThisMonth,
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'provider_id' => ['nullable', 'exists:providers,id'],
            'service_id' => ['nullable', 'exists:services,id'],
        ]);

        $entry = WalkInQueue::create($data + [
            'clinic_id' => auth()->user()->clinic_id ?? 1,
            'token' => Str::random(32),
            'status' => 'waiting',
            'joined_at' => now(),
        ]);

        AuditLog::record('walk_in_joined', $entry, null, $entry->toArray());

        return back()->with('success', $entry->name.' added to the queue — position '.$entry->position.'.');
    }

    public function updateStatus(Request $request, WalkInQueue $walkin): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:waiting,serving,done,left'],
        ]);

        $before = $walkin->toArray();
        $walkin->status = $data['status'];

        match ($data['status']) {
            'serving' => $walkin->called_at = now(),
            'done', 'left' => $walkin->completed_at = now(),
            default => null,
        };

        $walkin->save();
        AuditLog::record('walk_in_status_changed', $walkin, $before, $walkin->toArray());

        return back()->with('success', 'Queue updated.');
    }

    public function destroy(WalkInQueue $walkin): RedirectResponse
    {
        AuditLog::record('walk_in_removed', $walkin, $walkin->toArray());
        $walkin->delete();

        return back()->with('success', 'Removed from queue.');
    }
}
