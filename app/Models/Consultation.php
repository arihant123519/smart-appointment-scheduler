<?php

namespace App\Models;

use App\Models\Concerns\ScopedToClinic;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Consultation extends Model
{
    use HasFactory, ScopedToClinic, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_FINALIZED = 'finalized';

    protected $fillable = [
        'appointment_id', 'provider_id', 'patient_id', 'clinic_id',
        'chief_complaint', 'vitals', 'examination_notes', 'diagnosis',
        'follow_up_date', 'follow_up_instructions', 'status', 'finalized_at',
    ];

    protected $casts = [
        'vitals' => 'array',
        'follow_up_date' => 'date',
        'finalized_at' => 'datetime',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function prescription(): HasOne
    {
        return $this->hasOne(Prescription::class);
    }

    public function getIsFinalizedAttribute(): bool
    {
        return $this->status === self::STATUS_FINALIZED;
    }
}
