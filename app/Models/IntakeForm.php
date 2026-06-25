<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntakeForm extends Model
{
    protected $fillable = [
        'appointment_id', 'schema', 'responses', 'ai_summary',
        'status', 'signed_at', 'signature_name',
    ];

    protected $casts = [
        'schema' => 'array',
        'responses' => 'array',
        'signed_at' => 'datetime',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
