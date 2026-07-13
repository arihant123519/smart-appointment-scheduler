<?php

namespace App\Services\Messaging;

use App\Models\Appointment;
use App\Models\MessageLog;
use App\Models\Setting;
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
 * Credentials are clinic-scoped: every send resolves which clinic it belongs
 * to (from the recipient User's clinic_id, the related Appointment's, or an
 * explicit override) and looks up THAT clinic's own saved driver/credentials
 * via {@see Setting::getForClinic()} — never a single global account shared
 * across every clinic.
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
        ?int $clinicId = null,
    ): bool {
        Log::debug('MessageService::send() to '.$user->email.' / '.$user->phone.' via '.$channel.' — '.$subject);
        $channel ??= $user->preferred_channel ?? 'email';
        $clinicId ??= $user->clinic_id ?? $appointment?->clinic_id;

        $result = match ($channel) {
            'whatsapp' => $this->sendWhatsapp($user, $body, $clinicId),
            'sms' => $this->sendSms($user, $body, $clinicId),
            default => $this->sendEmail($user, $subject, $body, $clinicId),
        };

        $this->logMessage([
            'channel' => $channel,
            'user_id' => $user->id,
            'appointment_id' => $appointment?->id,
            'address' => in_array($channel, ['whatsapp', 'sms'], true) ? PhoneNumber::normalize($user->phone) : $user->email,
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
    protected function sendEmail(User $user, string $subject, string $body, ?int $clinicId): array
    {
        if (! $user->email) {
            return ['ok' => false, 'provider' => 'smtp', 'provider_message_id' => null, 'error' => 'Patient has no email on file.'];
        }

        $this->applyClinicMailConfig($clinicId);

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
     * Push a clinic's own saved SMTP settings into the mailer config right
     * before sending. No-op (falls through to the app-wide `.env` mailer)
     * when the clinic hasn't configured its own. Re-applied on every send
     * rather than at boot, since each request may belong to a different
     * clinic.
     */
    protected function applyClinicMailConfig(?int $clinicId): void
    {
        if (! $clinicId) {
            return;
        }

        $host = Setting::getForClinic($clinicId, 'mail.host');
        if (! $host) {
            return;
        }

        config([
            'mail.default' => Setting::getForClinic($clinicId, 'mail.mailer', config('mail.default')),
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => (int) Setting::getForClinic($clinicId, 'mail.port', 587),
            'mail.mailers.smtp.username' => Setting::getForClinic($clinicId, 'mail.username'),
            'mail.mailers.smtp.password' => Setting::getForClinic($clinicId, 'mail.password'),
            'mail.mailers.smtp.scheme' => match (Setting::getForClinic($clinicId, 'mail.scheme')) {
                'ssl', 'smtps' => 'smtps',
                default => null, // tls / none → STARTTLS
            },
        ]);

        $fromAddress = Setting::getForClinic($clinicId, 'mail.from_address');
        if ($fromAddress) {
            config(['mail.from.address' => $fromAddress]);
        }
        $fromName = Setting::getForClinic($clinicId, 'mail.from_name');
        if ($fromName) {
            config(['mail.from.name' => $fromName]);
        }
    }

    /**
     * @return array{ok: bool, provider: string, provider_message_id: ?string, error: ?string}
     */
    protected function sendWhatsapp(User $user, string $body, ?int $clinicId): array
    {
        if (! $user->phone) {
            return ['ok' => false, 'provider' => 'log', 'provider_message_id' => null, 'error' => 'Patient has no phone on file.'];
        }

        $driver = $this->whatsappDriver($clinicId);

        if ($driver === 'gupshup') {
            return $this->sendViaGupshup($user->phone, $body, $clinicId);
        }

        // Default `log` driver — records the message without external delivery.
        Log::channel('stack')->info('[WhatsApp:'.$driver.'] to '.$user->phone.' — '.$body);

        return ['ok' => true, 'provider' => 'log', 'provider_message_id' => null, 'error' => null];
    }

    /**
     * @return array{ok: bool, provider: string, provider_message_id: ?string, error: ?string}
     */
    protected function sendSms(User $user, string $body, ?int $clinicId): array
    {
        if (! $user->phone) {
            return ['ok' => false, 'provider' => 'log', 'provider_message_id' => null, 'error' => 'Patient has no phone on file.'];
        }

        return $this->sendSmsToDestination($user->phone, $body, $clinicId);
    }

    /**
     * Send a plain SMS to a raw phone number with no User context — used by
     * the missed-call text-back and SMS-booking webhooks, where the caller
     * may not have an account on file at all. $clinicId is required in that
     * case (resolved by the webhook from which clinic's secret matched);
     * otherwise it falls back to $user's or $appointment's clinic.
     */
    public function sendSmsToPhone(
        string $phone,
        string $body,
        ?User $user = null,
        ?Appointment $appointment = null,
        ?string $source = null,
        ?string $eventKey = null,
        ?int $clinicId = null,
    ): bool {
        $clinicId ??= $user?->clinic_id ?? $appointment?->clinic_id;
        $result = $this->sendSmsToDestination($phone, $body, $clinicId);

        $this->logMessage([
            'channel' => 'sms',
            'user_id' => $user?->id,
            'appointment_id' => $appointment?->id,
            'address' => PhoneNumber::normalize($phone),
            'source' => $source,
            'event_key' => $eventKey,
            'body' => $body,
        ], $result);

        return $result['ok'];
    }

    /**
     * @return array{ok: bool, provider: string, provider_message_id: ?string, error: ?string}
     */
    protected function sendSmsToDestination(string $phone, string $body, ?int $clinicId): array
    {
        $destination = PhoneNumber::normalize($phone);
        if (! $destination) {
            return ['ok' => false, 'provider' => 'log', 'provider_message_id' => null, 'error' => 'No destination phone number.'];
        }

        $driver = $this->smsDriver($clinicId);

        if ($driver === 'gupshup') {
            return $this->sendViaGupshupSms($destination, $body, $clinicId);
        }

        Log::channel('stack')->info('[SMS:'.$driver.'] to '.$destination.' — '.$body);

        return ['ok' => true, 'provider' => 'log', 'provider_message_id' => null, 'error' => null];
    }

    /**
     * Send a plain SMS through Gupshup's SMS API — same apikey-header
     * pattern as the WhatsApp integration, different endpoint/product. Verify
     * against a live Gupshup dashboard if a real send ever looks wrong.
     *
     * @return array{ok: bool, provider: string, provider_message_id: ?string, error: ?string}
     */
    protected function sendViaGupshupSms(string $destination, string $body, ?int $clinicId): array
    {
        $apiKey = $this->clinicSetting($clinicId, 'sms.gupshup_api_key', 'services.sms.gupshup_api_key');
        $source = PhoneNumber::normalize($this->clinicSetting($clinicId, 'sms.gupshup_source', 'services.sms.gupshup_source'));

        if (! $apiKey || ! $source) {
            Log::channel('stack')->warning('[SMS:gupshup] missing credentials for clinic #'.$clinicId.'; not sent to '.$destination);

            return ['ok' => false, 'provider' => 'gupshup', 'provider_message_id' => null, 'error' => 'Missing credentials.'];
        }

        try {
            $response = Http::asForm()
                ->withHeaders(['apikey' => $apiKey])
                ->post('https://api.gupshup.io/sm/api/v1/msg', [
                    'channel' => 'sms',
                    'source' => $source,
                    'destination' => $destination,
                    'message' => $body,
                ]);

            if ($response->successful()) {
                return ['ok' => true, 'provider' => 'gupshup', 'provider_message_id' => $response->json('messageId'), 'error' => null];
            }

            Log::channel('stack')->error('[SMS:gupshup] send failed ('.$response->status().'): '.$response->body());

            return ['ok' => false, 'provider' => 'gupshup', 'provider_message_id' => null, 'error' => 'HTTP '.$response->status().': '.$response->body()];
        } catch (\Throwable $e) {
            Log::channel('stack')->error('[SMS:gupshup] exception: '.$e->getMessage());

            return ['ok' => false, 'provider' => 'gupshup', 'provider_message_id' => null, 'error' => $e->getMessage()];
        }
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
        ?int $clinicId = null,
    ): bool {
        $phone = $toPhone ?: $user->phone;
        $clinicId ??= $user->clinic_id ?? $appointment?->clinic_id;

        if (! $phone) {
            $this->logMessage([
                'channel' => 'whatsapp', 'user_id' => $user->id, 'appointment_id' => $appointment?->id,
                'address' => null, 'source' => $source, 'event_key' => $eventKey, 'template_id' => $templateId,
            ], ['ok' => false, 'provider' => 'log', 'provider_message_id' => null, 'error' => 'No destination phone number.']);

            return false;
        }

        $driver = $this->whatsappDriver($clinicId);

        $result = $driver === 'gupshup'
            ? $this->sendViaGupshupTemplate($phone, $templateId, $params, $clinicId)
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
    protected function sendViaGupshupTemplate(string $phone, string $templateId, array $params, ?int $clinicId): array
    {
        $apiKey = $this->clinicSetting($clinicId, 'whatsapp.gupshup_api_key', 'services.whatsapp.gupshup_api_key');
        $source = PhoneNumber::normalize($this->clinicSetting($clinicId, 'whatsapp.gupshup_source', 'services.whatsapp.gupshup_source'));
        $appName = $this->clinicSetting($clinicId, 'whatsapp.gupshup_app_name', 'services.whatsapp.gupshup_app_name');
        $destination = PhoneNumber::normalize($phone);

        if (! $apiKey || ! $source || ! $destination || ! $templateId) {
            Log::channel('stack')->warning('[WhatsApp:gupshup:template] missing credentials/template/destination for clinic #'.$clinicId.'; not sent to '.$phone);

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
    protected function sendViaGupshup(string $phone, string $body, ?int $clinicId): array
    {
        $apiKey = $this->clinicSetting($clinicId, 'whatsapp.gupshup_api_key', 'services.whatsapp.gupshup_api_key');
        $source = PhoneNumber::normalize($this->clinicSetting($clinicId, 'whatsapp.gupshup_source', 'services.whatsapp.gupshup_source'));
        $appName = $this->clinicSetting($clinicId, 'whatsapp.gupshup_app_name', 'services.whatsapp.gupshup_app_name');
        $destination = PhoneNumber::normalize($phone);

        if (! $apiKey || ! $source || ! $destination) {
            Log::channel('stack')->warning('[WhatsApp:gupshup] missing credentials or destination for clinic #'.$clinicId.'; not sent to '.$phone);

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

    /** A clinic's saved WhatsApp driver, falling back to the app-wide `.env` default. */
    protected function whatsappDriver(?int $clinicId): string
    {
        return $this->clinicSetting($clinicId, 'messaging.whatsapp_driver', 'services.messaging.whatsapp_driver') ?: 'log';
    }

    /** A clinic's saved SMS driver, falling back to the app-wide `.env` default. */
    protected function smsDriver(?int $clinicId): string
    {
        return $this->clinicSetting($clinicId, 'messaging.sms_driver', 'services.messaging.sms_driver') ?: 'log';
    }

    /**
     * A clinic's own saved value for $key, falling back to the app-wide
     * `.env`-backed config default at $configPath when the clinic hasn't
     * configured its own (or when there's no clinic context at all).
     */
    protected function clinicSetting(?int $clinicId, string $key, string $configPath): mixed
    {
        $default = config($configPath);

        return $clinicId ? Setting::getForClinic($clinicId, $key, $default) : $default;
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
