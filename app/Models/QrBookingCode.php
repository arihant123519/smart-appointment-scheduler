<?php

namespace App\Models;

use App\Models\Concerns\ScopedToClinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Named QrBookingCode (not QrCode) to avoid colliding with the
 * endroid/qr-code package's own Endroid\QrCode\QrCode class.
 */
class QrBookingCode extends Model
{
    use ScopedToClinic;

    protected $table = 'qr_codes';

    protected $fillable = [
        'clinic_id', 'service_id', 'label', 'token',
        'scans_count', 'bookings_count', 'created_by',
    ];

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getRedeemUrlAttribute(): string
    {
        return route('qrcodes.redeem', $this->token);
    }

    public function getImageUrlAttribute(): string
    {
        return route('qrcodes.image', $this->token);
    }
}
