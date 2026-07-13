<?php

namespace App\Http\Controllers;

use App\Models\Clinic;
use App\Models\Setting;
use App\Models\User;
use App\Services\AI\AiService;
use App\Services\Messaging\MessageService;
use App\Support\WhatsappTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin screen for the Email, WhatsApp (Gupshup), SMS (Gupshup) and Payments
 * (Razorpay) integration credentials.
 *
 * Credentials are clinic-scoped (see {@see Setting}): a clinic admin only
 * ever sees and edits their own clinic's row — one clinic's saved WhatsApp/
 * SMS/Razorpay account never applies to another clinic. A system admin (who
 * holds `manage clinics`) additionally gets a clinic picker on this page and
 * can view/edit any clinic's credentials.
 *
 * Values are persisted to the `settings` table (secrets encrypted) and read
 * directly by MessageService/PaymentService at send time — never cached into
 * global config(), since that would leak one clinic's credentials into
 * another request.
 */
class IntegrationSettingsController extends Controller
{
    /** Secret keys are encrypted and never echoed back to the form. */
    protected array $secretKeys = [
        'mail.password',
        'whatsapp.gupshup_api_key', 'whatsapp.gupshup_webhook_secret',
        'sms.gupshup_api_key', 'sms.inbound_webhook_secret', 'sms.missed_call_webhook_secret',
        'payments.razorpay_key_secret', 'payments.razorpay_webhook_secret',
        'ai.gemini_key', 'ai.openai_key',
    ];

    public function edit(Request $request): View
    {
        $clinicId = $this->resolveClinicId((int) $request->query('clinic') ?: null);
        $clinics = $this->pickerClinics();

        // A system admin lands here with no clinic picked yet — show the
        // picker and nothing else until one is actively selected, rather
        // than silently defaulting to (and risking edits landing on) their
        // own clinic.
        if ($clinicId === null) {
            return view('settings.integrations', [
                'clinicId' => null, 'clinics' => $clinics,
                'values' => [], 'secretSet' => [], 'sections' => [],
                'events' => WhatsappTemplate::events(), 'tokens' => WhatsappTemplate::tokens(),
                'webhookUrl' => null, 'smsInboundWebhookUrl' => null,
                'missedCallWebhookUrl' => null, 'razorpayWebhookUrl' => null,
            ]);
        }

        $keys = [
            'mail.mailer', 'mail.host', 'mail.port', 'mail.username', 'mail.scheme',
            'mail.from_address', 'mail.from_name',
            'messaging.whatsapp_driver',
            'whatsapp.gupshup_source', 'whatsapp.gupshup_app_name',
            'messaging.sms_driver', 'sms.gupshup_source',
            'payments.driver', 'payments.razorpay_key_id',
            'ai.provider', 'ai.gemini_model', 'ai.openai_model',
        ];

        $values = [];
        foreach ($keys as $key) {
            $values[$key] = Setting::getForClinic($clinicId, $key);
        }

        $secretSet = [];
        foreach ($this->secretKeys as $key) {
            $secretSet[$key] = Setting::hasForClinic($clinicId, $key);
        }

        // Dynamic WhatsApp template sections (add / edit / remove). Use the
        // editor variant so every event — including newly-added ones — shows up.
        $sections = WhatsappTemplate::sectionsForEditing($clinicId);
        $events = WhatsappTemplate::events();
        $tokens = WhatsappTemplate::tokens();

        // Ready-to-paste inbound URLs. Each is only shown once its shared
        // secret has actually been saved, so nobody pastes a token-less URL.
        $webhookUrl = $secretSet['whatsapp.gupshup_webhook_secret']
            ? url('/webhooks/gupshup').'?token='.Setting::getForClinic($clinicId, 'whatsapp.gupshup_webhook_secret')
            : null;
        $smsInboundWebhookUrl = $secretSet['sms.inbound_webhook_secret']
            ? url('/webhooks/sms-inbound').'?token='.Setting::getForClinic($clinicId, 'sms.inbound_webhook_secret')
            : null;
        $missedCallWebhookUrl = $secretSet['sms.missed_call_webhook_secret']
            ? url('/webhooks/missed-call').'?token='.Setting::getForClinic($clinicId, 'sms.missed_call_webhook_secret')
            : null;
        $razorpayWebhookUrl = $secretSet['payments.razorpay_webhook_secret']
            ? url('/webhooks/razorpay')
            : null;

        return view('settings.integrations', compact(
            'values', 'secretSet', 'sections', 'events', 'tokens', 'clinicId', 'clinics',
            'webhookUrl', 'smsInboundWebhookUrl', 'missedCallWebhookUrl', 'razorpayWebhookUrl',
        ));
    }

    public function update(Request $request): RedirectResponse
    {
        $clinicId = $this->resolveClinicId((int) $request->input('clinic_id') ?: null);
        if ($clinicId === null) {
            return back()->with('error', 'Select a clinic first.');
        }

        return match ($request->input('section')) {
            'email' => $this->saveEmail($request, $clinicId),
            'whatsapp' => $this->saveWhatsapp($request, $clinicId),
            'sms' => $this->saveSms($request, $clinicId),
            'payments' => $this->savePayments($request, $clinicId),
            'ai' => $this->saveAi($request, $clinicId),
            default => back()->with('error', 'Unknown settings section.'),
        };
    }

    /**
     * Which clinic's settings this request is for. A system admin (holds
     * `manage clinics`) may target any clinic via the picker/hidden field,
     * but gets no silent default — null until one is actively picked, so a
     * fresh visit to this page shows "select a clinic" rather than risking
     * an edit landing on the admin's own clinic by accident. Everyone else
     * is hard-locked to their own clinic regardless of what was submitted —
     * never trust client input for cross-clinic access.
     */
    private function resolveClinicId(?int $requested): ?int
    {
        $user = auth()->user();

        if ($user->can('manage clinics')) {
            return ($requested && Clinic::whereKey($requested)->exists()) ? $requested : null;
        }

        return (int) $user->clinic_id;
    }

    /** Clinic list for the picker — only a system admin needs/sees this. */
    private function pickerClinics()
    {
        return auth()->user()->can('manage clinics')
            ? Clinic::orderBy('name')->get(['id', 'name'])
            : collect();
    }

    protected function saveEmail(Request $request, int $clinicId): RedirectResponse
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

        Setting::setForClinic($clinicId, 'mail.mailer', $request->input('mailer', 'smtp'), 'email');
        Setting::setForClinic($clinicId, 'mail.host', $request->input('host'), 'email');
        Setting::setForClinic($clinicId, 'mail.port', $request->input('port'), 'email');
        Setting::setForClinic($clinicId, 'mail.username', $request->input('username'), 'email');
        Setting::setForClinic($clinicId, 'mail.scheme', $request->input('scheme'), 'email');
        Setting::setForClinic($clinicId, 'mail.from_address', $request->input('from_address'), 'email');
        Setting::setForClinic($clinicId, 'mail.from_name', $request->input('from_name'), 'email');
        $this->setSecret($clinicId, 'mail.password', $request->input('password'), 'email');

        return back()->with('success', 'Email (SMTP) settings saved.');
    }

    protected function saveWhatsapp(Request $request, int $clinicId): RedirectResponse
    {
        $request->validate([
            'driver' => ['required', 'in:log,gupshup'],
            'source' => ['nullable', 'string', 'max:40'],
            'app_name' => ['nullable', 'string', 'max:120'],
            'api_key' => ['nullable', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
            'sections' => ['nullable', 'array'],
            'sections.*.event' => ['nullable', Rule::in(WhatsappTemplate::eventKeys())],
            'sections.*.label' => ['nullable', 'string', 'max:120'],
            'sections.*.template_id' => ['nullable', 'string', 'max:255'],
            'sections.*.namespace' => ['nullable', 'string', 'max:255'],
            'sections.*.body' => ['nullable', 'string', 'max:2000'],
            'sections.*.variables' => ['nullable', 'array'],
            'sections.*.variables.*' => ['nullable', Rule::in(WhatsappTemplate::tokenKeys())],
        ]);

        Setting::setForClinic($clinicId, 'messaging.whatsapp_driver', $request->input('driver', 'log'), 'whatsapp');
        Setting::setForClinic($clinicId, 'whatsapp.gupshup_source', $request->input('source'), 'whatsapp');
        Setting::setForClinic($clinicId, 'whatsapp.gupshup_app_name', $request->input('app_name'), 'whatsapp');
        $this->setSecret($clinicId, 'whatsapp.gupshup_api_key', $request->input('api_key'), 'whatsapp');
        $this->setSecret($clinicId, 'whatsapp.gupshup_webhook_secret', $request->input('webhook_secret'), 'whatsapp');

        // Dynamic per-event template sections (add / edit / remove).
        WhatsappTemplate::save($clinicId, $request->input('sections', []));

        return back()->with('success', 'WhatsApp (Gupshup) settings saved.');
    }

    protected function saveSms(Request $request, int $clinicId): RedirectResponse
    {
        $request->validate([
            'driver' => ['required', 'in:log,gupshup'],
            'source' => ['nullable', 'string', 'max:40'],
            'api_key' => ['nullable', 'string', 'max:255'],
            'inbound_webhook_secret' => ['nullable', 'string', 'max:255'],
            'missed_call_webhook_secret' => ['nullable', 'string', 'max:255'],
        ]);

        Setting::setForClinic($clinicId, 'messaging.sms_driver', $request->input('driver', 'log'), 'sms');
        Setting::setForClinic($clinicId, 'sms.gupshup_source', $request->input('source'), 'sms');
        $this->setSecret($clinicId, 'sms.gupshup_api_key', $request->input('api_key'), 'sms');
        $this->setSecret($clinicId, 'sms.inbound_webhook_secret', $request->input('inbound_webhook_secret'), 'sms');
        $this->setSecret($clinicId, 'sms.missed_call_webhook_secret', $request->input('missed_call_webhook_secret'), 'sms');

        return back()->with('success', 'SMS (Gupshup) settings saved.');
    }

    protected function savePayments(Request $request, int $clinicId): RedirectResponse
    {
        $request->validate([
            'driver' => ['required', 'in:manual,razorpay'],
            'razorpay_key_id' => ['nullable', 'string', 'max:255'],
            'razorpay_key_secret' => ['nullable', 'string', 'max:255'],
            'razorpay_webhook_secret' => ['nullable', 'string', 'max:255'],
        ]);

        Setting::setForClinic($clinicId, 'payments.driver', $request->input('driver', 'manual'), 'payments');
        Setting::setForClinic($clinicId, 'payments.razorpay_key_id', $request->input('razorpay_key_id'), 'payments');
        $this->setSecret($clinicId, 'payments.razorpay_key_secret', $request->input('razorpay_key_secret'), 'payments');
        $this->setSecret($clinicId, 'payments.razorpay_webhook_secret', $request->input('razorpay_webhook_secret'), 'payments');

        return back()->with('success', 'Payments (Razorpay) settings saved.');
    }

    protected function saveAi(Request $request, int $clinicId): RedirectResponse
    {
        $request->validate([
            'provider' => ['required', 'in:rule_based,gemini,openai'],
            'gemini_model' => ['nullable', 'string', 'max:120'],
            'gemini_key' => ['nullable', 'string', 'max:255'],
            'openai_model' => ['nullable', 'string', 'max:120'],
            'openai_key' => ['nullable', 'string', 'max:255'],
        ]);

        Setting::setForClinic($clinicId, 'ai.provider', $request->input('provider', 'rule_based'), 'ai');
        Setting::setForClinic($clinicId, 'ai.gemini_model', $request->input('gemini_model'), 'ai');
        Setting::setForClinic($clinicId, 'ai.openai_model', $request->input('openai_model'), 'ai');
        $this->setSecret($clinicId, 'ai.gemini_key', $request->input('gemini_key'), 'ai');
        $this->setSecret($clinicId, 'ai.openai_key', $request->input('openai_key'), 'ai');

        return back()->with('success', 'AI assistant settings saved.');
    }

    /** Only overwrite a secret when a new value was actually entered. */
    protected function setSecret(int $clinicId, string $key, ?string $value, string $group): void
    {
        if ($value !== null && $value !== '') {
            Setting::setForClinic($clinicId, $key, $value, $group, true);
        }
    }

    /** Send a test message — to a typed recipient, or the current user. Always uses the targeted clinic's own credentials. */
    public function test(Request $request, MessageService $messaging, AiService $ai): RedirectResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'in:email,whatsapp,sms,ai'],
            'to' => ['nullable', 'string', 'max:60'],
        ]);
        $channel = $data['channel'];
        $to = $data['to'] ?? null;
        $user = $request->user();
        $clinicId = $this->resolveClinicId((int) $request->input('clinic_id') ?: null);
        if ($clinicId === null) {
            return back()->with('error', 'Select a clinic first.');
        }

        if ($channel === 'ai') {
            $reply = $ai->testConnection($clinicId);

            return back()->with('success', 'AI ('.strtoupper($ai->provider($clinicId)).') replied: "'.$reply.'"');
        }

        if ($channel === 'whatsapp') {
            // WhatsApp business-initiated messages must use an approved template.
            // The test exercises the Appointment reminder template (the primary
            // no-show lever), filling its variables with sample values.
            $section = WhatsappTemplate::forEvent($clinicId, 'appointment');
            if (! $section || ! $section['template_id']) {
                return back()->with('error', 'Set and save the Appointment reminder template ID first — WhatsApp needs an approved template to send.');
            }
            if (! $to && ! $user->phone) {
                return back()->with('error', 'Enter a test WhatsApp number (with country code), or add a phone number to your profile.');
            }

            $params = WhatsappTemplate::resolveParams($section['variables'], WhatsappTemplate::sampleContext());
            $ok = $messaging->sendWhatsappTemplate($user, $section['template_id'], $params, $to, null, null, null, $clinicId);

            if (! $ok) {
                return back()->with('error', 'WhatsApp test failed. Check storage/logs/laravel.log for "[WhatsApp:gupshup:template]" — common causes: API key not saved, template still Pending (not yet Approved), or the number has not opted in.');
            }

            return back()->with('success', 'Test WhatsApp dispatched via Gupshup to '.($to ?: $user->phone).'. Check the device and the log.');
        }

        if ($channel === 'sms') {
            if (! $to && ! $user->phone) {
                return back()->with('error', 'Enter a test SMS number (with country code), or add a phone number to your profile.');
            }

            $ok = $messaging->sendSmsToPhone(
                $to ?: $user->phone,
                'Test SMS — your Smart Appointment Scheduler integration is working.',
                $user,
                null, null, null,
                $clinicId,
            );

            if (! $ok) {
                return back()->with('error', 'SMS test failed. Check storage/logs/laravel.log for "[SMS:gupshup]" — common causes: API key not saved, or the Gupshup sender id is not DLT/approved for the destination country.');
            }

            return back()->with('success', 'Test SMS dispatched via Gupshup to '.($to ?: $user->phone).'. Check the device and the log.');
        }

        // Email
        if (! $to && ! $user->email) {
            return back()->with('error', 'Enter a test email address, or add one to your profile.');
        }

        $recipient = $to
            ? new User(['email' => $to, 'name' => 'Test recipient'])
            : $user;

        $ok = $messaging->send(
            $recipient,
            'Test message — Smart Appointment Scheduler',
            'This is a test email confirming your integration settings are wired up correctly.',
            'email',
            null, null, null,
            $clinicId,
        );

        if (! $ok) {
            return back()->with('error', 'Test email could not be sent — check the SMTP credentials above.');
        }

        return back()->with('success', 'Test email dispatched to '.($to ?: $user->email).'. Check the inbox (or the log if the mailer is set to Log).');
    }
}
