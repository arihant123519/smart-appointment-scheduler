<?php

namespace App\Services;

use App\Models\Otp;
use App\Models\User;
use App\Services\Messaging\MessageService;
use App\Support\PhoneNumber;

/**
 * Phone + one-time-code login (PRD "booking without needing to create an
 * account" / "phone + OTP, never a password"). This pass covers LOGIN for an
 * existing account only — the code is delivered over WhatsApp, reusing the
 * same driver as reminders (Gupshup in production, `log` locally). A raw-SMS
 * fallback channel lands naturally once Phase 5 builds SMS sending; until
 * then WhatsApp is the sole delivery channel, so a patient without WhatsApp
 * still logs in with email + password as today.
 */
class OtpService
{
    public const EXPIRES_MINUTES = 5;

    public const MAX_ATTEMPTS = 5;

    public function __construct(private MessageService $messages)
    {
    }

    public function issue(User $user): Otp
    {
        $phone = PhoneNumber::normalize($user->phone);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $otp = Otp::create([
            'phone' => $phone,
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addMinutes(self::EXPIRES_MINUTES),
        ]);

        $this->messages->send(
            $user,
            'Your verification code',
            "Your verification code is {$code}. It expires in ".self::EXPIRES_MINUTES." minutes.\nNever share this code with anyone.",
            'whatsapp',
        );

        return $otp;
    }

    public function verify(string $phone, string $code): bool
    {
        $phone = PhoneNumber::normalize($phone);

        $otp = Otp::where('phone', $phone)
            ->whereNull('consumed_at')
            ->where('expires_at', '>=', now())
            ->orderByDesc('id')
            ->first();

        if (! $otp || $otp->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        $otp->increment('attempts');

        if (! hash_equals($otp->code_hash, hash('sha256', $code))) {
            return false;
        }

        $otp->update(['consumed_at' => now()]);

        return true;
    }
}
