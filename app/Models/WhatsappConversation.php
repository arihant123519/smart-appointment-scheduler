<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A live instance of a WhatsappFlow running against one appointment/patient.
 * `phone` (normalized digits) is how an inbound Gupshup webhook reply is
 * matched back to the conversation that's waiting for it.
 */
class WhatsappConversation extends Model
{
    protected $fillable = [
        'whatsapp_flow_id', 'appointment_id', 'patient_id', 'phone', 'current_node_id',
        'context', 'status', 'outcome', 'started_at', 'last_message_at', 'completed_at',
    ];

    protected $casts = [
        'context' => 'array',
        'started_at' => 'datetime',
        'last_message_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function flow(): BelongsTo
    {
        return $this->belongsTo(WhatsappFlow::class, 'whatsapp_flow_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function messageLogs(): HasMany
    {
        return $this->hasMany(MessageLog::class, 'conversation_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
