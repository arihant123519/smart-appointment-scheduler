<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\Setting;

/**
 * Dynamic registry of WhatsApp message templates.
 *
 * Each "section" maps an app event (appointment reminder, confirmation,
 * waitlist offer, broadcast) to an approved Gupshup template, and records the
 * ordered list of merge tokens that fill the template's {{1}}, {{2}}, …
 * placeholders. Admins add / edit / remove sections from
 * Settings → Integrations, so template wording and variable count can change
 * without code edits — all in service of the product's core goal: cutting
 * no-shows.
 *
 * Sections are stored as a single JSON blob in the settings store under
 * {@see self::SETTING_KEY}. The send services ({@see \App\Services\ReminderService},
 * {@see \App\Services\WaitlistService}, {@see \App\Http\Controllers\AnnouncementController})
 * resolve the active template for their event via {@see self::forEvent()} and
 * build the params with {@see self::resolveParams()}.
 */
class WhatsappTemplate
{
    public const SETTING_KEY = 'whatsapp.templates';

    /**
     * App events a template section can be wired to. The key is stored; the
     * label is shown in the UI. Adding a new event requires a matching send
     * path in code, so this list is intentionally code-defined.
     *
     * @var array<string, string>
     */
    public const EVENTS = [
        'appointment' => 'Appointment reminder',
        'confirmation' => 'Appointment confirmation',
        'status_update' => 'Status change update',
        'reschedule' => 'Appointment rescheduled',
        'missed' => 'Missed appointment',
        'waitlist' => 'Waitlist — slot opened',
        'broadcast' => 'Broadcast / announcement',
    ];

    /**
     * Merge tokens available to fill template variables. The key is stored
     * against each {{n}}; the label is shown in the variable picker.
     *
     * @var array<string, string>
     */
    public const TOKENS = [
        'patient_name' => 'Patient name',
        'provider_name' => 'Provider name',
        'clinic_name' => 'Clinic / location name',
        'service_name' => 'Service name',
        'date' => 'Date (e.g. 20 Jun 2026)',
        'time' => 'Time (e.g. 11:00 AM)',
        'day_label' => 'Day (Today / weekday)',
        'status' => 'Appointment status (e.g. Confirmed)',
        'message' => 'Message body (broadcast)',
    ];

    /** Event other events fall back to when they have no template of their own. */
    public const FALLBACK_EVENT = 'appointment';

    // --- Metadata accessors ------------------------------------------------

    /** @return array<string, string> */
    public static function events(): array
    {
        return self::EVENTS;
    }

    /** @return array<int, string> */
    public static function eventKeys(): array
    {
        return array_keys(self::EVENTS);
    }

    /** @return array<string, string> */
    public static function tokens(): array
    {
        return self::TOKENS;
    }

    /** @return array<int, string> */
    public static function tokenKeys(): array
    {
        return array_keys(self::TOKENS);
    }

    public static function eventLabel(string $event): string
    {
        return self::EVENTS[$event] ?? $event;
    }

    // --- Persistence -------------------------------------------------------

    /**
     * All configured sections (saved set, or the seeded defaults the first time).
     *
     * @return array<int, array{event: string, label: string, template_id: ?string, namespace: ?string, body: string, variables: array<int, string>}>
     */
    public static function sections(): array
    {
        $raw = Setting::get(self::SETTING_KEY);

        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(array_map([self::class, 'normalize'], $decoded));
            }
        }

        return self::defaults();
    }

    /**
     * Sections for the admin editor: the saved set, plus a seeded default for
     * any event that has no section yet. This guarantees newly-added events
     * (e.g. reschedule) appear in the UI even when the admin saved their
     * templates before those events existed.
     *
     * @return array<int, array{event: string, label: string, template_id: ?string, namespace: ?string, body: string, variables: array<int, string>}>
     */
    public static function sectionsForEditing(): array
    {
        $sections = self::sections();
        $present = array_column($sections, 'event');
        $defaults = [];
        foreach (self::defaults() as $d) {
            $defaults[$d['event']] = $d;
        }

        foreach (self::eventKeys() as $event) {
            if (! in_array($event, $present, true)) {
                $sections[] = self::normalize($defaults[$event] ?? ['event' => $event, 'label' => self::eventLabel($event)]);
            }
        }

        return $sections;
    }

    /**
     * Persist the full set of sections (replacing whatever was there).
     *
     * @param  array<int, array<string, mixed>>  $sections
     */
    public static function save(array $sections): void
    {
        $clean = [];
        foreach ($sections as $section) {
            $section = self::normalize($section);

            // Drop rows that carry nothing useful (no template id and no label).
            if (($section['template_id'] === null || $section['template_id'] === '')
                && $section['label'] === '') {
                continue;
            }

            $clean[] = $section;
        }

        Setting::set(self::SETTING_KEY, json_encode(array_values($clean)), 'whatsapp');
    }

    /**
     * Update (or insert) the template section for a single event, leaving every
     * other event's configuration untouched. Lets a focused screen (e.g. the
     * Broadcast page) manage just its own template.
     *
     * @param  array<string, mixed>  $data  template_id / namespace / body / variables
     */
    public static function saveSection(string $event, array $data): void
    {
        $sections = self::sectionsForEditing();
        $data['event'] = $event;
        $replaced = false;

        foreach ($sections as $i => $existing) {
            if ($existing['event'] === $event && ! $replaced) {
                // Preserve the existing label unless one was supplied.
                $data['label'] = $data['label'] ?? $existing['label'];
                $sections[$i] = self::normalize($data);
                $replaced = true;
            }
        }

        if (! $replaced) {
            $sections[] = self::normalize($data);
        }

        self::save($sections);
    }

    /**
     * Coerce a raw (request/JSON) section into the canonical shape.
     *
     * @param  array<string, mixed>  $section
     * @return array{event: string, label: string, template_id: ?string, namespace: ?string, body: string, variables: array<int, string>}
     */
    public static function normalize(array $section): array
    {
        $event = (string) ($section['event'] ?? self::FALLBACK_EVENT);
        if (! isset(self::EVENTS[$event])) {
            $event = self::FALLBACK_EVENT;
        }

        $variables = [];
        foreach ((array) ($section['variables'] ?? []) as $token) {
            if (isset(self::TOKENS[$token])) {
                $variables[] = $token;
            }
        }

        $id = trim((string) ($section['template_id'] ?? ''));
        $ns = trim((string) ($section['namespace'] ?? ''));

        return [
            'event' => $event,
            'label' => trim((string) ($section['label'] ?? '')),
            'template_id' => $id === '' ? null : $id,
            'namespace' => $ns === '' ? null : $ns,
            'body' => (string) ($section['body'] ?? ''),
            'variables' => $variables,
        ];
    }

    // --- Runtime resolution ------------------------------------------------

    /**
     * The active template section for an event: the first one with a non-empty
     * template id. Other events fall back to the appointment template.
     *
     * @return array{event: string, label: string, template_id: ?string, namespace: ?string, body: string, variables: array<int, string>}|null
     */
    public static function forEvent(string $event, bool $fallback = true): ?array
    {
        $sections = self::sections();

        foreach ($sections as $section) {
            if ($section['event'] === $event && $section['template_id']) {
                return $section;
            }
        }

        if ($fallback && $event !== self::FALLBACK_EVENT) {
            return self::forEvent(self::FALLBACK_EVENT, false);
        }

        return null;
    }

    /**
     * Map an ordered list of variable tokens to their values, in template order.
     *
     * @param  array<int, string>  $variables  token keys, in {{1}},{{2}},… order
     * @param  array<string, string>  $context  token key => value
     * @return array<int, string>
     */
    public static function resolveParams(array $variables, array $context): array
    {
        return array_map(fn ($token) => (string) ($context[$token] ?? ''), $variables);
    }

    /**
     * Render a section's body by substituting {{1}}, {{2}}, … with the resolved
     * params. Used to store a human-readable copy of what was sent.
     *
     * @param  array{body?: string}  $section
     * @param  array<int, string>  $params
     */
    public static function renderBody(array $section, array $params): string
    {
        $body = $section['body'] ?? '';

        foreach ($params as $i => $value) {
            $body = str_replace('{{'.($i + 1).'}}', $value, $body);
        }

        return $body;
    }

    /**
     * Build the token => value map for an appointment-based event
     * (appointment / confirmation / waitlist).
     *
     * @return array<string, string>
     */
    public static function contextFromAppointment(Appointment $appointment): array
    {
        $start = $appointment->start_at;

        return [
            'patient_name' => (string) ($appointment->patient->name ?? ''),
            'provider_name' => (string) ($appointment->provider->name ?? ''),
            'clinic_name' => (string) ($appointment->clinic->name ?? ''),
            'service_name' => (string) ($appointment->service->name ?? ''),
            'date' => $start ? $start->format('d M Y') : '',
            'time' => $start ? $start->format('g:i A') : '',
            'day_label' => $start ? ($start->isToday() ? 'Today' : $start->format('l, M j')) : '',
            'status' => (string) ($appointment->status_label ?? $appointment->status ?? ''),
        ];
    }

    /**
     * A realistic sample value for each token — used by the test send and the
     * UI preview so admins can see the message take shape.
     *
     * @return array<string, string>
     */
    public static function sampleContext(): array
    {
        return [
            'patient_name' => 'Rita Sharma',
            'provider_name' => 'Shruti Handa',
            'clinic_name' => 'Handa Aesthetics',
            'service_name' => 'Consultation',
            'date' => '20 Jun 2026',
            'time' => '11:00 AM',
            'day_label' => 'Today',
            'status' => 'Confirmed',
            'message' => 'This is a test broadcast message.',
        ];
    }

    // --- Defaults ----------------------------------------------------------

    /**
     * Seed sections used until the admin saves their own. The appointment
     * template reuses any legacy env/DB template id so existing setups keep
     * working.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function defaults(): array
    {
        $legacyId = config('services.whatsapp.gupshup_template_id') ?: null;
        $legacyNs = config('services.whatsapp.gupshup_namespace') ?: null;

        return [
            [
                'event' => 'appointment',
                'label' => 'Appointment reminder',
                'template_id' => $legacyId,
                'namespace' => $legacyNs,
                'body' => 'Hi {{1}}, your appointment at {{2}} with Dr. {{3}} is booked for {{4}} at {{5}}. We look forward to seeing you. Reply STOP to opt out.',
                'variables' => ['patient_name', 'clinic_name', 'provider_name', 'date', 'time'],
            ],
            [
                'event' => 'confirmation',
                'label' => 'Appointment confirmation',
                'template_id' => null,
                'namespace' => null,
                'body' => 'Hi {{1}}, please confirm your appointment at {{2}} with Dr. {{3}} on {{4}} at {{5}}.',
                'variables' => ['patient_name', 'clinic_name', 'provider_name', 'date', 'time'],
            ],
            [
                'event' => 'status_update',
                'label' => 'Status change update',
                'template_id' => null,
                'namespace' => null,
                'body' => 'Hi {{1}}, your appointment with Dr. {{2}} on {{3}} at {{4}} is now {{5}}.',
                'variables' => ['patient_name', 'provider_name', 'date', 'time', 'status'],
            ],
            [
                'event' => 'reschedule',
                'label' => 'Appointment rescheduled',
                'template_id' => null,
                'namespace' => null,
                'body' => 'Hi {{1}}, your appointment at {{2}} has been rescheduled. New date: {{3}} at {{4}} with Dr. {{5}}. Thank you for your understanding.',
                'variables' => ['patient_name', 'clinic_name', 'date', 'time', 'provider_name'],
            ],
            [
                'event' => 'missed',
                'label' => 'Missed appointment',
                'template_id' => null,
                'namespace' => null,
                'body' => 'Hi {{1}}, seems like you didn\'t come in the appointed time on {{2}} at {{3}} with Dr. {{4}}. Please contact us to reschedule.',
                'variables' => ['patient_name', 'date', 'time', 'provider_name'],
            ],
            [
                'event' => 'waitlist',
                'label' => 'Waitlist — slot opened',
                'template_id' => null,
                'namespace' => null,
                'body' => 'Hi {{1}}, a sooner slot just opened at {{2}} with Dr. {{3}} on {{4}} at {{5}}. Log in to book it.',
                'variables' => ['patient_name', 'clinic_name', 'provider_name', 'date', 'time'],
            ],
            [
                'event' => 'broadcast',
                'label' => 'Broadcast / announcement',
                'template_id' => null,
                'namespace' => null,
                'body' => '{{1}}',
                'variables' => ['message'],
            ],
        ];
    }
}
