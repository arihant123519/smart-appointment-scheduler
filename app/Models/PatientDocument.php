<?php

namespace App\Models;

use App\Models\Concerns\ScopedToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientDocument extends Model
{
    use ScopedToClinic;

    public const TYPE_REFERRAL_LETTER = 'referral_letter';
    public const TYPE_CONSENT_FORM = 'consent_form';
    public const TYPE_VISIT_RECAP = 'visit_recap';

    public const TYPES = [
        self::TYPE_REFERRAL_LETTER => 'Referral letter',
        self::TYPE_CONSENT_FORM => 'Consent form',
        self::TYPE_VISIT_RECAP => 'Visit recap',
    ];

    protected $fillable = [
        'appointment_id', 'clinic_id', 'type', 'content', 'status',
        'created_by', 'approved_by', 'approved_at', 'sent_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }
}
