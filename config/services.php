<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // --- Smart Appointment Scheduler service drivers ------------------------

    'ai' => [
        'provider' => env('AI_PROVIDER', 'rule_based'), // gemini | openai | rule_based
        'timeout' => (int) env('AI_TIMEOUT', 20),       // seconds before we fall back
        'max_per_minute' => (int) env('AI_MAX_PER_MINUTE', 60), // cost / rate-limit guard
        'minimize_phi' => (bool) env('AI_MINIMIZE_PHI', true),  // scrub PHI before external calls

        // Provider-specific credentials. Existing .env keys are reused as-is so the
        // integration works without renaming anything — just set AI_PROVIDER=gemini.
        'gemini' => [
            'key' => env('GEMINI_API_KEY', env('AI_API_KEY')),
            'model' => env('GEMINI_MODEL_ID', env('AI_MODEL', 'gemini-2.5-flash')),
            'endpoint' => env('GEMINI_ENDPOINT', 'https://generativelanguage.googleapis.com/v1beta/models'),
        ],
        'openai' => [
            'key' => env('OPENAI_API_KEY', env('AI_API_KEY')),
            'model' => env('OPENAI_MODEL', env('AI_MODEL', 'gpt-4o-mini')),
            'endpoint' => env('OPENAI_ENDPOINT', 'https://api.openai.com/v1/chat/completions'),
        ],
    ],

    'messaging' => [
        'whatsapp_driver' => env('MESSAGING_WHATSAPP_DRIVER', 'log'), // log | gupshup
    ],

    'whatsapp' => [
        'gupshup_api_key' => env('GUPSHUP_API_KEY'),
        'gupshup_source' => env('GUPSHUP_SOURCE'),     // sender WhatsApp number (digits, with country code)
        'gupshup_app_name' => env('GUPSHUP_APP_NAME'), // Gupshup app name (src.name)

        // Legacy default reminder template. Templates are now managed per event
        // in Settings → Integrations (see App\Support\WhatsappTemplate); this
        // env value seeds the default "Appointment reminder" section.
        'gupshup_template_id' => env('GUPSHUP_TEMPLATE_ID'),
        'gupshup_namespace' => env('GUPSHUP_NAMESPACE'),

        // Shared secret checked against `?token=` on the inbound Gupshup
        // webhook (routes/web.php: webhooks.gupshup) — Gupshup does not sign
        // its webhook payloads, so this is the only auth on that public route.
        'gupshup_webhook_secret' => env('GUPSHUP_WEBHOOK_SECRET'),
    ],

    'telehealth' => [
        'driver' => env('TELEHEALTH_DRIVER', 'jitsi'), // jitsi | zoom | twilio | daily
    ],

    'payments' => [
        'driver' => env('PAYMENTS_DRIVER', 'manual'), // manual | stripe
        'stripe_key' => env('STRIPE_KEY'),
        'stripe_secret' => env('STRIPE_SECRET'),
    ],

];
