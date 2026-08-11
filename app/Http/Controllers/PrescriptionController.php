<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesClinicalAccess;
use App\Models\Appointment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PrescriptionController extends Controller
{
    use AuthorizesClinicalAccess;

    public function pdf(Appointment $appointment): Response
    {
        abort_unless($this->canView($appointment), 403);

        $prescription = $appointment->prescription()->with(['items', 'provider.user', 'patient', 'clinic'])->first();
        abort_unless($prescription, 404, 'No prescription has been recorded for this appointment yet.');

        $pdf = Pdf::loadView('prescriptions.pdf', [
            'appointment' => $appointment,
            'consultation' => $appointment->consultation,
            'prescription' => $prescription,
            'clinic' => $appointment->clinic,
            'provider' => $appointment->provider,
            'logoDataUri' => $this->imageDataUri($appointment->clinic->logo_path),
            'signatureDataUri' => $this->imageDataUri($appointment->provider->signature_path),
        ]);

        return $pdf->stream('prescription-'.$appointment->id.'.pdf');
    }

    /** dompdf renders most reliably from embedded data URIs rather than resolving storage paths itself. */
    private function imageDataUri(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';
        $contents = Storage::disk('public')->get($path);

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    /** Treating provider, the patient themselves, or legitimate staff/provider read access (see AuthorizesClinicalAccess). */
    private function canView(Appointment $appointment): bool
    {
        $user = auth()->user();

        return $user->id === $appointment->patient_id
            || ($user->provider && $appointment->provider_id === $user->provider->id)
            || $this->canViewClinicalRecord($appointment);
    }
}
