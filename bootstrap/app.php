<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
        ]);

        // Gupshup posts to this webhook as a server-to-server call with no
        // Laravel session/CSRF token — it's authenticated by a shared-secret
        // `?token=` query param instead (see GupshupWebhookController).
        $middleware->validateCsrfTokens(except: [
            'webhooks/gupshup',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
