<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use App\Models\Service;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\PatientScoringService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WaitlistController extends Controller
{
    public function index(PatientScoringService $scoring): View
    {
        $entries = WaitlistEntry::with(['patient', 'service', 'provider.user'])
            ->orderByDesc('priority')->orderBy('created_at')->get();

        // Explainable/overridable (PRD): every priority comes with a plain
        // reason, computed live so it always reflects the patient's current
        // history even for entries added a while ago.
        $reasons = $entries->mapWithKeys(fn (WaitlistEntry $e) => [
            $e->id => $e->patient ? $scoring->scoreWithReason($e->patient)['reason'] : null,
        ]);

        $patients = User::role('patient')->orderBy('name')->get();
        $services = Service::where('is_active', true)->orderBy('name')->get();
        $providers = Provider::with('user')->get();

        return view('waitlist.index', compact('entries', 'patients', 'services', 'providers', 'reasons'));
    }

    public function store(Request $request, PatientScoringService $scoring): RedirectResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'exists:users,id'],
            'service_id' => ['nullable', 'exists:services,id'],
            'provider_id' => ['nullable', 'exists:providers,id'],
            'time_pref' => ['nullable', 'in:morning,afternoon,evening,any'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $data['clinic_id'] = auth()->user()->clinic_id ?? 1;

        // Priority defaults to the patient-value score (visit frequency,
        // attendance reliability, referrals) — staff can still override it
        // manually by passing an explicit value; the system never forces it.
        if (! isset($data['priority'])) {
            $patient = User::find($data['patient_id']);
            $data['priority'] = $scoring->score($patient);
        }

        WaitlistEntry::create($data);

        return back()->with('success', 'Added to waitlist.');
    }

    public function destroy(WaitlistEntry $waitlist): RedirectResponse
    {
        $waitlist->delete();

        return back()->with('success', 'Removed from waitlist.');
    }
}
