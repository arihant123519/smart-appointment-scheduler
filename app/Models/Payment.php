<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'appointment_id', 'patient_id', 'amount', 'currency', 'type',
        'method', 'provider_ref', 'status', 'paid_at', 'expires_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function scopeAbandonedDeposits($query)
    {
        return $query->where('type', 'deposit')->where('status', 'pending')
            ->whereNotNull('expires_at')->where('expires_at', '<', now());
    }

    public function scopePendingDeposits($query)
    {
        return $query->where('type', 'deposit')->where('status', 'pending');
    }
}
