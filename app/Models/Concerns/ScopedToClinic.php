<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Adds a `forCurrentClinic()` query scope. System admins see all clinics;
 * everyone else is limited to the records of their own clinic (multi-tenant).
 */
trait ScopedToClinic
{
    public function scopeForCurrentClinic(Builder $query): Builder
    {
        $user = auth()->user();

        if (! $user || $user->hasRole('system_admin')) {
            return $query;
        }

        if ($user->clinic_id) {
            return $query->where($this->getTable().'.clinic_id', $user->clinic_id);
        }

        return $query;
    }
}
