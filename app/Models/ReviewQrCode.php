<?php

namespace App\Models;

use App\Models\Concerns\ScopedToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewQrCode extends Model
{
    use ScopedToClinic;

    protected $fillable = [
        'clinic_id', 'label', 'token', 'scans_count', 'submissions_count', 'created_by',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getSubmitUrlAttribute(): string
    {
        return route('reviews.public.show', $this->token);
    }

    public function getImageUrlAttribute(): string
    {
        return route('reviews.public.image', $this->token);
    }
}
