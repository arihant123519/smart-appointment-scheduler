<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives Razorpay payment events for deposits created by
 * PaymentService::createDeposit(). Verified via Razorpay's signed-webhook
 * scheme (see PaymentService::verifyRazorpaySignature()) rather than the
 * razorpay-php SDK, matching how this app talks to Gupshup — a plain HTTP
 * call, no external SDK dependency.
 *
 * The webhook secret is clinic-scoped (each clinic's own Razorpay account),
 * so the clinic is resolved from the local Payment row the order_id already
 * points to — found first, verified second — rather than a shared secret in
 * the URL.
 *
 * Configure this URL (…/webhooks/razorpay) in each clinic's own Razorpay
 * dashboard once real keys are in place, and save that clinic's webhook
 * signing secret in its Settings → Integrations page.
 */
class RazorpayWebhookController extends Controller
{
    public function handle(Request $request, PaymentService $payments): JsonResponse
    {
        $orderId = (string) $request->input('payload.payment.entity.order_id', '');
        if ($orderId === '') {
            return response()->json(['ok' => true]);
        }

        $payment = Payment::with('appointment')->where('provider_ref', $orderId)->first();
        if (! $payment || ! $payment->appointment) {
            Log::channel('stack')->warning('[Payments:razorpay] webhook for unknown order '.$orderId);

            return response()->json(['ok' => true]);
        }

        $clinicId = $payment->appointment->clinic_id;
        $secret = (string) Setting::getForClinic($clinicId, 'payments.razorpay_webhook_secret', config('services.payments.razorpay_webhook_secret'));
        $signature = (string) $request->header('X-Razorpay-Signature', '');

        if ($secret === '' || $signature === '' || ! $payments->verifyRazorpaySignature($request->getContent(), $signature, $secret)) {
            abort(403);
        }

        $event = $request->input('event');

        match ($event) {
            'payment.captured' => $payments->confirmDeposit($payment),
            'payment.failed' => $payment->update(['status' => 'failed']),
            default => null,
        };

        return response()->json(['ok' => true]);
    }
}
