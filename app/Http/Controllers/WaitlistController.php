<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use App\Models\Service;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WaitlistController extends Controller
{
    public function index(): View
    {
        $entries = WaitlistEntry::with(['patient', 'service', 'provider.user'])
            ->orderBy('priority')->orderBy('created_at')->get();

        $patients = User::role('patient')->orderBy('name')->get();
        $services = Service::where('is_active', true)->orderBy('name')->get();
        $providers = Provider::with('user')->get();

        return view('waitlist.index', compact('entries', 'patients', 'services', 'providers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'patient_id' => ['required', 'exists:users,id'],
            'service_id' => ['nullable', 'exists:services,id'],
            'provider_id' => ['nullable', 'exists:providers,id'],
            'time_pref' => ['nullable', 'in:morning,afternoon,evening,any'],
            'priority' => ['nullable', 'integer', 'min:1', 'max:10'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $data['clinic_id'] = auth()->user()->clinic_id ?? 1;
        $data['priority'] ??= 5;

        WaitlistEntry::create($data);

        return back()->with('success', 'Added to waitlist.');
    }

    public function destroy(WaitlistEntry $waitlist): RedirectResponse
    {
        $waitlist->delete();

        return back()->with('success', 'Removed from waitlist.');
    }
}
