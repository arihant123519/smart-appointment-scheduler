<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Provider;
use App\Services\SchedulingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function index(): View
    {
        $providers = Provider::with('user')->where('is_active', true)->get();

        return view('calendar.index', compact('providers'));
    }

    public function events(Request $request): JsonResponse
    {
        $query = Appointment::with(['patient', 'provider.user', 'service'])
            ->forCurrentClinic()
            ->active()
            ->whereBetween('start_at', [
                $request->query('start', now()->subMonth()),
                $request->query('end', now()->addMonth()),
            ]);

        if ($request->filled('provider_id')) {
            $query->where('provider_id', $request->integer('provider_id'));
        }

        // Providers only see their own calendar.
        $user = auth()->user();
        if ($user->hasRole('provider') && $user->provider) {
            $query->where('provider_id', $user->provider->id);
        }

        $events = $query->get()->map(function (Appointment $a) {
            return [
                'id' => $a->id,
                'title' => ($a->patient->name ?? 'Patient').' — '.($a->service->name ?? 'Visit'),
                'start' => $a->start_at->toIso8601String(),
                'end' => $a->end_at->toIso8601String(),
                'color' => $a->service->color ?? '#5955D1',
                'url' => route('appointments.show', $a),
                'extendedProps' => [
                    'status' => $a->status_label,
                    'provider' => $a->provider->name ?? '',
                    'risk' => $a->risk_level,
                ],
            ];
        });

        return response()->json($events);
    }

    /** Drag-and-drop reschedule from the calendar. */
    public function reschedule(Request $request, Appointment $appointment, SchedulingService $scheduling): JsonResponse
    {
        $data = $request->validate([
            'start' => ['required', 'date'],
            'end' => ['nullable', 'date'],
        ]);

        try {
            $scheduling->reschedule(
                $appointment,
                Carbon::parse($data['start']),
                isset($data['end']) ? Carbon::parse($data['end']) : null,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        AuditLog::record('rescheduled_drag', $appointment);

        return response()->json(['ok' => true]);
    }
}
