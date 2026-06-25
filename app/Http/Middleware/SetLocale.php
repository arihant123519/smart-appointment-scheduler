<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * Applies the user's preferred locale (PRD: multi-language support).
 * Locale comes from ?lang=, the session, or the signed-in user's `locale`.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $supported = ['en', 'es'];

        $locale = $request->query('lang')
            ?? session('locale')
            ?? optional($request->user())->locale
            ?? config('app.locale');

        if (in_array($locale, $supported, true)) {
            App::setLocale($locale);
            session(['locale' => $locale]);
        }

        return $next($request);
    }
}
