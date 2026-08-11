<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        $user = auth()->user();
        $tokens = $user->tokens()->latest()->get();

        return view('profile.edit', compact('user', 'tokens'));
    }

    public function createToken(Request $request): RedirectResponse
    {
        $data = $request->validate(['token_name' => ['required', 'string', 'max:60']]);
        $token = auth()->user()->createToken($data['token_name'])->plainTextToken;

        return back()->with('success', 'API token created. Copy it now — it will not be shown again: '.$token);
    }

    public function deleteToken(int $tokenId): RedirectResponse
    {
        auth()->user()->tokens()->where('id', $tokenId)->delete();

        return back()->with('success', 'API token revoked.');
    }

    public function update(Request $request): RedirectResponse
    {
        $user = auth()->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'locale' => ['nullable', 'string', 'max:10'],
        ]);

        $user->update($data);

        return back()->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        auth()->user()->update([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
        ]);

        return back()->with('success', 'Password changed.');
    }

    /** Registration number + signature used on this doctor's own prescriptions. */
    public function updateProviderDetails(Request $request): RedirectResponse
    {
        $provider = auth()->user()->provider;
        abort_unless($provider, 404);

        $data = $request->validate([
            'registration_no' => ['nullable', 'string', 'max:100'],
            'signature' => ['nullable', 'image', 'max:1024'],
        ]);

        if ($request->hasFile('signature')) {
            if ($provider->signature_path) {
                Storage::disk('public')->delete($provider->signature_path);
            }
            $data['signature_path'] = $request->file('signature')->store('provider-signatures', 'public');
        }
        unset($data['signature']);

        $provider->update($data);

        return back()->with('success', 'Prescription details updated.');
    }
}
