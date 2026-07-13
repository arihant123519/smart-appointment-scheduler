<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Reminder;
use Illuminate\View\View;

/**
 * One-tap reminder actions reached from links inside reminder messages.
 * Authenticated by an unguessable per-reminder token — no login required,
 * which is what makes "tap Confirm in your SMS" work.
 */
class ReminderActionController extends Controller
{
    public function confirm(string $token): View
    {
        $appointment = $this->resolve($token);
        $appointment->update([
            'status' => Appointment::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]);
        AuditLog::record('confirmed_via_reminder', $appointment);

        $notifications = app(\App\Services\AppointmentNotificationService::class);
        $notifications->notifyStatusChange($appointment);
        $notifications->syncLeadTimes($appointment);

        return $this->page('Confirmed', 'Thanks! Your appointment is confirmed.', $appointment, 'success');
    }

    public function cancel(string $token): View
    {
        $appointment = $this->resolve($token);
        $appointment->update([
            'status' => Appointment::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Cancelled via reminder link',
        ]);
        AuditLog::record('cancelled_via_reminder', $appointment);

        $notifications = app(\App\Services\AppointmentNotificationService::class);
        $notifications->notifyStatusChange($appointment);
        $notifications->cancelLeadTimes($appointment);

        app(\App\Services\PaymentService::class)->forfeitOrRefundDeposit($appointment);

        // Auto-offer the freed slot to the smart waitlist.
        app(\App\Services\WaitlistService::class)->offerFreedSlot($appointment);

        return $this->page('Cancelled', 'Your appointment has been cancelled. The freed slot is being offered to our waitlist.', $appointment, 'warning');
    }

    public function reschedule(string $token): View
    {
        $appointment = $this->resolve($token);

        return $this->page(
            'Reschedule',
            'To reschedule, please log in and pick a new time.',
            $appointment,
            'info',
            route('login'),
        );
    }

    private function resolve(string $token): Appointment
    {
        $reminder = Reminder::where('token', $token)->firstOrFail();
        $reminder->update(['response' => request()->route()->getActionMethod()]);

        return $reminder->appointment()->firstOrFail();
    }

    private function page(string $title, string $message, Appointment $a, string $variant, ?string $link = null): View
    {
        return view('reminders.action', compact('title', 'message', 'a', 'variant', 'link'));
    }
}
