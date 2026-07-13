<?php

namespace App\Models;

use App\Models\Concerns\ScopedToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalkInQueue extends Model
{
    use ScopedToClinic;

    protected $fillable = [
        'clinic_id', 'patient_id', 'name', 'phone', 'provider_id', 'service_id',
        'appointment_id', 'status', 'token', 'joined_at', 'called_at', 'completed_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'called_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function scopeWaiting($query)
    {
        return $query->where('status', 'waiting')->orderBy('joined_at');
    }

    /** 1-based position among everyone still waiting ahead of (and including) this entry. */
    public function getPositionAttribute(): ?int
    {
        if ($this->status !== 'waiting') {
            return null;
        }

        return static::where('clinic_id', $this->clinic_id)
            ->where('status', 'waiting')
            ->where('joined_at', '<=', $this->joined_at)
            ->count();
    }
}
