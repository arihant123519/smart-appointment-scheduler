<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Provider;
use App\Models\Service;
use App\Services\SchedulingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiController extends Controller
{
    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user()->only('id', 'name', 'email', 'clinic_id'));
    }

    public function appointments(Request $request): JsonResponse
    {
        $query = Appointment::with(['patient:id,name', 'provider.user:id,name', 'service:id,name'])
            ->forCurrentClinic()->orderByDesc('start_at');

        if ($request->user()->hasRole('patient')) {
            $query->where('patient_id', $request->user()->id);
        }

        return response()->json($query->limit(100)->get());
    }

    public function show(Appointment $appointment): JsonResponse
    {
        return response()->json($appointment->load(['patient:id,name', 'provider.user:id,name', 'service:id,name']));
    }

    public function providers(): JsonResponse
    {
        return response()->json(
            Provider::with('user:id,name')->forCurrentClinic()->where('is_active', true)->get()
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'specialty' => $p->specialty])
        );
    }

    public function services(): JsonResponse
    {
        return response()->json(
            Service::forCurrentClinic()->where('is_active', true)->get(['id', 'name', 'duration', 'price'])
        );
    }

    public function slots(Request $request, SchedulingService $scheduling): JsonResponse
    {
        $data = $request->validate([
            'provider_id' => ['required', 'exists:providers,id'],
            'service_id' => ['required', 'exists:services,id'],
            'date' => ['required', 'date'],
        ]);

        return response()->json([
            'slots' => $scheduling->availableSlots(
                Provider::findOrFail($data['provider_id']),
                Service::findOrFail($data['service_id']),
                Carbon::parse($data['date']),
            ),
        ]);
    }

    public function book(Request $request, SchedulingService $scheduling): JsonResponse
    {
        $data = $request->validate([
            'provider_id' => ['required', 'exists:providers,id'],
            'service_id' => ['required', 'exists:services,id'],
            'start_at' => ['required', 'date', 'after:now'],
            'patient_id' => ['nullable', 'exists:users,id'],
        ]);

        $patientId = $request->user()->hasRole('patient') ? $request->user()->id : ($data['patient_id'] ?? $request->user()->id);

        try {
            $appointment = $scheduling->book([
                'patient_id' => $patientId,
                'provider_id' => $data['provider_id'],
                'service_id' => $data['service_id'],
                'start_at' => $data['start_at'],
                'channel' => 'api',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json($appointment, 201);
    }
}
