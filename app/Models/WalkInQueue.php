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

    /**
     * Currently-waiting/serving entries plus today's completed ones for a
     * specific clinic — the shared query behind both the full page render
     * and the broadcast-triggered partial-refresh endpoint, scoped
     * explicitly by clinic_id (not the acting user's auth scope) so it
     * stays correct when called from contexts without that user in play.
     *
     * @return array{0: \Illuminate\Support\Collection<int, self>, 1: \Illuminate\Support\Collection<int, self>}
     */
    public static function activeAndCompletedForClinic(int $clinicId): array
    {
        $entries = static::withoutClinicScope()
            ->where('clinic_id', $clinicId)
            ->with(['patient', 'provider.user', 'service'])
            ->whereIn('status', ['waiting', 'serving'])
            ->orderByRaw("status = 'serving' desc")
            ->orderBy('joined_at')
            ->get();

        $completedToday = static::withoutClinicScope()
            ->where('clinic_id', $clinicId)
            ->with(['patient', 'provider.user', 'service'])
            ->whereIn('status', ['done', 'left'])
            ->whereDate('completed_at', today())
            ->orderByDesc('completed_at')
            ->get();

        return [$entries, $completedToday];
    }

    /**
     * Front-desk KPIs for the queue: today's throughput, how long people are
     * actually waiting, and a couple of longer-range rollups so a slow week
     * doesn't just vanish once the day's entries scroll off the table.
     *
     * @param  \Illuminate\Support\Collection<int, self>  $activeEntries  the currently waiting/serving entries, already loaded — reused to avoid an extra query
     * @return array<string, int|float|null>
     */
    public static function statsForClinic(int $clinicId, $activeEntries): array
    {
        $base = static::withoutClinicScope()->where('clinic_id', $clinicId);

        $doneToday = (clone $base)->where('status', 'done')->whereDate('completed_at', today())->count();
        $leftToday = (clone $base)->where('status', 'left')->whereDate('completed_at', today())->count();
        $doneThisWeek = (clone $base)->where('status', 'done')->where('completed_at', '>=', now()->subDays(6)->startOfDay())->count();
        $doneThisMonth = (clone $base)->where('status', 'done')->where('completed_at', '>=', now()->subDays(29)->startOfDay())->count();

        // Average wait (joined -> called in) for everyone actually called in
        // today — the clearest read on how long today's walk-ins are sitting
        // before being seen.
        $avgWaitMinutes = (clone $base)
            ->whereNotNull('called_at')
            ->whereDate('called_at', today())
            ->get(['joined_at', 'called_at'])
            ->avg(fn (self $e) => $e->joined_at->diffInMinutes($e->called_at, true));

        $processedToday = $doneToday + $leftToday;

        return [
            'waiting' => $activeEntries->where('status', 'waiting')->count(),
            'serving' => $activeEntries->where('status', 'serving')->count(),
            'done_today' => $doneToday,
            'left_today' => $leftToday,
            'avg_wait_minutes' => $avgWaitMinutes ? (int) round($avgWaitMinutes) : null,
            // Left/no-show as a share of everyone processed today — a rising
            // rate usually means the wait is too long, not a staffing fluke.
            'left_rate' => $processedToday > 0 ? round($leftToday / $processedToday * 100) : null,
            'done_this_week' => $doneThisWeek,
            'done_this_month' => $doneThisMonth,
        ];
    }
}
