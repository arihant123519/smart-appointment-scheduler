<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The UI is Bootstrap-based, not Tailwind (Tailwind is configured but
        // unused/dormant — see resources/css/app.css). Laravel's default
        // ->links() view is Tailwind-styled and renders unstyled without it,
        // so every paginate() call in the app (notifications, audit, etc.)
        // needs the Bootstrap partial instead. Presentation-only — no change
        // to pagination behavior, page size, or query results.
        Paginator::useBootstrapFive();
    }
}
