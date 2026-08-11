<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(): View
    {
        $scopeToClinic = fn ($q) => $q->whereHas('patient', fn ($qq) => $qq->forCurrentClinic());

        $payments = Payment::with(['patient', 'appointment'])->tap($scopeToClinic)->orderByDesc('created_at')->get();

        $totals = [
            'collected' => Payment::where('status', 'paid')->whereIn('type', ['copay', 'fee', 'deposit', 'no_show_fee'])->tap($scopeToClinic)->sum('amount'),
            'refunded' => Payment::where('type', 'refund')->tap($scopeToClinic)->sum('amount'),
            'pending' => Payment::where('status', 'pending')->tap($scopeToClinic)->sum('amount'),
        ];

        $pendingDeposits = Payment::pendingDeposits()->with(['patient', 'appointment.provider.user'])->tap($scopeToClinic)
            ->orderBy('expires_at')->get();

        $clinicId = auth()->user()->clinic_id;
        $driver = $clinicId ? app(PaymentService::class)->driver($clinicId) : config('services.payments.driver', 'manual');

        return view('payments.index', compact('payments', 'totals', 'driver', 'pendingDeposits'));
    }

    /** Staff confirms a pending deposit was collected (manual driver — cash at the desk). */
    public function confirmDeposit(Payment $payment, PaymentService $payments): RedirectResponse
    {
        abort_unless($payment->type === 'deposit' && $payment->status === 'pending', 404);

        $before = $payment->toArray();
        $payments->confirmDeposit($payment);
        AuditLog::record('deposit_confirmed', $payment, $before, $payment->fresh()->toArray());

        return back()->with('success', 'Deposit marked as collected.');
    }

    /** Collect a copay / fee for an appointment. */
    public function charge(Request $request, Appointment $appointment, PaymentService $payments): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.5'],
            'type' => ['required', 'in:copay,fee,deposit,no_show_fee'],
            'method' => ['required', 'in:cash,card,insurance'],
        ]);

        $payment = $payments->charge($appointment, (float) $data['amount'], $data['type'], $data['method']);
        AuditLog::record('payment_charged', $payment, null, $payment->toArray());

        return back()->with('success', 'Payment of ₹'.number_format($data['amount'], 2).' recorded.');
    }

    public function refund(Payment $payment, PaymentService $payments): RedirectResponse
    {
        $user = auth()->user();
        if (! $user->hasRole('system_admin')) {
            $payment->loadMissing('patient');
            abort_unless($payment->patient && $payment->patient->clinic_id === $user->clinic_id, 404);
        }

        // Only an actually-collected, not-already-refunded transaction can be
        // refunded — blocks refunding a pending/failed payment and blocks a
        // double refund (refund() flips the original to status=refunded).
        abort_unless(in_array($payment->type, ['copay', 'fee', 'deposit', 'no_show_fee'], true) && $payment->status === 'paid', 422);

        $refund = $payments->refund($payment);
        AuditLog::record('payment_refunded', $refund, null, $refund->toArray());

        return back()->with('success', 'Refund recorded.');
    }
}
