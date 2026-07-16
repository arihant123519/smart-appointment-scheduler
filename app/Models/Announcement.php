<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    /** Audience targets for a broadcast. */
    public const AUDIENCES = [
        'scheduled' => 'Patients with a scheduled appointment',
        'patients' => 'All patients',
        'providers' => 'Providers / doctors',
        'all' => 'All users',
        'custom' => 'Custom filter',
    ];

    protected $fillable = [
        'clinic_id', 'created_by', 'title', 'body', 'channel', 'audience', 'filters',
        'wa_template_id', 'wa_namespace', 'wa_variables',
        'recipients_count', 'send_at', 'status', 'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'send_at' => 'datetime',
        'wa_variables' => 'array',
        'filters' => 'array',
    ];

    public function scopeDue($query)
    {
        return $query->where('status', 'scheduled')->where('send_at', '<=', now());
    }

    public function getAudienceLabelAttribute(): string
    {
        return self::AUDIENCES[$this->audience] ?? $this->audience;
    }

    /** "email,whatsapp" => "Email + WhatsApp". */
    public function getChannelLabelAttribute(): string
    {
        $labels = ['email' => 'Email', 'whatsapp' => 'WhatsApp'];

        return collect(explode(',', (string) $this->channel))
            ->filter()
            ->map(fn ($c) => $labels[trim($c)] ?? ucfirst(trim($c)))
            ->implode(' + ');
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
