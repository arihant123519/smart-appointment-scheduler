<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Staff-created accounts (patients/providers/users made by front desk or an
 * admin) start with a temporary password only the creating staff member saw.
 * Block access to everything except the profile/password-change screen and
 * logout until the user sets their own password.
 */
class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $exempt = $request->routeIs('profile.*', 'logout');

        if ($user && $user->must_change_password && ! $exempt) {
            return redirect()->route('profile.edit')
                ->with('error', 'Please set your own password before continuing.');
        }

        return $next($request);
    }
}
