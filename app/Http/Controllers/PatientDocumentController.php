<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\PatientDocument;
use App\Services\AI\AiService;
use App\Services\Messaging\MessageService;
use App\Support\WhatsappTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Referral letters, consent forms, and plain-language visit recaps: drafted
 * by AI (or its rule-based fallback), always reviewed/edited by staff, and
 * only ever sent to the patient after an explicit approval action — never
 * automatically. Gated behind `manage patients` like the rest of the
 * patient-record surface (referrals, patient CRUD).
 */
class PatientDocumentController extends Controller
{
    public function store(Request $request, Appointment $appointment, AiService $ai): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(PatientDocument::TYPES))],
        ]);

        if ($data['type'] === PatientDocument::TYPE_VISIT_RECAP && $appointment->status !== Appointment::STATUS_COMPLETED) {
            return back()->with('error', 'A visit recap can only be drafted once the appointment is completed.');
        }

        $context = WhatsappTemplate::contextFromAppointment($appointment) + [
            'reason' => (string) ($appointment->reason ?? ''),
            'notes' => (string) ($appointment->notes ?? ''),
        ];

        $content = match ($data['type']) {
            PatientDocument::TYPE_REFERRAL_LETTER => $ai->draftReferralLetter($context, $appointment->clinic_id),
            PatientDocument::TYPE_CONSENT_FORM => $ai->draftConsentForm($context, $appointment->clinic_id),
            PatientDocument::TYPE_VISIT_RECAP => $ai->draftVisitRecap($context, $appointment->clinic_id),
        };

        $document = PatientDocument::create([
            'appointment_id' => $appointment->id,
            'clinic_id' => $appointment->clinic_id,
            'type' => $data['type'],
            'content' => $content,
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        AuditLog::record('document_drafted', $document);

        return back()->with('success', PatientDocument::TYPES[$data['type']].' drafted — review and approve before sending.');
    }

    public function update(Request $request, PatientDocument $document): RedirectResponse
    {
        abort_unless($document->status === 'draft', 403, 'Only a draft can be edited.');

        $data = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
        ]);

        $document->update(['content' => $data['content']]);

        return back()->with('success', 'Draft updated.');
    }

    /** Approve a draft and send it to the patient immediately, in one action. */
    public function approve(PatientDocument $document, MessageService $messages): RedirectResponse
    {
        abort_unless($document->status === 'draft', 403, 'This document was already '.$document->status.'.');

        $appointment = $document->appointment;

        $document->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        $sent = $messages->send(
            $appointment->patient,
            PatientDocument::TYPES[$document->type],
            $document->content,
            null,
            $appointment,
            'patient_document',
            $document->type,
        );

        $document->update([
            'status' => $sent ? 'sent' : 'approved',
            'sent_at' => $sent ? now() : null,
        ]);

        AuditLog::record('document_'.($sent ? 'sent' : 'approve_failed'), $document);

        return back()->with($sent ? 'success' : 'error', $sent
            ? PatientDocument::TYPES[$document->type].' sent to the patient.'
            : 'Approved, but sending failed — check the patient has a contact address on file.');
    }
}
