<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Experiment extends Model
{
    protected $fillable = ['key', 'name', 'variants', 'status'];

    protected $casts = [
        'variants' => 'array',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(ExperimentAssignment::class);
    }
}
