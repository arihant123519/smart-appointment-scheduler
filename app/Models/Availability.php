<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Availability extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id', 'day_of_week', 'start_time', 'end_time',
        'recurring', 'effective_from', 'effective_to',
    ];

    protected $casts = [
        'recurring' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }
}
