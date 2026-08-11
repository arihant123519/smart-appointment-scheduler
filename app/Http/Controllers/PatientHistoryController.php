<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Doctor-facing patient history — separate from PatientController (gated
 * behind `manage patients`, which the `provider` role doesn't hold). A
 * provider only ever sees patients they've personally treated at least once,
 * but once assigned, sees that patient's full clinic-wide history (every
 * provider's visits/consultations/prescriptions) for continuity of care.
 */
class PatientHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        abort_unless($user->provider || $user->hasRole('system_admin'), 403);

        $query = User::role('patient')->forCurrentClinic()->withCount('appointments')->orderBy('name');

        if (! $user->hasRole('system_admin')) {
            $query->whereHas('appointments', fn ($q) => $q->where('provider_id', $user->provider->id));
        }

        if ($request->filled('q')) {
            $term = $request->string('q');
            $query->where(fn ($w) => $w->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%"));
        }

        $patients = $query->get();

        return view('patient-history.index', compact('patients'));
    }

    public function show(User $patient): View
    {
        abort_unless($patient->hasRole('patient'), 404);

        $user = auth()->user();
        $isSystemAdmin = $user->hasRole('system_admin');
        $sameClinic = $patient->clinic_id === $user->clinic_id;
        $hasTreated = $user->provider && Appointment::where('patient_id', $patient->id)
            ->where('provider_id', $user->provider->id)
            ->exists();

        abort_unless($isSystemAdmin || ($sameClinic && $hasTreated), 403);

        $patient->load([
            'appointments' => fn ($q) => $q->with(['provider.user', 'service'])->orderByDesc('start_at'),
            'consultations' => fn ($q) => $q->with(['provider.user', 'prescription.items'])->orderByDesc('created_at'),
        ]);

        return view('patient-history.show', compact('patient'));
    }
}
