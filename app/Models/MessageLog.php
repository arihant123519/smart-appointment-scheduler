<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single outbound or inbound message (email/WhatsApp), logged automatically
 * by MessageService (outbound) or the Gupshup webhook (inbound) regardless of
 * which feature triggered it — reminders, notifications, announcements, flows.
 */
class MessageLog extends Model
{
    protected $fillable = [
        'direction', 'channel', 'status', 'user_id', 'appointment_id', 'conversation_id',
        'address', 'source', 'event_key', 'template_id', 'subject', 'body',
        'provider', 'provider_message_id', 'error', 'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsappConversation::class, 'conversation_id');
    }
}
