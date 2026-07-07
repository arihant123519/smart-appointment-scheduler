<?php

namespace App\Models;

use App\Models\Concerns\ScopedToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * A staff-authored WhatsApp conversation flow, built visually in the flow
 * builder UI and executed by FlowEngine. Only one flow can be `active` per
 * (clinic, trigger_event) pair — activating one archives any sibling active
 * flow sharing the same trigger.
 */
class WhatsappFlow extends Model
{
    use ScopedToClinic;

    protected $fillable = [
        'clinic_id', 'name', 'trigger_event', 'status', 'canvas_json', 'graph', 'version', 'created_by',
    ];

    protected $casts = [
        'canvas_json' => 'array',
        'graph' => 'array',
        'version' => 'integer',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(WhatsappConversation::class);
    }

    public static function activeFor(string $triggerEvent, ?int $clinicId = null): ?self
    {
        return static::query()
            ->where('trigger_event', $triggerEvent)
            ->where('status', 'active')
            ->when($clinicId, fn ($q) => $q->where(fn ($q2) => $q2->where('clinic_id', $clinicId)->orWhereNull('clinic_id')))
            ->when(! $clinicId, fn ($q) => $q->whereNull('clinic_id'))
            ->orderByRaw('clinic_id is null') // prefer a clinic-specific flow over a global one
            ->first();
    }

    public function activate(): void
    {
        DB::transaction(function () {
            static::query()
                ->where('trigger_event', $this->trigger_event)
                ->where('clinic_id', $this->clinic_id)
                ->where('id', '!=', $this->id)
                ->where('status', 'active')
                ->update(['status' => 'archived']);

            $this->update(['status' => 'active']);
        });
    }
}
