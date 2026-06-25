<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /** Export appointments as CSV (PRD: custom reporting & export). */
    public function appointmentsCsv(): StreamedResponse
    {
        $rows = Appointment::with(['patient', 'provider.user', 'service'])
            ->forCurrentClinic()->orderByDesc('start_at')->get();

        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="appointments.csv"'];

        return response()->stream(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Date', 'Time', 'Patient', 'Provider', 'Service', 'Status', 'Channel', 'No-show risk %']);
            foreach ($rows as $a) {
                fputcsv($out, [
                    $a->id,
                    $a->start_at->format('Y-m-d'),
                    $a->start_at->format('H:i'),
                    $a->patient->name ?? '',
                    $a->provider->name ?? '',
                    $a->service->name ?? '',
                    $a->status_label,
                    $a->channel,
                    $a->no_show_score,
                ]);
            }
            fclose($out);
        }, 200, $headers);
    }

    /** Export the signed-in user's appointments as an iCal feed (calendar sync). */
    public function ics(): StreamedResponse
    {
        $user = auth()->user();
        $query = Appointment::with(['provider.user', 'service'])->active();

        if ($user->hasRole('patient')) {
            $query->where('patient_id', $user->id);
        } elseif ($user->provider) {
            $query->where('provider_id', $user->provider->id);
        } else {
            $query->forCurrentClinic();
        }

        $appointments = $query->get();

        $headers = ['Content-Type' => 'text/calendar; charset=utf-8', 'Content-Disposition' => 'attachment; filename="appointments.ics"'];

        return response()->stream(function () use ($appointments) {
            $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Smart Appointment Scheduler//EN'];
            foreach ($appointments as $a) {
                $lines[] = 'BEGIN:VEVENT';
                $lines[] = 'UID:appointment-'.$a->id.'@scheduler';
                $lines[] = 'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z');
                $lines[] = 'DTSTART:'.$a->start_at->utc()->format('Ymd\THis\Z');
                $lines[] = 'DTEND:'.$a->end_at->utc()->format('Ymd\THis\Z');
                $lines[] = 'SUMMARY:'.($a->service->name ?? 'Appointment').' with '.($a->provider->name ?? '');
                $lines[] = 'STATUS:'.strtoupper($a->status);
                $lines[] = 'END:VEVENT';
            }
            $lines[] = 'END:VCALENDAR';
            echo implode("\r\n", $lines);
        }, 200, $headers);
    }
}
