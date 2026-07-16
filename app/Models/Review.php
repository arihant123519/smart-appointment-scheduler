<?php

namespace App\Models;

use App\Models\Concerns\ScopedToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use ScopedToClinic;

    protected $fillable = [
        'clinic_id', 'appointment_id', 'patient_id', 'provider_id', 'rating', 'comment',
        'sentiment', 'reviewer_name', 'reviewer_phone', 'source',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function getReviewerDisplayNameAttribute(): string
    {
        return $this->patient->name ?? $this->reviewer_name ?? 'Anonymous';
    }
}
