<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecallNotice extends Model
{
    public const TYPE_RECALL = 'recall';
    public const TYPE_CARE_GAP = 'care_gap';
    public const TYPE_FOLLOW_THROUGH = 'follow_through';

    protected $fillable = [
        'patient_id', 'service_id', 'appointment_id',
        'type', 'due_at', 'sent_at', 'status',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'sent_at' => 'datetime',
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

    public function scopeDue($query)
    {
        return $query->where('status', 'pending')->where('due_at', '<=', now());
    }
}
