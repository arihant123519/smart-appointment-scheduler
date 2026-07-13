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

        // These post as a server-to-server call with no Laravel session/CSRF
        // token — Gupshup is authenticated by a shared-secret `?token=` query
        // param, Razorpay by its signed-payload scheme (see the respective
        // webhook controllers).
        $middleware->validateCsrfTokens(except: [
            'webhooks/gupshup',
            'webhooks/razorpay',
            'webhooks/sms-inbound',
            'webhooks/missed-call',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
