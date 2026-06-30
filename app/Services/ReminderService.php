<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Reminder;
use App\Services\AI\AiService;
use App\Services\Messaging\MessageService;
use Illuminate\Support\Str;

/**
 * Builds and dispatches the no-show reminder cadence described in PRD §6.
 *
 * Default cadence (configurable): at booking → 72h → 24h → 2h before.
 * High-risk appointments (no_show_score >= 70) add an extra 48h reminder.
 * Every reminder carries one-tap Confirm / Reschedule / Cancel links.
 */
class ReminderService
{
    public function __construct(private MessageService $messages, private ?AiService $ai = null)
    {
        $this->ai ??= app(AiService::class);
    }

    /** Cadence in hours-before-start. */
    public const CADENCE = [72, 24, 2];
    public const HIGH_RISK_EXTRA = [48];

    public function scheduleFor(Appointment $appointment): void
    {
        $appointment->loadMissing('patient', 'service', 'provider.user');

        // Immediate confirmation request at booking time.
        $this->make($appointment, 'confirmation', now());

        $offsets = self::CADENCE;
        if ($appointment->no_show_score >= 70) {
            $offsets = array_merge($offsets, self::HIGH_RISK_EXTRA);
        }

        foreach ($offsets as $hours) {
            $when = $appointment->start_at->copy()->subHours($hours);
            if ($when->isFuture()) {
                $this->make($appointment, 'reminder', $when);
            }
        }
    }

    private function make(Appointment $appointment, string $type, $when): Reminder
    {
        return Reminder::create([
            'appointment_id' => $appointment->id,
            'channel' => $appointment->patient->preferred_channel ?? 'email',
            'type' => $type,
            'template' => $type,
            'scheduled_at' => $when,
            'status' => 'scheduled',
            'token' => Str::random(48),
        ]);
    }

    /** Send everything that is due. Returns the count dispatched. */
    public function dispatchDue(): int
    {
        $count = 0;

        Reminder::due()->with('appointment.patient', 'appointment.provider.user', 'appointment.service')
            ->get()
            ->each(function (Reminder $reminder) use (&$count) {
                $appointment = $reminder->appointment;

                // Skip if the appointment is gone or already resolved.
                if (! $appointment || in_array($appointment->status, [
                    Appointment::STATUS_CANCELLED, Appointment::STATUS_NO_SHOW, Appointment::STATUS_COMPLETED,
                ], true)) {
                    $reminder->update(['status' => 'cancelled']);

                    return;
                }

                $subject = $reminder->type === 'confirmation'
                    ? 'Please confirm your appointment'
                    : 'Appointment reminder';

                // `reminders:send` is an EMAIL-only channel: the legacy reminder
                // cadence always goes out by email. WhatsApp (and configurable
                // lead-time / status notifications) is handled separately by the
                // Appointment Notifications system (`appointments:notify`).
                $body = $this->compose($reminder);
                $ok = $this->messages->send($appointment->patient, $subject, $body, 'email');

                $reminder->update([
                    'body' => $body,
                    'status' => $ok ? 'sent' : 'failed',
                    'sent_at' => now(),
                ]);

                if ($reminder->type === 'confirmation') {
                    $appointment->update(['confirmation_requested' => true]);
                }

                $count++;
            });

        return $count;
    }

    private function compose(Reminder $reminder): string
    {
        $a = $reminder->appointment;
        $base = url('/r/'.$reminder->token);

        // Personalized, tone-appropriate intro line (PRD §5.2 "Smart reminder
        // generation"). Falls back to a fixed greeting when AI is unavailable.
        $intro = $this->ai->generateReminderMessage([
            'patient' => $a->patient->name,
            'service' => $a->service?->name ?? 'your appointment',
            'provider' => $a->provider->name,
            'when' => $a->start_at->format('l, F j \a\t g:i A'),
            'type' => $reminder->type,
            'locale' => $a->patient->locale ?? 'en',
        ]);

        // The action links are always appended verbatim — never AI-generated.
        return implode("\n", [
            $intro,
            '',
            'Please choose one:',
            "  Confirm:    {$base}/confirm",
            "  Reschedule: {$base}/reschedule",
            "  Cancel:     {$base}/cancel",
            '',
            'Thank you.',
        ]);
    }
}
