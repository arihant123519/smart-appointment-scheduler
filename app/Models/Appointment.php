<?php

namespace App\Models;

use App\Models\Concerns\ScopedToClinic;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use HasFactory, ScopedToClinic, SoftDeletes;

    public const STATUS_BOOKED = 'booked';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CHECKED_IN = 'checked_in';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW = 'no_show';

    public const STATUSES = [
        self::STATUS_BOOKED => 'Booked',
        self::STATUS_CONFIRMED => 'Confirmed',
        self::STATUS_CHECKED_IN => 'Checked In',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_CANCELLED => 'Cancelled',
        self::STATUS_NO_SHOW => 'No Show',
    ];

    protected $fillable = [
        'patient_id', 'provider_id', 'clinic_id', 'service_id', 'resource_id',
        'start_at', 'end_at', 'status', 'channel', 'no_show_score',
        'is_telehealth', 'telehealth_link', 'reason', 'notes',
        'confirmed_at', 'checked_in_at', 'cancelled_at', 'cancellation_reason',
        'missed_notified_at', 'created_by',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'missed_notified_at' => 'datetime',
        'is_telehealth' => 'boolean',
        'no_show_score' => 'integer',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    protected static function booted(): void
    {
        // When a signed-in user changes an appointment's status, alert the
        // clinic's front desk in real time. Bulk/system updates (the settle
        // command, seeders) use query-builder updates that never load a model,
        // so they don't reach this event.
        static::updated(function (self $appointment): void {
            if ($appointment->wasChanged('status') && auth()->check()) {
                app(\App\Services\ClinicDeskNotifier::class)
                    ->appointmentStatusChanged($appointment, auth()->user());
            }
        });
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class);
    }

    public function intakeForm(): HasOne
    {
        return $this->hasOne(IntakeForm::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(AppointmentNotification::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('start_at', '>=', now())
            ->whereNotIn('status', [self::STATUS_CANCELLED, self::STATUS_NO_SHOW]);
    }

    public function scopeForDay($query, $date)
    {
        return $query->whereDate('start_at', $date);
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [self::STATUS_CANCELLED]);
    }

    /**
     * Missed appointments: the time slot is fully over but the patient never
     * completed it (still booked/confirmed, or explicitly marked no-show).
     * Excludes completed, cancelled, and checked-in.
     */
    public function scopeMissed($query)
    {
        return $query->where('end_at', '<', now())
            ->whereIn('status', [self::STATUS_BOOKED, self::STATUS_CONFIRMED, self::STATUS_NO_SHOW]);
    }

    /**
     * Auto-settle the status of past appointments that were never finalised:
     *   • booked / confirmed (patient never arrived)  → no_show
     *   • checked_in (arrived but not closed out)      → completed
     * Completed, cancelled and already-no_show rows are left untouched.
     *
     * Pass a base query to limit the scope (e.g. one patient or one clinic);
     * otherwise every clinic's overdue appointments are settled.
     *
     * @return array{missed:int, completed:int}
     */
    public static function settleOverdue(?\Illuminate\Database\Eloquent\Builder $base = null): array
    {
        $base ??= static::query();
        $now = now();

        $missed = (clone $base)->where('end_at', '<', $now)
            ->whereIn('status', [self::STATUS_BOOKED, self::STATUS_CONFIRMED])
            ->update(['status' => self::STATUS_NO_SHOW]);

        $completed = (clone $base)->where('end_at', '<', $now)
            ->where('status', self::STATUS_CHECKED_IN)
            ->update(['status' => self::STATUS_COMPLETED]);

        return ['missed' => $missed, 'completed' => $completed];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst($this->status);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_BOOKED => 'warning',
            self::STATUS_CONFIRMED => 'info',
            self::STATUS_CHECKED_IN => 'primary',
            self::STATUS_COMPLETED => 'success',
            self::STATUS_CANCELLED => 'secondary',
            self::STATUS_NO_SHOW => 'danger',
            default => 'secondary',
        };
    }

    public function getRiskLevelAttribute(): string
    {
        return match (true) {
            $this->no_show_score >= 70 => 'high',
            $this->no_show_score >= 40 => 'medium',
            default => 'low',
        };
    }
}
