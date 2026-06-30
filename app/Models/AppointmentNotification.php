<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A scheduled lead-time notification (email / WhatsApp) for an appointment.
 * Created from the configured lead times for Booked / Confirmed appointments
 * and dispatched by the `appointments:notify` command.
 */
class AppointmentNotification extends Model
{
    protected $fillable = [
        'appointment_id', 'channel', 'hours_before', 'send_at', 'status', 'sent_at',
    ];

    protected $casts = [
        'send_at' => 'datetime',
        'sent_at' => 'datetime',
        'hours_before' => 'integer',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function scopeDue($query)
    {
        return $query->where('status', 'scheduled')
            ->where('send_at', '<=', now());
    }
}
