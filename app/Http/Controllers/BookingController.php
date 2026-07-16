<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Provider;
use App\Models\Service;
use App\Services\ExperimentService;
use App\Services\PaymentService;
use App\Services\ReminderService;
use App\Services\RetentionService;
use App\Services\SchedulingService;
use App\Services\WaitlistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
    /** A/B test: does a warmer, more specific intro line improve booking completion? */
    public const INTRO_COPY_EXPERIMENT = 'booking_intro_copy';

    public function __construct(
        private SchedulingService $scheduling,
        private ReminderService $reminders,
    ) {
    }

    public function create(ExperimentService $experiments): View
    {
        $services = Service::forCurrentClinic()->where('is_active', true)->orderBy('name')->get();
        $providers = Provider::with('user')->forCurrentClinic()->where('is_active', true)->get();
        // A QR code scan (or referral link) may have pre-selected a service.
        $preselectedServiceId = session('qr_service_id');

        $introVariant = $experiments->variantFor(
            self::INTRO_COPY_EXPERIMENT,
            ['control', 'reassuring'],
            (string) auth()->id(),
        );

        return view('booking.create', compact('services', 'providers', 'preselectedServiceId', 'introVariant'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider_id' => ['required', 'exists:providers,id'],
            'service_id' => ['required', 'exists:services,id'],
            'start_at' => ['required', 'date', 'after:now'],
            'reason' => ['nullable', 'string', 'max:255'],
            'booked_for_name' => ['nullable', 'string', 'max:255'],
            'booked_for_relationship' => ['nullable', 'string', 'max:255', 'required_with:booked_for_name'],
        ]);

        try {
            $appointment = $this->scheduling->book([
                'patient_id' => auth()->id(),
                'provider_id' => $data['provider_id'],
                'service_id' => $data['service_id'],
                'start_at' => $data['start_at'],
                'reason' => $data['reason'] ?? null,
                'booked_for_name' => $data['booked_for_name'] ?? null,
                'booked_for_relationship' => $data['booked_for_relationship'] ?? null,
                'channel' => session('qr_token') ? 'qr' : 'web',
                'source' => session('qr_token'),
            ]);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $this->reminders->scheduleFor($appointment);
        AuditLog::record('created', $appointment, null, ['self_booked' => true]);
        app(ExperimentService::class)->recordConversion(self::INTRO_COPY_EXPERIMENT, (string) auth()->id());

        // If this booking arrived via a referral link, redeem it now that the
        // booking has actually succeeded (never on click — only on a real visit).
        if ($token = session('referral_token')) {
            app(RetentionService::class)->redeem($token, $appointment);
            session()->forget('referral_token');
        }

        // Attribute this booking to whichever QR code drove it (never on
        // scan alone — only once the booking has actually succeeded).
        if ($qrToken = session('qr_token')) {
            \App\Models\QrBookingCode::where('token', $qrToken)->increment('bookings_count');
            session()->forget(['qr_token', 'qr_service_id']);
        }

        auth()->user()->notify(new \App\Notifications\GenericNotification(
            'Appointment booked',
            $appointment->start_at->format('M j, Y g:i A').' with '.$appointment->provider->name,
            route('dashboard'),
            'fi-rr-calendar-check',
        ));

        // If the service requires a deposit, open it now — the slot is held
        // either way (the row already exists), but it's released automatically
        // if the deposit isn't completed within the hold window.
        $deposit = app(PaymentService::class)->createDeposit($appointment);
        $message = $deposit
            ? 'Your appointment is held! A ₹'.number_format((float) $deposit->amount, 2)." deposit is due within ".PaymentService::ABANDON_AFTER_MINUTES.' minutes to confirm it.'
            : 'Your appointment has been booked! A confirmation has been sent.';

        return redirect()->route('dashboard')->with('success', $message);
    }

    public function cancel(Appointment $appointment, WaitlistService $waitlist): RedirectResponse
    {
        abort_unless($appointment->patient_id === auth()->id(), 403);

        $appointment->update([
            'status' => Appointment::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Cancelled by patient',
        ]);

        app(PaymentService::class)->forfeitOrRefundDeposit($appointment);
        $waitlist->offerFreedSlot($appointment);

        return back()->with('success', 'Appointment cancelled.');
    }
}
