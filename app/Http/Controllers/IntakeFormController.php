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
        'main_question' => "What's the one thing you most want to ask or mention today?",
        'reason_for_visit' => 'Reason for visit',
        'symptoms' => 'Current symptoms',
        'medications' => 'Current medications',
        'allergies' => 'Known allergies',
        'medical_history' => 'Relevant medical history',
    ];

    public function edit(Appointment $appointment): View
    {
        // Patients may fill their own intake; the assigned provider or staff with
        // `manage appointments` may view/complete it on the patient's behalf.
        abort_unless($this->canAccessIntake($appointment), 403);

        $intake = $appointment->intakeForm ?? new IntakeForm(['schema' => self::SCHEMA]);
        $schema = $intake->schema ?: self::SCHEMA;

        return view('intake.edit', compact('appointment', 'intake', 'schema'));
    }

    public function update(Request $request, Appointment $appointment, AiService $ai): RedirectResponse
    {
        abort_unless($this->canAccessIntake($appointment), 403);

        $responses = $request->validate([
            'responses' => ['array'],
            'responses.*' => ['nullable', 'string', 'max:2000'],
            'signature_name' => ['required', 'string', 'max:255'],
        ]);

        // AI (or rule-based) pre-visit summary for the provider.
        $summary = $ai->summarizeIntake($responses['responses'] ?? [], $appointment->clinic_id);

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

        // Patients hold no staff permissions, so `appointments.show` (gated
        // behind `view appointments`) 403s for their own self-service
        // submission — send them to the dashboard instead; staff still land
        // on the appointment detail page as before.
        $redirect = auth()->user()->can('view appointments')
            ? route('appointments.show', $appointment)
            : route('dashboard');

        return redirect()->to($redirect)->with('success', 'Intake form submitted and signed.');
    }

    /** Digital self check-in on arrival. */
    public function checkIn(Appointment $appointment): RedirectResponse
    {
        abort_unless($this->canAccessIntake($appointment), 403);

        $appointment->update([
            'status' => Appointment::STATUS_CHECKED_IN,
            'checked_in_at' => now(),
        ]);
        AuditLog::record('checked_in', $appointment);

        return back()->with('success', 'Checked in. Please take a seat.');
    }

    /** Patient themselves, the assigned provider, or staff with `manage appointments`. */
    private function canAccessIntake(Appointment $appointment): bool
    {
        $user = auth()->user();

        return $appointment->patient_id === $user->id
            || $user->can('manage appointments')
            || ($user->provider && $appointment->provider_id === $user->provider->id);
    }
}
