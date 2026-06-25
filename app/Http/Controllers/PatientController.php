<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PatientController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::role('patient')->withCount('appointments')->orderBy('name');

        if ($request->filled('q')) {
            $term = $request->string('q');
            $query->where(fn ($w) => $w->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%"));
        }

        $patients = $query->get();

        return view('patients.index', compact('patients'));
    }

    public function create(): View
    {
        return view('patients.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:male,female,other'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $patient = User::create(array_merge($data, [
            'password' => Hash::make('password'),
            'clinic_id' => auth()->user()->clinic_id,
            'is_active' => true,
        ]));
        $patient->assignRole('patient');

        return redirect()->route('patients.show', $patient)->with('success', 'Patient created.');
    }

    public function show(User $patient): View
    {
        abort_unless($patient->hasRole('patient'), 404);
        $patient->load(['appointments' => fn ($q) => $q->with(['provider.user', 'service'])->orderByDesc('start_at')]);

        return view('patients.show', compact('patient'));
    }

    public function edit(User $patient): View
    {
        abort_unless($patient->hasRole('patient'), 404);

        return view('patients.edit', compact('patient'));
    }

    public function update(Request $request, User $patient): RedirectResponse
    {
        abort_unless($patient->hasRole('patient'), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,'.$patient->id],
            'phone' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:male,female,other'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        $patient->update($data);

        return redirect()->route('patients.show', $patient)->with('success', 'Patient updated.');
    }

    public function destroy(User $patient): RedirectResponse
    {
        abort_unless($patient->hasRole('patient'), 404);
        $patient->delete();

        return redirect()->route('patients.index')->with('success', 'Patient removed.');
    }
}
