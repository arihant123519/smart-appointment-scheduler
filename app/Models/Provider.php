<?php

namespace App\Models;

use App\Models\Concerns\ScopedToClinic;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Provider extends Model
{
    use HasFactory, ScopedToClinic, SoftDeletes;

    protected $fillable = [
        'user_id', 'clinic_id', 'specialty', 'credentials', 'registration_no',
        'signature_path', 'bio', 'default_slot_minutes', 'accepts_telehealth', 'is_active',
    ];

    protected $casts = [
        'accepts_telehealth' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(Availability::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(AvailabilityException::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function getNameAttribute(): string
    {
        return $this->user?->name ?? 'Unknown';
    }

    public function getSignatureUrlAttribute(): ?string
    {
        return $this->signature_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->signature_path) : null;
    }
}
