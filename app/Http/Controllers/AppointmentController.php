<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Provider;
use App\Models\Resource;
use App\Models\Service;
use App\Models\User;
use App\Services\AppointmentNotificationService;
use App\Services\PaymentService;
use App\Services\ReminderService;
use App\Services\RescheduleSuggestionService;
use App\Services\SchedulingService;
use App\Services\WaitlistService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function __construct(private SchedulingService $scheduling)
    {
    }

    public function index(Request $request): View
    {
        $query = Appointment::with(['patient', 'provider.user', 'service'])
            ->forCurrentClinic()
            ->orderByDesc('start_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('provider_id')) {
            $query->where('provider_id', $request->integer('provider_id'));
        }
        if ($request->filled('date')) {
            $query->whereDate('start_at', $request->date('date'));
        }
        if ($request->filled('q')) {
            $term = $request->string('q');
            $query->whereHas('patient', fn ($p) => $p->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
        }

        // Providers see only their own.
        $user = auth()->user();
        if ($user->hasRole('provider') && ! $user->hasAnyRole(['clinic_admin', 'system_admin']) && $user->provider) {
            $query->where('provider_id', $user->provider->id);
        }

        $appointments = $query->get();
        $providers = Provider::with('user')->get();
        $statuses = Appointment::STATUSES;

        return view('appointments.index', compact('appointments', 'providers', 'statuses'));
    }

    public function create(): View
    {
        return view('appointments.create', $this->formData());
    }

    public function store(Request $request, ReminderService $reminders): RedirectResponse
    {
        $data = $this->validateAppointment($request);
        $recurring = $request->validate([
            'is_recurring' => ['nullable', 'boolean'],
            'frequency' => ['nullable', 'in:daily,weekly,biweekly,monthly'],
            'occurrences' => ['nullable', 'integer', 'min:2', 'max:52'],
        ]);

        $payload = [
            'patient_id' => $data['patient_id'],
            'provider_id' => $data['provider_id'],
            'service_id' => $data['service_id'],
            'resource_id' => $data['resource_id'] ?? null,
            'start_at' => $data['start_at'],
            'reason' => $data['reason'] ?? null,
            'notes' => $data['notes'] ?? null,
            'channel' => 'web',
        ];

        try {
            if ($request->boolean('is_recurring')) {
                $result = $this->scheduling->bookRecurring(
                    $payload,
                    $recurring['frequency'] ?? 'weekly',
                    (int) ($recurring['occurrences'] ?? 4),
                );
                $appointments = $result['booked'];

                if (empty($appointments)) {
                    return back()->withInput()->with('error', 'No occurrences could be booked (all slots taken).');
                }

                foreach ($appointments as $appt) {
                    $reminders->scheduleFor($appt);
                    app(AppointmentNotificationService::class)->syncLeadTimes($appt);
                    AuditLog::record('created', $appt, null, $appt->toArray());
                }

                $msg = count($appointments).' appointment(s) booked.';
                if (! empty($result['skipped'])) {
                    $msg .= ' Skipped (conflicts): '.implode(', ', $result['skipped']).'.';
                }

                return redirect()->route('appointments.show', $appointments[0])->with('success', $msg);
            }

            $appointment = $this->scheduling->book($payload);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $reminders->scheduleFor($appointment);
        app(AppointmentNotificationService::class)->syncLeadTimes($appointment);
        AuditLog::record('created', $appointment, null, $appointment->toArray());

        return redirect()->route('appointments.show', $appointment)
            ->with('success', 'Appointment booked. Confirmation request + reminder cadence scheduled.');
    }

    public function show(Appointment $appointment): View
    {
        $appointment->load(['patient', 'provider.user', 'service', 'resource', 'clinic', 'reminders', 'payment', 'intakeForm', 'documents']);

        return view('appointments.show', compact('appointment'));
    }

    /** Personalized reschedule suggestions (AJAX, PRD "smarter rescheduling suggestions"). */
    public function rescheduleSuggestions(Appointment $appointment, RescheduleSuggestionService $suggestions): JsonResponse
    {
        return response()->json(['slots' => $suggestions->suggestFor($appointment)]);
    }

    public function edit(Appointment $appointment): View
    {
        return view('appointments.edit', array_merge(
            $this->formData(),
            ['appointment' => $appointment]
        ));
    }

    public function update(Request $request, Appointment $appointment): RedirectResponse
    {
        $data = $this->validateAppointment($request, $appointment);
        $before = $appointment->toArray();

        $rescheduled = false;

        try {
            // If time changed, run conflict-safe reschedule.
            $newStart = Carbon::parse($data['start_at']);
            if (! $appointment->start_at->equalTo($newStart) || $appointment->provider_id != $data['provider_id']) {
                $appointment->provider_id = $data['provider_id'];
                $service = Service::find($data['service_id']);
                $end = $newStart->copy()->addMinutes($service?->duration ?? 30);
                $this->scheduling->reschedule($appointment, $newStart, $end);
                $rescheduled = true;
            }

            $appointment->update([
                'patient_id' => $data['patient_id'],
                'service_id' => $data['service_id'],
                'resource_id' => $data['resource_id'] ?? null,
                'reason' => $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        // Start time / provider may have changed — re-derive lead-time reminders,
        // and tell the patient if the appointment was actually moved.
        $notifications = app(AppointmentNotificationService::class);
        $notifications->syncLeadTimes($appointment);
        if ($rescheduled) {
            $notifications->notifyRescheduled($appointment);
        }

        AuditLog::record('updated', $appointment, $before, $appointment->fresh()->toArray());

        return redirect()->route('appointments.show', $appointment)
            ->with('success', $rescheduled ? 'Appointment rescheduled — patient notified.' : 'Appointment updated.');
    }

    public function destroy(Appointment $appointment): RedirectResponse
    {
        AuditLog::record('deleted', $appointment, $appointment->toArray());
        $appointment->delete();

        return redirect()->route('appointments.index')->with('success', 'Appointment deleted.');
    }

    public function updateStatus(Request $request, Appointment $appointment): RedirectResponse
    {
        $allowedTargets = Appointment::TRANSITIONS[$appointment->status] ?? [];

        if (empty($allowedTargets)) {
            return back()->with('error', 'This appointment status is locked and can no longer be changed.');
        }

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(Appointment::STATUSES))],
            'cancellation_reason' => ['nullable', 'string', 'max:255'],
        ]);

        if (! in_array($data['status'], $allowedTargets, true)) {
            return back()->with('error', 'Cannot change status from "'.Appointment::STATUSES[$appointment->status].'" to "'.Appointment::STATUSES[$data['status']].'".');
        }

        $before = $appointment->toArray();
        $appointment->status = $data['status'];

        match ($data['status']) {
            Appointment::STATUS_CONFIRMED => $appointment->confirmed_at = now(),
            Appointment::STATUS_CHECKED_IN => $appointment->checked_in_at = now(),
            Appointment::STATUS_CANCELLED => tap($appointment, function ($a) use ($data) {
                $a->cancelled_at = now();
                $a->cancellation_reason = $data['cancellation_reason'] ?? null;
            }),
            Appointment::STATUS_NO_SHOW => $appointment->cancellation_reason = $data['cancellation_reason'] ?? null,
            default => null,
        };

        $appointment->save();
        AuditLog::record('status_changed', $appointment, $before, $appointment->toArray());

        // Notify the patient of the new status (email + WhatsApp), and re-derive
        // lead-time reminders (scheduled for Booked / Confirmed, cleared otherwise).
        $notifications = app(AppointmentNotificationService::class);
        $notifications->notifyStatusChange($appointment);
        $notifications->syncLeadTimes($appointment);

        // Freed slot? Auto-offer it to the smart waitlist, and apply the
        // service's deposit policy (a no-show always forfeits; a cancellation
        // forfeits only within the configured window before the visit).
        $offered = null;
        if (in_array($data['status'], [Appointment::STATUS_CANCELLED, Appointment::STATUS_NO_SHOW], true)) {
            app(PaymentService::class)->forfeitOrRefundDeposit($appointment, $data['status'] === Appointment::STATUS_NO_SHOW);
            $offered = app(WaitlistService::class)->offerFreedSlot($appointment);
        }

        $msg = 'Status updated to '.$appointment->status_label.'.';
        if ($offered) {
            $msg .= ' Freed slot offered to waitlisted patient '.$offered->patient->name.'.';
        }

        return back()->with('success', $msg);
    }

    /** Edit the cancellation/no-show note on a locked appointment, without touching its status. */
    public function updateReason(Request $request, Appointment $appointment): RedirectResponse
    {
        if (! in_array($appointment->status, [Appointment::STATUS_CANCELLED, Appointment::STATUS_NO_SHOW], true)) {
            return back()->with('error', 'A reason note can only be edited on a cancelled or no-show appointment.');
        }

        $data = $request->validate([
            'cancellation_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $before = $appointment->toArray();
        $appointment->cancellation_reason = $data['cancellation_reason'] ?? null;
        $appointment->save();
        AuditLog::record('reason_updated', $appointment, $before, $appointment->toArray());

        return back()->with('success', 'Reason note updated.');
    }

    /** AJAX: available slots for provider + service + date. */
    public function slots(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provider_id' => ['required', 'exists:providers,id'],
            'service_id' => ['required', 'exists:services,id'],
            'date' => ['required', 'date'],
        ]);

        $provider = Provider::findOrFail($data['provider_id']);
        $service = Service::findOrFail($data['service_id']);
        $slots = $this->scheduling->availableSlots($provider, $service, Carbon::parse($data['date']));

        return response()->json(['slots' => $slots]);
    }

    private function formData(): array
    {
        return [
            'patients' => User::role('patient')->orderBy('name')->get(),
            'providers' => Provider::with('user')->forCurrentClinic()->where('is_active', true)->get(),
            'services' => Service::forCurrentClinic()->where('is_active', true)->orderBy('name')->get(),
            'resources' => Resource::where('is_active', true)->orderBy('name')->get(),
        ];
    }

    private function validateAppointment(Request $request, ?Appointment $appointment = null): array
    {
        return $request->validate([
            'patient_id' => ['required', 'exists:users,id'],
            'provider_id' => ['required', 'exists:providers,id'],
            'service_id' => ['required', 'exists:services,id'],
            'resource_id' => ['nullable', 'exists:resources,id'],
            'start_at' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
