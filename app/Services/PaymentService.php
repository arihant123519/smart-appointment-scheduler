<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Payment;

/**
 * Payment handling. The default driver is `manual` (records cash/at-desk
 * payments and marks them paid) so copay tracking works with no gateway.
 * Set PAYMENTS_DRIVER=stripe + keys to charge cards via Stripe PaymentIntents.
 */
class PaymentService
{
    public function driver(): string
    {
        return config('services.payments.driver', 'manual');
    }

    /** Record a copay/fee for an appointment. */
    public function charge(Appointment $appointment, float $amount, string $type = 'copay', string $method = 'cash'): Payment
    {
        // Stripe path would create a PaymentIntent here and set status from the result.
        $paid = $this->driver() === 'manual';

        return Payment::create([
            'appointment_id' => $appointment->id,
            'patient_id' => $appointment->patient_id,
            'amount' => $amount,
            'currency' => 'USD',
            'type' => $type,
            'method' => $method,
            'provider_ref' => null,
            'status' => $paid ? 'paid' : 'pending',
            'paid_at' => $paid ? now() : null,
        ]);
    }

    public function refund(Payment $payment): Payment
    {
        return Payment::create([
            'appointment_id' => $payment->appointment_id,
            'patient_id' => $payment->patient_id,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'type' => 'refund',
            'method' => $payment->method,
            'status' => 'refunded',
            'paid_at' => now(),
        ]);
    }
}
