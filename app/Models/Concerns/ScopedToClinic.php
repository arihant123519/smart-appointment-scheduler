<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Enforces multi-tenant isolation by `clinic_id`. Registers a real Eloquent
 * global scope (named "clinic") so every query against a model using this
 * trait is automatically restricted to the acting user's clinic — the old
 * `forCurrentClinic()` local scope was opt-in, which meant any query a
 * controller forgot to chain it onto silently leaked data across clinics
 * (see the cross-clinic IDOR findings in the operational audit). Guest/console
 * contexts (no authenticated user — webhooks, queued jobs, artisan commands)
 * and `system_admin` are intentionally unrestricted, matching the previous
 * behavior exactly.
 *
 * `forCurrentClinic()` is kept as a harmless no-op-when-redundant alias so
 * existing explicit call sites keep working unchanged. To deliberately see
 * across clinics (system-level reports, admin tooling), use
 * `Model::withoutClinicScope()`.
 */
trait ScopedToClinic
{
    public static function bootScopedToClinic(): void
    {
        static::addGlobalScope('clinic', function (Builder $query) {
            // Reentrancy guard: resolving the authenticated user (auth()->user())
            // runs a query against the User model, which — because User also uses
            // this trait — re-enters this very closure before the guard has cached
            // its user. Without this guard that recurses until memory is exhausted.
            // While that outer resolution is in flight we simply skip scoping.
            static $resolving = false;

            if ($resolving) {
                return;
            }

            $resolving = true;

            try {
                $user = auth()->user();

                if (! $user || $user->hasRole('system_admin') || ! $user->clinic_id) {
                    return;
                }

                $query->where($query->getModel()->getTable().'.clinic_id', $user->clinic_id);
            } finally {
                $resolving = false;
            }
        });
    }

    public function scopeForCurrentClinic(Builder $query): Builder
    {
        return $query;
    }

    public function scopeWithoutClinicScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('clinic');
    }
}
