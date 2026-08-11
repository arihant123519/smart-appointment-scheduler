<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Appointment;

trait AuthorizesClinicalAccess
{
    /**
     * Read access to a patient's clinical record (consultation/prescription) on
     * an appointment that isn't the viewer's own. System admins and front-desk/
     * clinic-admin staff get clinic-wide oversight; a fellow provider only gets
     * continuity-of-care access if they've personally treated the same patient
     * at least once. `view consultations` alone isn't enough for that case —
     * it's held by every provider in the clinic, and without this check would
     * let any doctor read any other doctor's notes for a patient they've never
     * actually seen, just by knowing an appointment ID.
     */
    private function canViewClinicalRecord(Appointment $appointment): bool
    {
        $user = auth()->user();

        if ($user->hasRole('system_admin')) {
            return true;
        }

        if (! $user->can('view consultations')) {
            return false;
        }

        if ($user->hasAnyRole(['front_desk', 'clinic_admin'])) {
            return $appointment->clinic_id === $user->clinic_id;
        }

        if ($user->provider) {
            return Appointment::where('patient_id', $appointment->patient_id)
                ->where('provider_id', $user->provider->id)
                ->exists();
        }

        return false;
    }
}
