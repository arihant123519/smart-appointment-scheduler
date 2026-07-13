<?php

namespace App\Models;

use App\Models\Concerns\ScopedToClinic;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, ScopedToClinic, SoftDeletes;

    protected $fillable = [
        'clinic_id', 'name', 'specialty', 'duration', 'buffer',
        'price', 'color', 'telehealth', 'is_active',
        'recall_window_days', 'recall_cadence_days',
        'deposit_required', 'deposit_amount', 'deposit_forfeit_hours',
        'overbooking_enabled', 'overbooking_margin',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'telehealth' => 'boolean',
        'is_active' => 'boolean',
        'deposit_required' => 'boolean',
        'deposit_amount' => 'decimal:2',
        'overbooking_enabled' => 'boolean',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function providers(): BelongsToMany
    {
        return $this->belongsToMany(Provider::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
