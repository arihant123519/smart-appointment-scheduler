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
                'color' => $a->status_hex,
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
        $user = auth()->user();
        if (! $user->hasRole('system_admin')) {
            abort_unless($appointment->clinic_id === $user->clinic_id, 404);
            if ($user->hasRole('provider') && ! $user->hasAnyRole(['clinic_admin', 'front_desk', 'billing'])) {
                abort_unless($user->provider && $appointment->provider_id === $user->provider->id, 403);
            }
        }

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

        // Re-derive lead-time reminders for the new time and notify the patient.
        $notifications = app(\App\Services\AppointmentNotificationService::class);
        $notifications->syncLeadTimes($appointment);
        $notifications->notifyRescheduled($appointment);

        return response()->json(['ok' => true]);
    }
}
