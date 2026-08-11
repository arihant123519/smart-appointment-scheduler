<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Clinic;
use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Payment handling. The default driver is `manual` (records cash/at-desk
 * payments and marks them paid) so copay and deposit tracking works with no
 * gateway. Each clinic sets its own driver + credentials from its own
 * Settings → Integrations page (see {@see Setting::getForClinic()}) — a
 * clinic that saves real Razorpay keys gets real charges; every other clinic
 * keeps working on the manual driver until it configures its own.
 *
 * Set a clinic's driver to `razorpay` + Razorpay key id/secret to actually
 * create Razorpay Orders (raw REST calls via Http, matching how this app
 * talks to Gupshup — no razorpay-php SDK dependency).
 *
 * Deposits: a slot is held the instant the appointment row is created (the
 * DB unique constraint guards that, same as any other booking) — what a
 * deposit gates is whether the booking *sticks*. createDeposit() opens a
 * pending Payment with a short expiry; payments:release-abandoned frees the
 * slot if it's never paid. confirmDeposit() marks it paid (staff collecting
 * cash at the desk, or the Razorpay webhook confirming a captured payment).
 * forfeitOrRefundDeposit() applies the service's cancellation policy.
 */
class PaymentService
{
    /** Minutes a pending deposit is held before the slot is released. */
    public const ABANDON_AFTER_MINUTES = 10;

    /** The configured driver for a specific clinic (falls back to the .env default for that clinic). */
    public function driver(int $clinicId): string
    {
        return Setting::getForClinic($clinicId, 'payments.driver', config('services.payments.driver', 'manual'));
    }

    /** Record a copay/fee for an appointment. */
    public function charge(Appointment $appointment, float $amount, string $type = 'copay', string $method = 'cash'): Payment
    {
        $driver = $this->driver($appointment->clinic_id);
        $paid = $driver === 'manual';

        $payment = Payment::create([
            'appointment_id' => $appointment->id,
            'patient_id' => $appointment->patient_id,
            'amount' => $amount,
            'currency' => 'INR',
            'type' => $type,
            'method' => $method,
            'provider_ref' => null,
            'status' => $paid ? 'paid' : 'pending',
            'paid_at' => $paid ? now() : null,
        ]);

        if (! $paid && $method === 'card') {
            $this->createRazorpayOrder($payment, $appointment->clinic_id);
        }

        return $payment;
    }

    public function refund(Payment $payment): Payment
    {
        $refund = Payment::create([
            'appointment_id' => $payment->appointment_id,
            'patient_id' => $payment->patient_id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'type' => 'refund',
            'method' => $payment->method,
            'status' => 'refunded',
            'paid_at' => now(),
        ]);

        // Flip the original transaction so it can't be refunded a second time
        // and so it stops being counted as "collected" in totals.
        if ($payment->type !== 'refund') {
            $payment->update(['status' => 'refunded']);
        }

        return $refund;
    }

    // --- Deposits -------------------------------------------------------------

    /**
     * Open a pending deposit for an appointment whose service requires one.
     * Returns null if the service doesn't require a deposit.
     */
    public function createDeposit(Appointment $appointment): ?Payment
    {
        $appointment->loadMissing('service');
        $service = $appointment->service;

        if (! $service || ! $service->deposit_required || ! $service->deposit_amount) {
            return null;
        }

        $driver = $this->driver($appointment->clinic_id);

        $payment = Payment::create([
            'appointment_id' => $appointment->id,
            'patient_id' => $appointment->patient_id,
            'amount' => $service->deposit_amount,
            'currency' => 'INR',
            'type' => 'deposit',
            'method' => $driver === 'razorpay' ? 'card' : 'cash',
            'status' => 'pending',
            'expires_at' => now()->addMinutes(self::ABANDON_AFTER_MINUTES),
        ]);

        if ($driver === 'razorpay') {
            $this->createRazorpayOrder($payment, $appointment->clinic_id);
        }

        return $payment;
    }

    /** Staff confirms a deposit was collected (manual driver), or a webhook confirms a card charge. */
    public function confirmDeposit(Payment $payment): Payment
    {
        $payment->update(['status' => 'paid', 'paid_at' => now()]);

        return $payment;
    }

    /**
     * Release every deposit that's sat pending past its expiry: mark the
     * payment abandoned and free the slot (soft-delete releases the
     * provider/start_at unique constraint, same as any other cancellation),
     * offering it straight to the waitlist rather than leaving it empty.
     *
     * @return int  number of appointments released
     */
    public function releaseAbandoned(): int
    {
        $released = 0;

        Payment::abandonedDeposits()->with('appointment')->get()
            ->each(function (Payment $payment) use (&$released) {
                $payment->update(['status' => 'abandoned']);

                $appointment = $payment->appointment;
                if (! $appointment || $appointment->trashed()) {
                    return;
                }

                $appointment->update([
                    'status' => Appointment::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'cancellation_reason' => 'Deposit not completed in time',
                ]);
                $appointment->delete();
                AuditLog::record('deposit_abandoned_released_slot', $appointment, null, $appointment->toArray());

                app(WaitlistService::class)->offerFreedSlot($appointment);
                $released++;
            });

        return $released;
    }

    /**
     * Forfeit any paid deposit for appointments that were just bulk-settled
     * to no-show (settleOverdue()'s query-builder update bypasses the model
     * event that would otherwise trigger this).
     *
     * @param  iterable<int>  $appointmentIds
     */
    public function forfeitForNoShowIds(iterable $appointmentIds): void
    {
        $ids = collect($appointmentIds);
        if ($ids->isEmpty()) {
            return;
        }

        Appointment::whereIn('id', $ids)->with('service', 'payments')->get()
            ->each(fn (Appointment $appointment) => $this->forfeitOrRefundDeposit($appointment, alwaysForfeit: true));
    }

    /**
     * Apply the service's deposit-cancellation policy: forfeit (no refund)
     * if cancelling within deposit_forfeit_hours of the visit, otherwise
     * refund in full. No-op if there's no paid deposit on this appointment.
     */
    public function forfeitOrRefundDeposit(Appointment $appointment, bool $alwaysForfeit = false): ?Payment
    {
        $appointment->loadMissing('service', 'payments');

        $deposit = $appointment->payments->firstWhere(fn (Payment $p) => $p->type === 'deposit' && $p->status === 'paid');
        if (! $deposit) {
            return null;
        }

        // A no-show forfeits outright — the patient never cancelled, so the
        // service's cancellation-timing policy doesn't apply.
        if ($alwaysForfeit) {
            $deposit->update(['status' => 'forfeited']);
            AuditLog::record('deposit_forfeited_no_show', $deposit, null, $deposit->toArray());

            return $deposit;
        }

        $forfeitHours = $appointment->service?->deposit_forfeit_hours;
        // Unambiguous sign: positive means start_at is still in the future.
        $hoursUntilStart = $appointment->start_at
            ? ($appointment->start_at->timestamp - now()->timestamp) / 3600
            : null;
        $withinForfeitWindow = $forfeitHours !== null && $hoursUntilStart !== null && $hoursUntilStart < $forfeitHours;

        if ($withinForfeitWindow) {
            $deposit->update(['status' => 'forfeited']);
            AuditLog::record('deposit_forfeited_late_cancel', $deposit, null, $deposit->toArray());

            return $deposit;
        }

        $refund = $this->refund($deposit);
        AuditLog::record('deposit_auto_refunded', $refund, null, $refund->toArray());

        return $refund;
    }

    // --- Razorpay (raw REST calls — no SDK dependency) -------------------------

    protected function createRazorpayOrder(Payment $payment, int $clinicId): void
    {
        $keyId = Setting::getForClinic($clinicId, 'payments.razorpay_key_id', config('services.payments.razorpay_key_id'));
        $keySecret = Setting::getForClinic($clinicId, 'payments.razorpay_key_secret', config('services.payments.razorpay_key_secret'));

        if (! $keyId || ! $keySecret) {
            Log::channel('stack')->warning('[Payments:razorpay] missing key id/secret for clinic #'.$clinicId.'; payment #'.$payment->id.' left pending for manual collection.');

            return;
        }

        try {
            $response = Http::withBasicAuth($keyId, $keySecret)
                ->post('https://api.razorpay.com/v1/orders', [
                    'amount' => (int) round(((float) $payment->amount) * 100), // paise
                    'currency' => strtoupper($payment->currency),
                    'receipt' => 'payment_'.$payment->id,
                    'notes' => [
                        'payment_id' => (string) $payment->id,
                        'appointment_id' => (string) $payment->appointment_id,
                    ],
                ]);

            if ($response->successful()) {
                $payment->update(['provider_ref' => $response->json('id')]);
            } else {
                Log::channel('stack')->error('[Payments:razorpay] order creation failed ('.$response->status().'): '.$response->body());
            }
        } catch (\Throwable $e) {
            Log::channel('stack')->error('[Payments:razorpay] exception: '.$e->getMessage());
        }
    }

    /**
     * Verify a Razorpay webhook signature without the SDK. Razorpay's scheme:
     * the `X-Razorpay-Signature` header is a hex HMAC-SHA256 of the raw
     * request body, keyed with the webhook secret configured in the Razorpay
     * dashboard. https://razorpay.com/docs/webhooks/validate-test/
     */
    public function verifyRazorpaySignature(string $payload, string $signatureHeader, string $secret): bool
    {
        if ($signatureHeader === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signatureHeader);
    }
}
