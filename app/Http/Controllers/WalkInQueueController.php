<?php

namespace App\Http\Controllers;

use App\Events\WalkInQueueUpdated;
use App\Models\AuditLog;
use App\Models\Provider;
use App\Models\Service;
use App\Models\WalkInQueue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WalkInQueueController extends Controller
{
    public function index(): View
    {
        $clinicId = auth()->user()->clinic_id ?? 1;

        [$entries, $completedToday] = WalkInQueue::activeAndCompletedForClinic($clinicId);
        $stats = WalkInQueue::statsForClinic($clinicId, $entries);

        $providers = Provider::with('user')->forCurrentClinic()->where('is_active', true)->get();
        $services = Service::forCurrentClinic()->where('is_active', true)->orderBy('name')->get();

        return view('walkins.index', compact('entries', 'completedToday', 'stats', 'providers', 'services'));
    }

    /**
     * JSON snapshot of the queue (stats + server-rendered row/modal HTML),
     * polled by the front-end after a walkins.updated broadcast ping so the
     * page updates without a full reload. Kept as a same-origin, authenticated
     * fetch — deliberately not the data carried by the broadcast itself — so
     * patient names/phone numbers never pass through the third-party Pusher
     * relay (see WalkInQueueUpdated).
     */
    public function partial(): JsonResponse
    {
        $clinicId = auth()->user()->clinic_id ?? 1;

        [$entries, $completedToday] = WalkInQueue::activeAndCompletedForClinic($clinicId);
        $stats = WalkInQueue::statsForClinic($clinicId, $entries);

        return response()->json([
            'stats' => $stats,
            'waitingCount' => $entries->where('status', 'waiting')->count(),
            'completedCount' => $completedToday->count(),
            'waitingHtml' => view('walkins.partials.waiting-rows', ['entries' => $entries])->render(),
            'completedHtml' => view('walkins.partials.completed-rows', ['entries' => $completedToday])->render(),
            'modalsHtml' => view('walkins.partials.patient-modals', ['entries' => $entries])->render(),
        ]);
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
        event(new WalkInQueueUpdated($entry->clinic_id));

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
        event(new WalkInQueueUpdated($walkin->clinic_id));

        return back()->with('success', 'Queue updated.');
    }

    public function destroy(WalkInQueue $walkin): RedirectResponse
    {
        $clinicId = $walkin->clinic_id;

        AuditLog::record('walk_in_removed', $walkin, $walkin->toArray());
        $walkin->delete();
        event(new WalkInQueueUpdated($clinicId));

        return back()->with('success', 'Removed from queue.');
    }
}
