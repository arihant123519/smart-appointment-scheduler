<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id', 'action', 'entity', 'entity_id',
        'before', 'after', 'ip', 'user_agent',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, $model = null, array $before = null, array $after = null): void
    {
        static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'entity' => $model ? class_basename($model) : null,
            'entity_id' => $model?->id,
            'before' => $before,
            'after' => $after,
            'ip' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
        ]);
    }
}
