<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Provider;
use App\Models\Service;
use App\Models\SmsBookingSession;
use App\Models\User;
use App\Services\Messaging\MessageService;
use App\Support\PhoneNumber;
use Carbon\Carbon;

/**
 * Book by text message (PRD "book by text message"): reading out an entire
 * calendar over SMS isn't usable, so a cold text gets offered just the next
 * 3 open times, and the patient replies with a single digit to lock one in.
 *
 * Scoped to existing patients only (matched by phone on file) — like phone
 * OTP login, originating a brand-new account from a bare SMS isn't possible
 * without an email, which this app's User schema requires.
 */
class SmsBookingService
{
    public const SESSION_EXPIRES_MINUTES = 30;

    public const OFFER_COUNT = 3;

    public const SEARCH_DAYS = 14;

    public function __construct(
        private SchedulingService $scheduling,
        private MessageService $messages,
    ) {
    }

    public function handleInbound(string $rawPhone, string $text, int $clinicId): void
    {
        $phone = PhoneNumber::normalize($rawPhone);
        if ($phone === '') {
            return;
        }

        $user = User::where('phone', $phone)->role('patient')->first();
        if (! $user) {
            // No account to resolve a clinic from — use the clinic whose
            // webhook secret authenticated this request instead.
            $this->messages->sendSmsToPhone(
                $phone,
                "We couldn't find an account with this phone number. Please book online or call the clinic.",
                null, null, 'sms_booking', 'sms_booking_unknown_phone', $clinicId,
            );

            return;
        }

        $choice = trim($text);
        $session = SmsBookingSession::pending()->where('phone', $phone)->latest('id')->first();

        if ($session && ctype_digit($choice) && (int) $choice >= 1 && (int) $choice <= count($session->offered_slots)) {
            $this->confirmSlot($user, $session, (int) $choice);

            return;
        }

        $this->offerSlots($user, $phone);
    }

    private function offerSlots(User $user, string $phone): void
    {
        $service = $this->defaultServiceFor($user);
        if (! $service) {
            $this->messages->sendSmsToPhone(
                $phone, "Sorry, we couldn't find a bookable service right now. Please call the clinic.",
                $user, null, 'sms_booking', 'sms_booking_no_service',
            );

            return;
        }

        $slots = $this->findNextSlots($service, self::OFFER_COUNT);
        if (empty($slots)) {
            $this->messages->sendSmsToPhone(
                $phone, "No open times in the next two weeks for {$service->name}. Please call the clinic.",
                $user, null, 'sms_booking', 'sms_booking_no_slots',
            );

            return;
        }

        SmsBookingSession::pending()->where('phone', $phone)->update(['status' => 'expired']);

        SmsBookingSession::create([
            'phone' => $phone,
            'patient_id' => $user->id,
            'service_id' => $service->id,
            'offered_slots' => $slots,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(self::SESSION_EXPIRES_MINUTES),
        ]);

        $lines = [];
        foreach ($slots as $i => $slot) {
            $lines[] = ($i + 1).'. '.$slot['label'];
        }

        $body = "Reply with a number to book your {$service->name}:\n".implode("\n", $lines);
        $this->messages->sendSmsToPhone($phone, $body, $user, null, 'sms_booking', 'sms_booking_offer');
    }

    private function confirmSlot(User $user, SmsBookingSession $session, int $choice): void
    {
        $slot = $session->offered_slots[$choice - 1];

        try {
            $appointment = $this->scheduling->book([
                'patient_id' => $user->id,
                'provider_id' => $slot['provider_id'],
                'service_id' => $session->service_id,
                'start_at' => $slot['start'],
                'channel' => 'sms',
            ]);
        } catch (\RuntimeException $e) {
            $session->update(['status' => 'expired']);
            $this->messages->sendSmsToPhone(
                $session->phone, 'Sorry, that time was just taken. Text "book" to see new times.',
                $user, null, 'sms_booking', 'sms_booking_conflict',
            );

            return;
        }

        $session->update(['status' => 'booked', 'appointment_id' => $appointment->id]);
        AuditLog::record('created', $appointment, null, ['sms_booked' => true]);

        $when = Carbon::parse($slot['start'])->format('D, M j \a\t g:i A');
        $this->messages->sendSmsToPhone(
            $session->phone, "Booked! {$when} with {$appointment->provider->name}. See you then.",
            $user, $appointment, 'sms_booking', 'sms_booking_confirmed',
        );
    }

    private function defaultServiceFor(User $user): ?Service
    {
        $lastService = Appointment::where('patient_id', $user->id)
            ->whereNotNull('service_id')
            ->orderByDesc('start_at')
            ->with('service')
            ->first()?->service;

        if ($lastService && $lastService->is_active) {
            return $lastService;
        }

        return Service::where('clinic_id', $user->clinic_id)->where('is_active', true)->orderBy('name')->first()
            ?? Service::where('is_active', true)->orderBy('name')->first();
    }

    /**
     * Scan forward day by day, across every active provider offering this
     * service, collecting slots until $count are found or SEARCH_DAYS runs out.
     *
     * @return array<int, array{provider_id: int, start: string, label: string}>
     */
    private function findNextSlots(Service $service, int $count): array
    {
        $providers = Provider::where('is_active', true)
            ->whereHas('services', fn ($q) => $q->where('services.id', $service->id))
            ->get();

        if ($providers->isEmpty()) {
            $providers = Provider::where('is_active', true)->where('clinic_id', $service->clinic_id)->get();
        }

        $found = [];
        for ($d = 0; $d < self::SEARCH_DAYS && count($found) < $count; $d++) {
            $date = today()->addDays($d);

            foreach ($providers as $provider) {
                foreach ($this->scheduling->availableSlots($provider, $service, $date) as $slot) {
                    if (count($found) >= $count) {
                        break 2;
                    }

                    $found[] = [
                        'provider_id' => $provider->id,
                        'start' => $slot['start'],
                        'label' => Carbon::parse($slot['start'])->format('D, M j \a\t g:i A').' — '.$provider->name,
                    ];
                }
            }
        }

        return $found;
    }
}
