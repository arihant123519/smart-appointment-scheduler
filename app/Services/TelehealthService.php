<?php

namespace App\Services;

use App\Models\Appointment;
use Illuminate\Support\Str;

/**
 * Generates virtual-visit links.
 *
 * Default driver is **Jitsi Meet** (`https://meet.jit.si/<room>`), which needs
 * no account or API key, so telehealth works immediately. Swap the driver to
 * Zoom / Twilio Video / Daily.co (config('services.telehealth.driver')) and add
 * credentials to issue provider-hosted links instead.
 */
class TelehealthService
{
    public function linkFor(Appointment $appointment): string
    {
        $driver = config('services.telehealth.driver', 'jitsi');

        return match ($driver) {
            // 'zoom', 'twilio', 'daily' would create real meetings here.
            default => 'https://meet.jit.si/'.$this->room($appointment),
        };
    }

    private function room(Appointment $appointment): string
    {
        return 'SAS-'.$appointment->id.'-'.Str::upper(Str::random(8));
    }
}
