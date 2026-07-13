<?php

namespace App\Models;

use App\Models\Concerns\ScopedToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    use ScopedToClinic;

    protected $fillable = [
        'clinic_id', 'referrer_patient_id', 'referrer_provider_id',
        'referred_name', 'referred_phone', 'referred_email',
        'token', 'status', 'appointment_id', 'redeemed_at',
    ];

    protected $casts = [
        'redeemed_at' => 'datetime',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function referrerPatient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_patient_id');
    }

    public function referrerProvider(): BelongsTo
    {
        return $this->belongsTo(Provider::class, 'referrer_provider_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function scopeUnredeemed($query)
    {
        return $query->where('status', 'pending');
    }

    public function getShareUrlAttribute(): string
    {
        return route('referral.redeem', $this->token);
    }
}
