<?php

namespace App\Http\Controllers;

use App\Support\AppointmentNotifications;
use App\Support\WhatsappTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Settings for the Appointment Notifications feature: the lead-time reminders
 * (email / WhatsApp before Booked & Confirmed appointments) and the immediate
 * status-change notifications.
 */
class AppointmentNotificationController extends Controller
{
    public function edit(): View
    {
        $leadTimes = AppointmentNotifications::leadTimes();
        $status = AppointmentNotifications::statusNotify();
        $repeat = AppointmentNotifications::repeat();

        // Surface whether the relevant WhatsApp templates are wired up, so the
        // admin knows WhatsApp sends will actually go out.
        $reminderTemplateSet = (bool) (WhatsappTemplate::forEvent('appointment')['template_id'] ?? null);
        $statusTemplateSet = (bool) (WhatsappTemplate::forEvent('status_update', false)['template_id'] ?? null);

        return view('appointments.notifications', compact(
            'leadTimes', 'status', 'repeat', 'reminderTemplateSet', 'statusTemplateSet'
        ));
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'status' => ['nullable', 'array'],
            'lead_times' => ['nullable', 'array'],
            'lead_times.*.hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'repeat' => ['nullable', 'array'],
            'repeat.hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
        ]);

        AppointmentNotifications::saveStatusNotify($request->input('status', []));
        AppointmentNotifications::saveLeadTimes($request->input('lead_times', []));
        AppointmentNotifications::saveRepeat($request->input('repeat', []));

        return back()->with('success', 'Appointment notification settings saved.');
    }
}
