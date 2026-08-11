<?php

use Illuminate\Support\Facades\Broadcast;

// Walk-in queue live updates — scoped to the viewer's own clinic, matching
// the same clinic-isolation rule enforced by ScopedToClinic on the model
// (see the cross-clinic IDOR note there). system_admin can watch any clinic.
Broadcast::channel('clinic.{clinicId}.walkins', function ($user, int $clinicId) {
    return $user->hasRole('system_admin') || (int) $user->clinic_id === $clinicId;
});
