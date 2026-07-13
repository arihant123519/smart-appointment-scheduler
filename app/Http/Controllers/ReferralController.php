<?php

namespace App\Http\Controllers;

use App\Models\Referral;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReferralController extends Controller
{
    /**
     * Public redemption landing page (token-authenticated, no login needed to
     * view). Stashes the token in the session so it survives register/login,
     * then sends the visitor on to book — BookingController::store redeems it
     * once their booking actually succeeds.
     */
    public function show(string $token): RedirectResponse
    {
        $referral = Referral::where('token', $token)->where('status', 'pending')->first();

        if (! $referral) {
            return redirect()->route('login')->with('error', 'This referral link is no longer valid.');
        }

        session(['referral_token' => $token]);

        return auth()->check()
            ? redirect()->route('booking.create')
            : redirect()->route('register');
    }

    /** Staff-facing list of referrals and their redemption status. */
    public function index(): View
    {
        $referrals = Referral::with(['referrerPatient', 'referrerProvider.user', 'appointment.patient'])
            ->forCurrentClinic()->orderByDesc('created_at')->get();

        $total = $referrals->count();
        $booked = $referrals->where('status', 'booked')->count();
        $stats = [
            'total' => $total,
            'booked' => $booked,
            'conversion_rate' => $total > 0 ? round($booked / $total * 100, 1) : 0,
        ];

        // Turning patients into a referral channel: who's actually bringing people in.
        $topReferrers = $referrals->whereNotNull('referrer_patient_id')
            ->groupBy('referrer_patient_id')
            ->map(fn ($group) => [
                'patient' => $group->first()->referrerPatient,
                'total' => $group->count(),
                'booked' => $group->where('status', 'booked')->count(),
            ])
            ->sortByDesc('booked')
            ->take(5)
            ->values();

        return view('referrals.index', compact('referrals', 'stats', 'topReferrers'));
    }
}
