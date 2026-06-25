<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(): View
    {
        $payments = Payment::with(['patient', 'appointment'])->orderByDesc('created_at')->get();

        $totals = [
            'collected' => Payment::where('status', 'paid')->whereIn('type', ['copay', 'fee', 'deposit', 'no_show_fee'])->sum('amount'),
            'refunded' => Payment::where('type', 'refund')->sum('amount'),
            'pending' => Payment::where('status', 'pending')->sum('amount'),
        ];

        $driver = config('services.payments.driver', 'manual');

        return view('payments.index', compact('payments', 'totals', 'driver'));
    }

    /** Collect a copay / fee for an appointment. */
    public function charge(Request $request, Appointment $appointment, PaymentService $payments): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.5'],
            'type' => ['required', 'in:copay,fee,deposit,no_show_fee'],
            'method' => ['required', 'in:cash,card,insurance'],
        ]);

        $payments->charge($appointment, (float) $data['amount'], $data['type'], $data['method']);

        return back()->with('success', 'Payment of $'.number_format($data['amount'], 2).' recorded.');
    }

    public function refund(Payment $payment, PaymentService $payments): RedirectResponse
    {
        $payments->refund($payment);

        return back()->with('success', 'Refund recorded.');
    }
}
