<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ProviderController extends Controller
{
    public function index(): View
    {
        $providers = Provider::with(['user', 'services'])->forCurrentClinic()->withCount('appointments')->get();

        return view('providers.index', compact('providers'));
    }

    public function create(): View
    {
        $services = Service::where('is_active', true)->orderBy('name')->get();

        return view('providers.create', compact('services'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'credentials' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'accepts_telehealth' => ['nullable', 'boolean'],
            'services' => ['nullable', 'array'],
            'services.*' => ['exists:services,id'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make('password'),
            'clinic_id' => auth()->user()->clinic_id,
            'is_active' => true,
        ]);
        $user->assignRole('provider');

        $provider = Provider::create([
            'user_id' => $user->id,
            'clinic_id' => auth()->user()->clinic_id ?? 1,
            'specialty' => $data['specialty'] ?? null,
            'credentials' => $data['credentials'] ?? null,
            'bio' => $data['bio'] ?? null,
            'accepts_telehealth' => $request->boolean('accepts_telehealth'),
        ]);
        $provider->services()->sync($data['services'] ?? []);

        return redirect()->route('providers.show', $provider)->with('success', 'Provider created.');
    }

    public function show(Provider $provider): View
    {
        $provider->load(['user', 'services', 'availabilities']);

        return view('providers.show', compact('provider'));
    }

    public function edit(Provider $provider): View
    {
        $provider->load(['user', 'services']);
        $services = Service::where('is_active', true)->orderBy('name')->get();

        return view('providers.edit', compact('provider', 'services'));
    }

    public function update(Request $request, Provider $provider): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,'.$provider->user_id],
            'phone' => ['nullable', 'string', 'max:30'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'credentials' => ['nullable', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'accepts_telehealth' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'services' => ['nullable', 'array'],
            'services.*' => ['exists:services,id'],
        ]);

        $provider->user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
        ]);

        $provider->update([
            'specialty' => $data['specialty'] ?? null,
            'credentials' => $data['credentials'] ?? null,
            'bio' => $data['bio'] ?? null,
            'accepts_telehealth' => $request->boolean('accepts_telehealth'),
            'is_active' => $request->boolean('is_active'),
        ]);
        $provider->services()->sync($data['services'] ?? []);

        return redirect()->route('providers.show', $provider)->with('success', 'Provider updated.');
    }

    public function destroy(Provider $provider): RedirectResponse
    {
        $provider->delete();

        return redirect()->route('providers.index')->with('success', 'Provider removed.');
    }
}
