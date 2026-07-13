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
        'start_at', 'end_at', 'overbook_slot', 'status', 'channel', 'source', 'no_show_score',
        'is_telehealth', 'telehealth_link', 'reason', 'notes',
        'confirmed_at', 'checked_in_at', 'cancelled_at', 'cancellation_reason',
        'missed_notified_at', 'review_requested_at', 'created_by',
        'booked_for_name', 'booked_for_relationship',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'checked_in_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'missed_notified_at' => 'datetime',
        'review_requested_at' => 'datetime',
        'is_telehealth' => 'boolean',
        'overbook_slot' => 'integer',
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

            // A visit just finished — schedule follow-up recall / care-gap
            // outreach. Bulk-updated completions (the settle command) don't
            // reach this event; that path calls RetentionService directly.
            if ($appointment->wasChanged('status') && $appointment->status === self::STATUS_COMPLETED) {
                app(\App\Services\RetentionService::class)->scheduleForCompletedIds([$appointment->id]);
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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recallNotices(): HasMany
    {
        return $this->hasMany(RecallNotice::class);
    }

    public function referral(): HasOne
    {
        return $this->hasOne(Referral::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PatientDocument::class);
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
     * Completed visits ready for the post-visit review request: genuinely
     * completed (never cancelled/no-show), not yet asked, and finished at
     * least 2 hours ago — never while the patient is still at the clinic.
     */
    public function scopeDueForReviewRequest($query)
    {
        return $query->where('status', self::STATUS_COMPLETED)
            ->whereNull('review_requested_at')
            ->where('updated_at', '<=', now()->subHours(2));
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
     * @return array{missed:int, completed:int, missed_ids: \Illuminate\Support\Collection, completed_ids: \Illuminate\Support\Collection}
     */
    public static function settleOverdue(?\Illuminate\Database\Eloquent\Builder $base = null): array
    {
        $base ??= static::query();
        $now = now();

        // Bulk update() bypasses the booted() model event, so callers that need
        // to react to newly-settled visits (retention outreach, deposit
        // forfeiture) use these ids rather than relying on the event firing.
        $missedQuery = (clone $base)->where('end_at', '<', $now)
            ->whereIn('status', [self::STATUS_BOOKED, self::STATUS_CONFIRMED]);
        $missedIds = (clone $missedQuery)->pluck('id');
        $missed = $missedQuery->update(['status' => self::STATUS_NO_SHOW]);

        $completedQuery = (clone $base)->where('end_at', '<', $now)->where('status', self::STATUS_CHECKED_IN);
        $completedIds = (clone $completedQuery)->pluck('id');
        $completed = $completedQuery->update(['status' => self::STATUS_COMPLETED]);

        return ['missed' => $missed, 'completed' => $completed, 'missed_ids' => $missedIds, 'completed_ids' => $completedIds];
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

    public function getIsOverbookedAttribute(): bool
    {
        return $this->overbook_slot > 0;
    }
}
