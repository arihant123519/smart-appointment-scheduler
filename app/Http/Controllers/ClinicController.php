<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClinicController extends Controller
{
    public function index(): View
    {
        $clinics = Clinic::withCount(['providers', 'services', 'appointments'])->get();

        return view('clinics.index', compact('clinics'));
    }

    public function create(): View
    {
        return view('clinics.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = Str::slug($data['name']).'-'.Str::random(4);

        // A clinic is unusable until someone can log in and run it — the
        // admin account is created in the same transaction as the clinic
        // itself, never as an optional afterthought.
        $admin = $request->validate([
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'unique:users,email'],
            'admin_password' => ['required', 'string', 'min:8'],
        ]);

        DB::transaction(function () use ($data, $admin) {
            $clinic = Clinic::create($data);

            $user = User::create([
                'name' => $admin['admin_name'],
                'email' => $admin['admin_email'],
                'password' => Hash::make($admin['admin_password']),
                'clinic_id' => $clinic->id,
                'is_active' => true,
            ]);
            $user->assignRole('clinic_admin');
        });

        return redirect()->route('clinics.index')->with('success', 'Clinic created, with its admin account.');
    }

    public function edit(Clinic $clinic): View
    {
        $clinicAdmins = $clinic->users()->role('clinic_admin')->orderBy('name')->get();

        return view('clinics.edit', compact('clinic', 'clinicAdmins'));
    }

    public function update(Request $request, Clinic $clinic): RedirectResponse
    {
        $clinic->update($this->validated($request, $clinic));

        // Adding another clinic admin here is optional (the clinic already
        // has one from creation) — only act if the admin fields were
        // actually filled in.
        if ($request->filled('admin_name') || $request->filled('admin_email') || $request->filled('admin_password')) {
            $admin = $request->validate([
                'admin_name' => ['required', 'string', 'max:255'],
                'admin_email' => ['required', 'email', 'unique:users,email'],
                'admin_password' => ['required', 'string', 'min:8'],
            ]);

            $user = User::create([
                'name' => $admin['admin_name'],
                'email' => $admin['admin_email'],
                'password' => Hash::make($admin['admin_password']),
                'clinic_id' => $clinic->id,
                'is_active' => true,
            ]);
            $user->assignRole('clinic_admin');
        }

        return redirect()->route('clinics.index')->with('success', 'Clinic updated.');
    }

    private function validated(Request $request, ?Clinic $clinic = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'is_active' => ['nullable', 'boolean'],
            'compliance_agreements_signed' => ['nullable', 'boolean'],
            'abdm_health_id' => ['nullable', 'string', 'max:64'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);
        $data['is_active'] = $request->boolean('is_active', true);
        $data['timezone'] ??= 'UTC';

        if ($request->hasFile('logo')) {
            if ($clinic?->logo_path) {
                Storage::disk('public')->delete($clinic->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('clinic-logos', 'public');
        }
        unset($data['logo']);

        // Technical scaffolding for the HIPAA/DPDP gate: a clinic can only be
        // activated once someone has checked that the actual signed
        // agreements are in place. This system doesn't create or verify the
        // agreements themselves — it only tracks and enforces the flag.
        $alreadySigned = $clinic?->compliance_agreements_signed_at !== null;
        $agreementsSigned = $request->boolean('compliance_agreements_signed') || $alreadySigned;
        $data['compliance_agreements_signed_at'] = $agreementsSigned
            ? ($clinic?->compliance_agreements_signed_at ?? now())
            : null;
        unset($data['compliance_agreements_signed']);

        if ($data['is_active'] && ! $agreementsSigned) {
            throw ValidationException::withMessages([
                'is_active' => 'This clinic cannot be activated until compliance agreements (HIPAA/DPDP) are marked as signed.',
            ]);
        }

        return $data;
    }
}
