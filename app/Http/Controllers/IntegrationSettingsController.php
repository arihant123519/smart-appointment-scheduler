<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\Messaging\MessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Admin screen for the Email and WhatsApp (Gupshup) integration credentials.
 *
 * Values are persisted to the `settings` table (secrets encrypted) and pushed
 * into config() at runtime by AppServiceProvider, so the mailer and
 * MessageService pick them up without any .env editing or redeploy.
 */
class IntegrationSettingsController extends Controller
{
    /** Secret keys are encrypted and never echoed back to the form. */
    protected array $secretKeys = ['mail.password', 'whatsapp.gupshup_api_key'];

    public function edit(): View
    {
        $keys = [
            'mail.mailer', 'mail.host', 'mail.port', 'mail.username', 'mail.scheme',
            'mail.from_address', 'mail.from_name',
            'messaging.whatsapp_driver',
            'whatsapp.gupshup_source', 'whatsapp.gupshup_app_name',
            'whatsapp.gupshup_template_id', 'whatsapp.gupshup_namespace',
        ];

        $values = [];
        foreach ($keys as $key) {
            $values[$key] = Setting::get($key);
        }

        $secretSet = [];
        foreach ($this->secretKeys as $key) {
            $secretSet[$key] = Setting::has($key);
        }

        return view('settings.integrations', compact('values', 'secretSet'));
    }

    public function update(Request $request): RedirectResponse
    {
        return match ($request->input('section')) {
            'email' => $this->saveEmail($request),
            'whatsapp' => $this->saveWhatsapp($request),
            default => back()->with('error', 'Unknown settings section.'),
        };
    }

    protected function saveEmail(Request $request): RedirectResponse
    {
        $request->validate([
            'mailer' => ['required', 'in:smtp,log'],
            'host' => ['nullable', 'required_if:mailer,smtp', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'scheme' => ['nullable', 'in:tls,ssl,smtps'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
        ]);

        Setting::set('mail.mailer', $request->input('mailer', 'smtp'), 'email');
        Setting::set('mail.host', $request->input('host'), 'email');
        Setting::set('mail.port', $request->input('port'), 'email');
        Setting::set('mail.username', $request->input('username'), 'email');
        Setting::set('mail.scheme', $request->input('scheme'), 'email');
        Setting::set('mail.from_address', $request->input('from_address'), 'email');
        Setting::set('mail.from_name', $request->input('from_name'), 'email');
        $this->setSecret('mail.password', $request->input('password'), 'email');

        return back()->with('success', 'Email (SMTP) settings saved.');
    }

    protected function saveWhatsapp(Request $request): RedirectResponse
    {
        $request->validate([
            'driver' => ['required', 'in:log,gupshup'],
            'source' => ['nullable', 'string', 'max:40'],
            'app_name' => ['nullable', 'string', 'max:120'],
            'api_key' => ['nullable', 'string', 'max:255'],
            'template_id' => ['nullable', 'string', 'max:255'],
            'namespace' => ['nullable', 'string', 'max:255'],
        ]);

        Setting::set('messaging.whatsapp_driver', $request->input('driver', 'log'), 'whatsapp');
        Setting::set('whatsapp.gupshup_source', $request->input('source'), 'whatsapp');
        Setting::set('whatsapp.gupshup_app_name', $request->input('app_name'), 'whatsapp');
        Setting::set('whatsapp.gupshup_template_id', $request->input('template_id'), 'whatsapp');
        Setting::set('whatsapp.gupshup_namespace', $request->input('namespace'), 'whatsapp');
        $this->setSecret('whatsapp.gupshup_api_key', $request->input('api_key'), 'whatsapp');

        return back()->with('success', 'WhatsApp (Gupshup) settings saved.');
    }

    /** Only overwrite a secret when a new value was actually entered. */
    protected function setSecret(string $key, ?string $value, string $group): void
    {
        if ($value !== null && $value !== '') {
            Setting::set($key, $value, $group, true);
        }
    }

    /** Send a test message — to a typed recipient, or the current user. */
    public function test(Request $request, MessageService $messaging): RedirectResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'in:email,whatsapp'],
            'to' => ['nullable', 'string', 'max:60'],
        ]);
        $channel = $data['channel'];
        $to = $data['to'] ?? null;
        $user = $request->user();

        if ($channel === 'whatsapp') {
            // WhatsApp business-initiated messages must use an approved template.
            $templateId = config('services.whatsapp.gupshup_template_id');
            if (! $templateId) {
                return back()->with('error', 'Set and save the reminder template ID first — WhatsApp needs an approved template to send.');
            }
            if (! $to && ! $user->phone) {
                return back()->with('error', 'Enter a test WhatsApp number (with country code), or add a phone number to your profile.');
            }

            $ok = $messaging->sendWhatsappTemplate($user, $templateId, ['Today', '5:00 PM', 'Dr. Smith'], $to);

            if (! $ok) {
                return back()->with('error', 'WhatsApp test failed. Check storage/logs/laravel.log for "[WhatsApp:gupshup:template]" — common causes: API key not saved, template still Pending (not yet Approved), or the number has not opted in.');
            }

            return back()->with('success', 'Test WhatsApp dispatched via Gupshup to '.($to ?: $user->phone).'. Check the device and the log.');
        }

        // Email
        if (! $to && ! $user->email) {
            return back()->with('error', 'Enter a test email address, or add one to your profile.');
        }

        $recipient = $to
            ? new \App\Models\User(['email' => $to, 'name' => 'Test recipient'])
            : $user;

        $ok = $messaging->send(
            $recipient,
            'Test message — Smart Appointment Scheduler',
            'This is a test email confirming your integration settings are wired up correctly.',
            'email',
        );

        if (! $ok) {
            return back()->with('error', 'Test email could not be sent — check the SMTP credentials above.');
        }

        return back()->with('success', 'Test email dispatched to '.($to ?: $user->email).'. Check the inbox (or the log if the mailer is set to Log).');
    }
}
