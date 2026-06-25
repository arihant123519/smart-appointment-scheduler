<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\IntakeForm;
use App\Services\AI\AiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IntakeFormController extends Controller
{
    /** Default pre-visit questionnaire. */
    public const SCHEMA = [
        'reason_for_visit' => 'Reason for visit',
        'symptoms' => 'Current symptoms',
        'medications' => 'Current medications',
        'allergies' => 'Known allergies',
        'medical_history' => 'Relevant medical history',
    ];

    public function edit(Appointment $appointment): View
    {
        // Patients may only fill their own intake.
        abort_unless(
            $appointment->patient_id === auth()->id() || auth()->user()->can('manage appointments'),
            403
        );

        $intake = $appointment->intakeForm ?? new IntakeForm(['schema' => self::SCHEMA]);
        $schema = $intake->schema ?: self::SCHEMA;

        return view('intake.edit', compact('appointment', 'intake', 'schema'));
    }

    public function update(Request $request, Appointment $appointment, AiService $ai): RedirectResponse
    {
        abort_unless(
            $appointment->patient_id === auth()->id() || auth()->user()->can('manage appointments'),
            403
        );

        $responses = $request->validate([
            'responses' => ['array'],
            'responses.*' => ['nullable', 'string', 'max:2000'],
            'signature_name' => ['required', 'string', 'max:255'],
        ]);

        // AI (or rule-based) pre-visit summary for the provider.
        $summary = $ai->summarizeIntake($responses['responses'] ?? []);

        $intake = IntakeForm::updateOrCreate(
            ['appointment_id' => $appointment->id],
            [
                'schema' => self::SCHEMA,
                'responses' => $responses['responses'] ?? [],
                'ai_summary' => $summary,
                'status' => 'completed',
                'signed_at' => now(),
                'signature_name' => $responses['signature_name'],
            ]
        );

        AuditLog::record('intake_completed', $appointment);

        return redirect()->route('appointments.show', $appointment)
            ->with('success', 'Intake form submitted and signed.');
    }

    /** Digital self check-in on arrival. */
    public function checkIn(Appointment $appointment): RedirectResponse
    {
        abort_unless(
            $appointment->patient_id === auth()->id() || auth()->user()->can('manage appointments'),
            403
        );

        $appointment->update([
            'status' => Appointment::STATUS_CHECKED_IN,
            'checked_in_at' => now(),
        ]);
        AuditLog::record('checked_in', $appointment);

        return back()->with('success', 'Checked in. Please take a seat.');
    }
}
