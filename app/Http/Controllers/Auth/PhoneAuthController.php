<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\OtpService;
use App\Support\PhoneNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Phone + OTP login, parallel to the existing email/password path in
 * AuthController (which this never touches). Scoped to LOGIN of an existing
 * account — a phone number with no matching user is told the same generic
 * "code sent" message as a real one, so this endpoint can't be used to probe
 * which numbers are registered.
 */
class PhoneAuthController extends Controller
{
    public function showRequest(): View
    {
        return view('auth.phone-request');
    }

    public function sendCode(Request $request, OtpService $otp): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
        ]);
        $phone = PhoneNumber::normalize($data['phone']);

        $user = User::where('phone', $phone)->first();
        if ($user) {
            $otp->issue($user);
        }

        session(['otp_phone' => $phone]);

        return redirect()->route('login.phone.verify')
            ->with('status', 'If that phone number has an account, a verification code has been sent via WhatsApp.');
    }

    public function showVerify(): View|RedirectResponse
    {
        if (! session('otp_phone')) {
            return redirect()->route('login.phone');
        }

        return view('auth.phone-verify');
    }

    public function verifyCode(Request $request, OtpService $otp): RedirectResponse
    {
        $phone = session('otp_phone');
        abort_unless($phone, 419);

        $data = $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        if (! $otp->verify($phone, $data['code'])) {
            throw ValidationException::withMessages([
                'code' => 'That code is invalid or has expired.',
            ]);
        }

        $user = User::where('phone', $phone)->first();
        if (! $user || ! $user->is_active) {
            throw ValidationException::withMessages([
                'code' => 'This account is unavailable. Please contact your administrator.',
            ]);
        }

        Auth::login($user);
        $request->session()->forget('otp_phone');
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now()])->save();
        AuditLog::record('login_via_otp', $user);

        return redirect()->intended(route('dashboard'));
    }
}
