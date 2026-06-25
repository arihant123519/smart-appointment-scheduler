<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
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
        $this->applyIntegrationSettings();
    }

    /**
     * Push DB-stored integration credentials (Email / SMS / WhatsApp) into the
     * runtime config so the mailer and MessageService use them transparently.
     * Guarded so it is a no-op before the settings table exists (fresh installs,
     * migrations, etc.).
     */
    protected function applyIntegrationSettings(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $s = fn (string $key, $default = null) => Setting::get($key, $default);

        // --- Email (SMTP) ---
        if ($s('mail.host')) {
            config([
                'mail.default' => $s('mail.mailer', config('mail.default')),
                'mail.mailers.smtp.host' => $s('mail.host'),
                'mail.mailers.smtp.port' => (int) $s('mail.port', 587),
                'mail.mailers.smtp.username' => $s('mail.username'),
                'mail.mailers.smtp.password' => $s('mail.password'),
                // Laravel 11 / Symfony Mailer only understands `smtps` (implicit
                // TLS, port 465) or an empty scheme (STARTTLS, port 587). Map the
                // friendly TLS/SSL choices to valid transport schemes.
                'mail.mailers.smtp.scheme' => match ($s('mail.scheme')) {
                    'ssl', 'smtps' => 'smtps',
                    default => null, // tls / none → STARTTLS
                },
            ]);
        }
        if ($s('mail.from_address')) {
            config(['mail.from.address' => $s('mail.from_address')]);
        }
        if ($s('mail.from_name')) {
            config(['mail.from.name' => $s('mail.from_name')]);
        }

        // --- WhatsApp (Gupshup) driver + credentials ---
        if ($s('messaging.whatsapp_driver')) {
            config(['services.messaging.whatsapp_driver' => $s('messaging.whatsapp_driver')]);
        }
        config([
            'services.whatsapp.gupshup_api_key' => $s('whatsapp.gupshup_api_key', config('services.whatsapp.gupshup_api_key')),
            'services.whatsapp.gupshup_source' => $s('whatsapp.gupshup_source', config('services.whatsapp.gupshup_source')),
            'services.whatsapp.gupshup_app_name' => $s('whatsapp.gupshup_app_name', config('services.whatsapp.gupshup_app_name')),
            'services.whatsapp.gupshup_template_id' => $s('whatsapp.gupshup_template_id', config('services.whatsapp.gupshup_template_id')),
            'services.whatsapp.gupshup_namespace' => $s('whatsapp.gupshup_namespace', config('services.whatsapp.gupshup_namespace')),
        ]);
    }
}
