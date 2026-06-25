<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Provider;
use App\Models\Service;
use App\Services\ReminderService;
use App\Services\SchedulingService;
use App\Services\WaitlistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __construct(
        private SchedulingService $scheduling,
        private ReminderService $reminders,
    ) {
    }

    public function create(): View
    {
        $services = Service::forCurrentClinic()->where('is_active', true)->orderBy('name')->get();
        $providers = Provider::with('user')->forCurrentClinic()->where('is_active', true)->get();

        return view('booking.create', compact('services', 'providers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'provider_id' => ['required', 'exists:providers,id'],
            'service_id' => ['required', 'exists:services,id'],
            'start_at' => ['required', 'date', 'after:now'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $appointment = $this->scheduling->book([
                'patient_id' => auth()->id(),
                'provider_id' => $data['provider_id'],
                'service_id' => $data['service_id'],
                'start_at' => $data['start_at'],
                'reason' => $data['reason'] ?? null,
                'channel' => 'web',
            ]);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $this->reminders->scheduleFor($appointment);
        AuditLog::record('created', $appointment, null, ['self_booked' => true]);

        auth()->user()->notify(new \App\Notifications\GenericNotification(
            'Appointment booked',
            $appointment->start_at->format('M j, Y g:i A').' with '.$appointment->provider->name,
            route('dashboard'),
            'fi-rr-calendar-check',
        ));

        return redirect()->route('dashboard')->with('success', 'Your appointment has been booked! A confirmation has been sent.');
    }

    public function cancel(Appointment $appointment, WaitlistService $waitlist): RedirectResponse
    {
        abort_unless($appointment->patient_id === auth()->id(), 403);

        $appointment->update([
            'status' => Appointment::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Cancelled by patient',
        ]);

        $waitlist->offerFreedSlot($appointment);

        return back()->with('success', 'Appointment cancelled.');
    }
}
