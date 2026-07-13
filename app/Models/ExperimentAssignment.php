<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExperimentAssignment extends Model
{
    protected $fillable = ['experiment_id', 'subject_key', 'variant', 'converted_at'];

    protected $casts = [
        'converted_at' => 'datetime',
    ];

    public function experiment(): BelongsTo
    {
        return $this->belongsTo(Experiment::class);
    }
}
