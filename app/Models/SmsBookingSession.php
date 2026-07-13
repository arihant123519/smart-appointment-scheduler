<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsBookingSession extends Model
{
    protected $fillable = [
        'phone', 'patient_id', 'service_id', 'offered_slots',
        'appointment_id', 'status', 'expires_at',
    ];

    protected $casts = [
        'offered_slots' => 'array',
        'expires_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending')->where('expires_at', '>=', now());
    }
}
