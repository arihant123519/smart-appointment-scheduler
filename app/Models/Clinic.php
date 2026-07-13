<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Clinic extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'email', 'phone', 'address', 'city', 'state',
        'country', 'timezone', 'settings', 'is_active',
        'compliance_agreements_signed_at', 'abdm_health_id',
        'logo_path', 'primary_color',
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean',
        'compliance_agreements_signed_at' => 'datetime',
    ];

    public function providers(): HasMany
    {
        return $this->hasMany(Provider::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->logo_path) : null;
    }
}
