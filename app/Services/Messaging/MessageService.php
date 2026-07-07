<?php

namespace App\Services\Messaging;

use App\Models\Appointment;
use App\Models\MessageLog;
use App\Models\User;
use App\Support\PhoneNumber;
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
 *
 * Every send made through the two public methods below (send/sendWhatsappTemplate)
 * is logged to `message_logs` — this is the single choke point every feature
 * (reminders, appointment notifications, announcements, conversation flows)
 * passes through, so nothing needs to log itself.
 */
class MessageService
{
    /**
     * @return bool  whether the message was accepted for delivery
     */
    public function send(
        User $user,
        string $subject,
        string $body,
        ?string $channel = null,
        ?Appointment $appointment = null,
        ?string $source = null,
        ?string $eventKey = null,
    ): bool {
        Log::debug('MessageService::send() to '.$user->email.' / '.$user->phone.' via '.$channel.' — '.$subject);
        $channel ??= $user->preferred_channel ?? 'email';

        $result = match ($channel) {
            'whatsapp' => $this->sendWhatsapp($user, $body),
            default => $this->sendEmail($user, $subject, $body),
        };

        $this->logMessage([
            'channel' => $channel,
            'user_id' => $user->id,
            'appointment_id' => $appointment?->id,
            'address' => $channel === 'whatsapp' ? PhoneNumber::normalize($user->phone) : $user->email,
            'source' => $source,
            'event_key' => $eventKey,
            'subject' => $subject,
            'body' => $body,
        ], $result);

        return $result['ok'];
    }

    /**
     * @return array{ok: bool, provider: string, provider_message_id: ?string, error: ?string}
     */
    protected function sendEmail(User $user, string $subject, string $body): array
    {
        if (! $user->email) {
            return ['ok' => false, 'provider' => 'smtp', 'provider_message_id' => null, 'error' => 'Patient has no email on file.'];
        }

        try {
            Mail::raw($body, function ($message) use ($user, $subject) {
                $message->to($user->email, $user->name)->subject($subject);
            });

            return ['ok' => true, 'provider' => 'smtp', 'provider_message_id' => null, 'error' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'provider' => 'smtp', 'provider_message_id' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{ok: bool, provider: string, provider_message_id: ?string, error: ?string}
     */
    protected function sendWhatsapp(User $user, string $body): array
    {
        if (! $user->phone) {
            return ['ok' => false, 'provider' => 'log', 'provider_message_id' => null, 'error' => 'Patient has no phone on file.'];
        }

        $driver = config('services.messaging.whatsapp_driver', 'log');

        if ($driver === 'gupshup') {
            return $this->sendViaGupshup($user, $body);
        }

        // Default `log` driver — records the message without external delivery.
        Log::channel('stack')->info('[WhatsApp:'.$driver.'] to '.$user->phone.' — '.$body);

        return ['ok' => true, 'provider' => 'log', 'provider_message_id' => null, 'error' => null];
    }

    /**
     * Send an approved WhatsApp *template* message (required for business-
     * initiated messages such as reminders). $params fills {{1}}, {{2}}, ... in
     * the template, in order.
     *
     * @param  array<int, string>  $params
     */
    public function sendWhatsappTemplate(
        User $user,
        string $templateId,
        array $params,
        ?string $toPhone = null,
        ?Appointment $appointment = null,
        ?string $source = null,
        ?string $eventKey = null,
    ): bool {
        $phone = $toPhone ?: $user->phone;

        if (! $phone) {
            $this->logMessage([
                'channel' => 'whatsapp', 'user_id' => $user->id, 'appointment_id' => $appointment?->id,
                'address' => null, 'source' => $source, 'event_key' => $eventKey, 'template_id' => $templateId,
            ], ['ok' => false, 'provider' => 'log', 'provider_message_id' => null, 'error' => 'No destination phone number.']);

            return false;
        }

        $driver = config('services.messaging.whatsapp_driver', 'log');

        $result = $driver === 'gupshup'
            ? $this->sendViaGupshupTemplate($phone, $templateId, $params)
            : $this->logOnlyTemplate($driver, $phone, $templateId, $params);

        $this->logMessage([
            'channel' => 'whatsapp',
            'user_id' => $user->id,
            'appointment_id' => $appointment?->id,
            'address' => PhoneNumber::normalize($phone),
            'source' => $source,
            'event_key' => $eventKey,
            'template_id' => $templateId,
            'body' => implode(' | ', $params),
        ], $result);

        return $result['ok'];
    }

    /**
     * @param  array<int, string>  $params
     * @return array{ok: bool, provider: string, provider_message_id: ?string, error: ?string}
     */
    protected function logOnlyTemplate(string $driver, string $phone, string $templateId, array $params): array
    {
        Log::channel('stack')->info('[WhatsApp:'.$driver.':template '.$templateId.'] to '.$phone.' — '.implode(' | ', $params));

        return ['ok' => true, 'provider' => 'log', 'provider_message_id' => null, 'error' => null];
    }

    /**
     * @param  array<int, string>  $params
     * @return array{ok: bool, provider: string, provider_message_id: ?string, error: ?string}
     */
    protected function sendViaGupshupTemplate(string $phone, string $templateId, array $params): array
    {
        $apiKey = config('services.whatsapp.gupshup_api_key');
        $source = PhoneNumber::normalize(config('services.whatsapp.gupshup_source'));
        $appName = config('services.whatsapp.gupshup_app_name');
        $destination = PhoneNumber::normalize($phone);

        if (! $apiKey || ! $source || ! $destination || ! $templateId) {
            Log::channel('stack')->warning('[WhatsApp:gupshup:template] missing credentials/template/destination; not sent to '.$phone);

            return ['ok' => false, 'provider' => 'gupshup', 'provider_message_id' => null, 'error' => 'Missing credentials/template/destination.'];
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
                return ['ok' => true, 'provider' => 'gupshup', 'provider_message_id' => $response->json('messageId'), 'error' => null];
            }

            Log::channel('stack')->error('[WhatsApp:gupshup:template] failed ('.$response->status().'): '.$response->body());

            return ['ok' => false, 'provider' => 'gupshup', 'provider_message_id' => null, 'error' => 'HTTP '.$response->status().': '.$response->body()];
        } catch (\Throwable $e) {
            Log::channel('stack')->error('[WhatsApp:gupshup:template] exception: '.$e->getMessage());

            return ['ok' => false, 'provider' => 'gupshup', 'provider_message_id' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send a WhatsApp text (session) message through the Gupshup API.
     * https://docs.gupshup.io/ — POST form-encoded to the messaging endpoint.
     *
     * @return array{ok: bool, provider: string, provider_message_id: ?string, error: ?string}
     */
    protected function sendViaGupshup(User $user, string $body): array
    {
        $apiKey = config('services.whatsapp.gupshup_api_key');
        $source = PhoneNumber::normalize(config('services.whatsapp.gupshup_source'));
        $appName = config('services.whatsapp.gupshup_app_name');
        $destination = PhoneNumber::normalize($user->phone);

        if (! $apiKey || ! $source || ! $destination) {
            Log::channel('stack')->warning('[WhatsApp:gupshup] missing credentials or destination; not sent to '.$user->phone);

            return ['ok' => false, 'provider' => 'gupshup', 'provider_message_id' => null, 'error' => 'Missing credentials or destination.'];
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
                return ['ok' => true, 'provider' => 'gupshup', 'provider_message_id' => $response->json('messageId'), 'error' => null];
            }

            Log::channel('stack')->error('[WhatsApp:gupshup] send failed ('.$response->status().'): '.$response->body());

            return ['ok' => false, 'provider' => 'gupshup', 'provider_message_id' => null, 'error' => 'HTTP '.$response->status().': '.$response->body()];
        } catch (\Throwable $e) {
            Log::channel('stack')->error('[WhatsApp:gupshup] exception: '.$e->getMessage());

            return ['ok' => false, 'provider' => 'gupshup', 'provider_message_id' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array{ok: bool, provider: string, provider_message_id: ?string, error: ?string}  $result
     */
    protected function logMessage(array $ctx, array $result): void
    {
        MessageLog::create(array_merge($ctx, [
            'direction' => 'outbound',
            'status' => $result['ok'] ? 'sent' : 'failed',
            'provider' => $result['provider'],
            'provider_message_id' => $result['provider_message_id'],
            'error' => $result['error'],
            'sent_at' => $result['ok'] ? now() : null,
        ]));
    }
}
