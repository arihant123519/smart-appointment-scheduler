<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesClinicalAccess;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Consultation;
use App\Models\Prescription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConsultationController extends Controller
{
    use AuthorizesClinicalAccess;

    public function edit(Appointment $appointment): View
    {
        abort_unless($this->canView($appointment), 403);

        $consultation = $appointment->consultation ?? new Consultation(['status' => Consultation::STATUS_DRAFT]);
        $prescription = $consultation->exists ? $consultation->prescription : null;
        $items = $prescription?->items ?? collect();
        $editable = $this->canEdit($appointment);

        return view('consultations.edit', compact('appointment', 'consultation', 'prescription', 'items', 'editable'));
    }

    public function update(Request $request, Appointment $appointment): RedirectResponse
    {
        abort_unless($this->canEdit($appointment), 403);

        $data = $this->validated($request);
        $consultation = $this->saveConsultation($appointment, $data);
        $this->savePrescription($consultation, $data);

        AuditLog::record('consultation_saved', $consultation);

        return redirect()->route('consultations.edit', $appointment)->with('success', 'Consultation saved as draft.');
    }

    public function finalize(Request $request, Appointment $appointment): RedirectResponse
    {
        abort_unless($this->canEdit($appointment), 403);

        $data = $this->validated($request);
        $consultation = $this->saveConsultation($appointment, $data, finalize: true);
        $this->savePrescription($consultation, $data);

        // Mirrors IntakeFormController::checkIn — a direct status write, since
        // Appointment::TRANSITIONS only allows checked_in -> completed and a
        // doctor may finalize a visit that was never explicitly checked in.
        if (! in_array($appointment->status, [
            Appointment::STATUS_COMPLETED, Appointment::STATUS_CANCELLED, Appointment::STATUS_NO_SHOW,
        ], true)) {
            $appointment->update(['status' => Appointment::STATUS_COMPLETED]);
        }

        AuditLog::record('consultation_finalized', $consultation);

        return redirect()->route('appointments.show', $appointment)
            ->with('success', 'Consultation finalized and appointment marked completed.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'chief_complaint' => ['nullable', 'string', 'max:2000'],
            'vitals' => ['nullable', 'array'],
            'vitals.bp' => ['nullable', 'string', 'max:20'],
            'vitals.pulse' => ['nullable', 'string', 'max:20'],
            'vitals.temp' => ['nullable', 'string', 'max:20'],
            'vitals.weight' => ['nullable', 'string', 'max:20'],
            'vitals.spo2' => ['nullable', 'string', 'max:20'],
            'examination_notes' => ['nullable', 'string', 'max:5000'],
            'diagnosis' => ['nullable', 'string', 'max:2000'],
            'follow_up_date' => ['nullable', 'date'],
            'follow_up_instructions' => ['nullable', 'string', 'max:2000'],
            'prescription_notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['nullable', 'array'],
            'items.*.medicine_name' => ['nullable', 'string', 'max:255'],
            'items.*.dosage' => ['nullable', 'string', 'max:100'],
            'items.*.frequency' => ['nullable', 'string', 'max:100'],
            'items.*.duration' => ['nullable', 'string', 'max:100'],
            'items.*.instructions' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function saveConsultation(Appointment $appointment, array $data, bool $finalize = false): Consultation
    {
        $consultation = Consultation::firstOrNew(['appointment_id' => $appointment->id]);

        $consultation->fill([
            'provider_id' => $appointment->provider_id,
            'patient_id' => $appointment->patient_id,
            'clinic_id' => $appointment->clinic_id,
            'chief_complaint' => $data['chief_complaint'] ?? null,
            'vitals' => array_filter($data['vitals'] ?? []),
            'examination_notes' => $data['examination_notes'] ?? null,
            'diagnosis' => $data['diagnosis'] ?? null,
            'follow_up_date' => $data['follow_up_date'] ?? null,
            'follow_up_instructions' => $data['follow_up_instructions'] ?? null,
        ]);

        if ($finalize && $consultation->status !== Consultation::STATUS_FINALIZED) {
            $consultation->status = Consultation::STATUS_FINALIZED;
            $consultation->finalized_at = now();
        } elseif (! $consultation->exists) {
            $consultation->status = Consultation::STATUS_DRAFT;
        }

        $consultation->save();

        return $consultation;
    }

    /** Prescription is optional — only persisted once there's actually a medicine line or note. */
    private function savePrescription(Consultation $consultation, array $data): void
    {
        $items = collect($data['items'] ?? [])->filter(fn ($row) => filled($row['medicine_name'] ?? null))->values();

        if ($items->isEmpty() && empty($data['prescription_notes'])) {
            return;
        }

        $prescription = Prescription::firstOrNew(['consultation_id' => $consultation->id]);
        $prescription->fill([
            'appointment_id' => $consultation->appointment_id,
            'provider_id' => $consultation->provider_id,
            'patient_id' => $consultation->patient_id,
            'clinic_id' => $consultation->clinic_id,
            'notes' => $data['prescription_notes'] ?? null,
            'issued_at' => $prescription->issued_at ?? now(),
        ]);
        $prescription->save();

        $prescription->items()->delete();
        foreach ($items as $i => $row) {
            $prescription->items()->create([
                'medicine_name' => $row['medicine_name'],
                'dosage' => $row['dosage'] ?? null,
                'frequency' => $row['frequency'] ?? null,
                'duration' => $row['duration'] ?? null,
                'instructions' => $row['instructions'] ?? null,
                'sort_order' => $i,
            ]);
        }
    }

    /** The assigned provider (write access) or a system admin (full override). */
    private function canEdit(Appointment $appointment): bool
    {
        $user = auth()->user();

        if ($user->hasRole('system_admin')) {
            return true;
        }

        return $user->can('manage consultations')
            && $user->provider
            && $appointment->provider_id === $user->provider->id;
    }

    /** Anyone who can edit, plus staff/providers with legitimate read access (see AuthorizesClinicalAccess). */
    private function canView(Appointment $appointment): bool
    {
        return $this->canEdit($appointment) || $this->canViewClinicalRecord($appointment);
    }
}
