<?php

namespace App\Services\Messaging;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Channel-abstracted outbound messaging.
 *
 * Email is fully wired through Laravel's mailer (works out of the box with the
 * `log` mail driver locally, or real SMTP once configured in Settings).
 * WhatsApp is dispatched through pluggable drivers; the default driver is `log`
 * (records the message) so the whole flow works with zero external accounts.
 * Set the WhatsApp driver to `gupshup` and add credentials in Settings →
 * Integrations to enable real delivery without touching calling code.
 */
class MessageService
{
    /**
     * @return bool  whether the message was accepted for delivery
     */
    public function send(User $user, string $subject, string $body, ?string $channel = null): bool
    {
        Log::debug('MessageService::send() to '.$user->email.' / '.$user->phone.' via '.$channel.' — '.$subject);
        $channel ??= $user->preferred_channel ?? 'email';

        return match ($channel) {
            'whatsapp' => $this->sendWhatsapp($user, $body),
            default => $this->sendEmail($user, $subject, $body),
        };
    }

    protected function sendEmail(User $user, string $subject, string $body): bool
    {
        if (! $user->email) {
            return false;
        }

        Mail::raw($body, function ($message) use ($user, $subject) {
            $message->to($user->email, $user->name)->subject($subject);
        });

        return true;
    }

    protected function sendWhatsapp(User $user, string $body): bool
    {
        if (! $user->phone) {
            return false;
        }

        $driver = config('services.messaging.whatsapp_driver', 'log');

        if ($driver === 'gupshup') {
            return $this->sendViaGupshup($user, $body);
        }

        // Default `log` driver — records the message without external delivery.
        Log::channel('stack')->info('[WhatsApp:'.$driver.'] to '.$user->phone.' — '.$body);

        return true;
    }

    /**
     * Send an approved WhatsApp *template* message (required for business-
     * initiated messages such as reminders). $params fills {{1}}, {{2}}, ... in
     * the template, in order.
     *
     * @param  array<int, string>  $params
     */
    public function sendWhatsappTemplate(User $user, string $templateId, array $params, ?string $toPhone = null): bool
    {
        $phone = $toPhone ?: $user->phone;

        if (! $phone) {
            return false;
        }

        $driver = config('services.messaging.whatsapp_driver', 'log');

        if ($driver === 'gupshup') {
            return $this->sendViaGupshupTemplate($phone, $templateId, $params);
        }

        Log::channel('stack')->info('[WhatsApp:'.$driver.':template '.$templateId.'] to '.$phone.' — '.implode(' | ', $params));

        return true;
    }

    protected function sendViaGupshupTemplate(string $phone, string $templateId, array $params): bool
    {
        $apiKey = config('services.whatsapp.gupshup_api_key');
        $source = preg_replace('/\D+/', '', (string) config('services.whatsapp.gupshup_source'));
        $appName = config('services.whatsapp.gupshup_app_name');
        $destination = preg_replace('/\D+/', '', $phone);

        if (! $apiKey || ! $source || ! $destination || ! $templateId) {
            Log::channel('stack')->warning('[WhatsApp:gupshup:template] missing credentials/template/destination; not sent to '.$phone);

            return false;
        }

        try {
            $response = Http::asForm()
                ->withHeaders(['apikey' => $apiKey])
                ->post('https://api.gupshup.io/wa/api/v1/template/msg', [
                    'source' => $source,
                    'destination' => $destination,
                    'src.name' => $appName,
                    'template' => json_encode(['id' => $templateId, 'params' => array_values($params)]),
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::channel('stack')->error('[WhatsApp:gupshup:template] failed ('.$response->status().'): '.$response->body());

            return false;
        } catch (\Throwable $e) {
            Log::channel('stack')->error('[WhatsApp:gupshup:template] exception: '.$e->getMessage());

            return false;
        }
    }

    /**
     * Send a WhatsApp text (session) message through the Gupshup API.
     * https://docs.gupshup.io/ — POST form-encoded to the messaging endpoint.
     */
    protected function sendViaGupshup(User $user, string $body): bool
    {
        $apiKey = config('services.whatsapp.gupshup_api_key');
        $source = preg_replace('/\D+/', '', (string) config('services.whatsapp.gupshup_source'));
        $appName = config('services.whatsapp.gupshup_app_name');
        $destination = preg_replace('/\D+/', '', (string) $user->phone);

        if (! $apiKey || ! $source || ! $destination) {
            Log::channel('stack')->warning('[WhatsApp:gupshup] missing credentials or destination; not sent to '.$user->phone);

            return false;
        }

        try {
            $response = Http::asForm()
                ->withHeaders(['apikey' => $apiKey])
                ->post('https://api.gupshup.io/wa/api/v1/msg', [
                    'channel' => 'whatsapp',
                    'source' => $source,
                    'destination' => $destination,
                    'src.name' => $appName,
                    'message' => json_encode(['type' => 'text', 'text' => $body]),
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::channel('stack')->error('[WhatsApp:gupshup] send failed ('.$response->status().'): '.$response->body());

            return false;
        } catch (\Throwable $e) {
            Log::channel('stack')->error('[WhatsApp:gupshup] exception: '.$e->getMessage());

            return false;
        }
    }
}
