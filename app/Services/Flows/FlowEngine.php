<?php

namespace App\Services\Flows;

use App\Models\Appointment;
use App\Models\WhatsappConversation;
use App\Models\WhatsappFlow;
use App\Services\AI\AiService;
use App\Services\AppointmentNotificationService;
use App\Services\ClinicDeskNotifier;
use App\Services\Messaging\MessageService;
use App\Services\SchedulingService;
use App\Services\WaitlistService;
use App\Support\PhoneNumber;
use App\Support\ReplyClassifier;
use App\Support\WhatsappTemplate;
use Carbon\Carbon;
use RuntimeException;

/**
 * Executes a WhatsappFlow's normalized graph (see App\Support\FlowGraph)
 * against a live WhatsappConversation. Two entry points:
 *
 *  - start()   — called when a trigger event fires (e.g. a reschedule);
 *                creates the conversation and sends its first message.
 *  - advance() — called by the Gupshup webhook when the patient replies;
 *                evaluates the current resting node against the reply and
 *                steps the conversation forward.
 *
 * `action` nodes reuse the exact same services the rest of the app already
 * uses for confirm/reschedule/cancel — there is no parallel notification path.
 */
class FlowEngine
{
    /** Guards against a cyclic admin-authored graph looping forever. */
    private const MAX_STEPS = 25;

    public function __construct(
        private MessageService $messages,
        private AppointmentNotificationService $notifications,
        private AiService $ai,
    ) {
    }

    public function start(WhatsappFlow $flow, Appointment $appointment): ?WhatsappConversation
    {
        $appointment->loadMissing('patient');
        $patient = $appointment->patient;

        if (! $patient || ! $patient->phone) {
            return null;
        }

        $startNodeId = $flow->graph['start'] ?? null;
        if (! $startNodeId) {
            return null;
        }

        $conversation = WhatsappConversation::create([
            'whatsapp_flow_id' => $flow->id,
            'appointment_id' => $appointment->id,
            'patient_id' => $patient->id,
            'phone' => PhoneNumber::normalize($patient->phone),
            'current_node_id' => $startNodeId,
            'context' => WhatsappTemplate::contextFromAppointment($appointment),
            'status' => 'active',
            'started_at' => now(),
            'last_message_at' => now(),
        ]);

        $this->runSteps($conversation);

        return $conversation->fresh();
    }

    public function advance(WhatsappConversation $conversation, string $inboundText): void
    {
        if ($conversation->status !== 'active') {
            return;
        }

        $node = $this->nodeAt($conversation, $conversation->current_node_id);
        if (! $node) {
            $this->escalate($conversation, 'the conversation was waiting on a step that no longer exists');

            return;
        }

        switch ($node['type']) {
            case 'branch_yes_no':
                $answer = ReplyClassifier::classifyYesNo($inboundText);

                if ($answer === null) {
                    $this->sendUnclearPrompt($conversation, $node);
                    $conversation->update(['last_message_at' => now()]);

                    return;
                }

                $next = $node['next'][$answer] ?? null;
                if (! $next) {
                    $this->escalate($conversation, 'the flow has no "'.$answer.'" branch configured');

                    return;
                }

                $conversation->update(['current_node_id' => $next, 'last_message_at' => now()]);
                break;

            case 'capture_text':
                $variable = $node['data']['variable'] ?? 'captured_text';
                $next = $node['next']['default'] ?? null;

                if (! $next) {
                    $this->escalate($conversation, 'the flow has no next step after capturing a reply');

                    return;
                }

                $context = array_merge($conversation->context ?? [], [$variable => $inboundText]);
                $conversation->update(['context' => $context, 'current_node_id' => $next, 'last_message_at' => now()]);
                break;

            default:
                // Not a resting node type — the conversation shouldn't be
                // parked here. Defensive escalation rather than a silent no-op.
                $this->escalate($conversation, 'the conversation was waiting on an unexpected step');

                return;
        }

        $this->runSteps($conversation);
    }

    /** Mark stale active conversations timed out and alert the front desk. Returns the count affected. */
    public function sweepTimeouts(int $hoursIdle = 24): int
    {
        $stale = WhatsappConversation::active()
            ->where('last_message_at', '<', now()->subHours($hoursIdle))
            ->with('appointment')
            ->get();

        foreach ($stale as $conversation) {
            $conversation->update(['status' => 'timed_out', 'completed_at' => now()]);

            if ($conversation->appointment) {
                app(ClinicDeskNotifier::class)->flowEscalated(
                    $conversation->appointment,
                    'a WhatsApp flow timed out awaiting a reply',
                );
            }
        }

        return $stale->count();
    }

    /**
     * Auto-run consecutive send_message/action nodes until the conversation
     * lands on a resting node (branch_yes_no, capture_text), reaches `end`,
     * or the step budget is exhausted (escalates rather than looping forever).
     */
    private function runSteps(WhatsappConversation $conversation): void
    {
        $steps = 0;

        while ($conversation->status === 'active' && $conversation->current_node_id) {
            if (++$steps > self::MAX_STEPS) {
                $this->escalate($conversation, 'the flow looped too many times without waiting for a reply');

                return;
            }

            $node = $this->nodeAt($conversation, $conversation->current_node_id);
            if (! $node) {
                $this->escalate($conversation, 'the flow referenced a step that no longer exists');

                return;
            }

            if ($node['type'] === 'send_message') {
                $this->sendMessage($conversation, $node);
                $next = $node['next']['default'] ?? null;
                if (! $next) {
                    $this->escalate($conversation, 'the flow has no next step after a message');

                    return;
                }
                $conversation->update(['current_node_id' => $next, 'last_message_at' => now()]);

                continue;
            }

            if ($node['type'] === 'action') {
                $this->runAction($conversation, $node);
                if ($conversation->status !== 'active') {
                    return; // the action escalated or otherwise ended the conversation
                }
                $next = $node['next']['default'] ?? null;
                if (! $next) {
                    $this->escalate($conversation, 'the flow has no next step after an action');

                    return;
                }
                $conversation->update(['current_node_id' => $next]);

                continue;
            }

            if ($node['type'] === 'end') {
                $conversation->update([
                    'status' => 'completed',
                    'outcome' => $node['data']['outcome'] ?? null,
                    'completed_at' => now(),
                ]);

                return;
            }

            if (in_array($node['type'], ['branch_yes_no', 'capture_text'], true)) {
                // A branch/capture step can carry its own question — either an
                // approved template or plain text — so it doesn't need a
                // separate preceding Send Message step for the common case.
                // Sent exactly once, on arrival, before resting.
                $data = $node['data'] ?? [];
                $hasQuestion = trim((string) ($data['question'] ?? '')) !== '';
                $hasTemplate = ($data['mode'] ?? 'text') !== 'text' && ! empty($data['event_key']);

                if ($hasQuestion || $hasTemplate) {
                    $this->sendConfiguredMessage($conversation, $data, 'question');
                    $conversation->update(['last_message_at' => now()]);
                }

                return; // resting node — wait for the next inbound reply
            }

            $this->escalate($conversation, 'the flow contains an unrecognized step type');

            return;
        }
    }

    private function runAction(WhatsappConversation $conversation, array $node): void
    {
        $appointment = $conversation->appointment;
        if (! $appointment) {
            $this->escalate($conversation, 'the linked appointment no longer exists');

            return;
        }

        match ($node['data']['action'] ?? null) {
            'confirm' => $this->actionConfirm($conversation, $appointment),
            'reschedule_to_captured' => $this->actionReschedule($conversation, $appointment, $node),
            'cancel' => $this->actionCancel($conversation, $appointment),
            'escalate' => $this->escalate($conversation, $node['data']['escalate_reason'] ?? 'the flow routes to staff at this step'),
            default => $this->escalate($conversation, 'the flow has an action step with no valid action configured'),
        };
    }

    private function actionConfirm(WhatsappConversation $conversation, Appointment $appointment): void
    {
        $appointment->update(['status' => Appointment::STATUS_CONFIRMED, 'confirmed_at' => now()]);
        $this->notifications->notifyStatusChange($appointment);
        $this->notifications->syncLeadTimes($appointment);
    }

    private function actionReschedule(WhatsappConversation $conversation, Appointment $appointment, array $node): void
    {
        $variable = $node['data']['variable'] ?? null;
        $raw = $variable ? ($conversation->context[$variable] ?? null) : null;

        $parsed = $this->parseRequestedTime($raw);

        if (! $parsed) {
            $this->escalate($conversation, 'the patient\'s requested time ("'.$raw.'") could not be understood');

            return;
        }

        try {
            app(SchedulingService::class)->reschedule($appointment, $parsed);
        } catch (RuntimeException $e) {
            $this->escalate($conversation, 'the requested time is no longer available — '.$e->getMessage());

            return;
        }

        // viaFlow=true: this reschedule was performed BY a flow action, so don't
        // let notifyRescheduled() start another instance of the same flow —
        // that would loop forever (see notifyRescheduled()'s doc comment).
        $this->notifications->notifyRescheduled($appointment, viaFlow: true);
        $this->notifications->syncLeadTimes($appointment);
    }

    /**
     * A direct Carbon parse handles most clearly-formatted replies ("July 10
     * 3pm") without an AI round-trip. For messier phrasing Carbon can't make
     * out ("day after tomorrow evening", "sometime Thursday afternoon"), fall
     * back to AiService::parseDateTime() — itself AI-with-a-rule-based-fallback,
     * so this never depends on the AI provider being configured to function.
     */
    private function parseRequestedTime(?string $raw): ?Carbon
    {
        if (! $raw || trim($raw) === '') {
            return null;
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            // fall through to AI interpretation below
        }

        $interpreted = $this->ai->parseDateTime($raw);
        if (! $interpreted) {
            return null;
        }

        try {
            return Carbon::parse($interpreted);
        } catch (\Throwable) {
            return null;
        }
    }

    private function actionCancel(WhatsappConversation $conversation, Appointment $appointment): void
    {
        $appointment->update([
            'status' => Appointment::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Cancelled via WhatsApp flow',
        ]);
        $this->notifications->notifyStatusChange($appointment);
        $this->notifications->cancelLeadTimes($appointment);
        app(WaitlistService::class)->offerFreedSlot($appointment);
    }

    private function sendMessage(WhatsappConversation $conversation, array $node): void
    {
        $data = $node['data'] ?? [];
        $data += ['mode' => 'template', 'text' => 'We have an update about your appointment.'];

        $this->sendConfiguredMessage($conversation, $data, 'text');
    }

    /**
     * Sends a step's configured message: an approved WhatsApp template when
     * mode != 'text' and one is configured for the picked event, otherwise a
     * plain session-text send (token-substituted). Shared by `send_message`
     * steps and a `branch_yes_no`/`capture_text` step's own optional prompt —
     * both let an admin pick either an approved template or plain text.
     *
     * @param  array<string, mixed>  $data  node data, e.g. {mode, event_key, text|question}
     * @param  string  $textField  which key in $data holds the plain-text fallback ('text' or 'question')
     */
    private function sendConfiguredMessage(WhatsappConversation $conversation, array $data, string $textField): void
    {
        $patient = $conversation->patient;
        if (! $patient) {
            return;
        }

        $eventKey = $data['event_key'] ?? null;
        $mode = $data['mode'] ?? 'text';
        $section = $mode !== 'text' && $eventKey ? WhatsappTemplate::forEvent($eventKey) : null;

        if ($section && $section['template_id']) {
            $params = WhatsappTemplate::resolveParams($section['variables'], $conversation->context ?? []);
            $this->messages->sendWhatsappTemplate(
                $patient, $section['template_id'], $params, null,
                $conversation->appointment, 'flow', $eventKey,
            );

            return;
        }

        // `text` mode, or `template` mode with no approved template configured
        // (session-message fallback — only deliverable within the patient's
        // 24h reply window, consistent with how the rest of the app treats
        // Gupshup template-vs-session rules).
        $text = trim((string) ($data[$textField] ?? ''));
        if ($text === '') {
            return;
        }

        $body = strtr($text, $this->tokenMap($conversation->context ?? []));
        $this->messages->send($patient, 'Appointment update', $body, 'whatsapp', $conversation->appointment, 'flow', $eventKey);
    }

    private function sendUnclearPrompt(WhatsappConversation $conversation, array $node): void
    {
        $message = (string) ($node['data']['unclear_message'] ?? "Sorry, I didn't quite catch that — could you reply yes or no?");
        $this->sendPlainMessage($conversation, $message);
    }

    /** A plain WhatsApp session text send (no template) — used for branch/capture prompts. */
    private function sendPlainMessage(WhatsappConversation $conversation, string $text, ?string $eventKey = null): void
    {
        $patient = $conversation->patient;
        if (! $patient || $text === '') {
            return;
        }

        $body = strtr($text, $this->tokenMap($conversation->context ?? []));
        $this->messages->send($patient, 'Appointment update', $body, 'whatsapp', $conversation->appointment, 'flow', $eventKey);
    }

    private function escalate(WhatsappConversation $conversation, string $reason): void
    {
        $conversation->update(['status' => 'escalated', 'outcome' => 'escalated_to_staff', 'completed_at' => now()]);

        if ($conversation->appointment) {
            app(ClinicDeskNotifier::class)->flowEscalated($conversation->appointment, $reason);
        }
    }

    private function nodeAt(WhatsappConversation $conversation, ?string $nodeId): ?array
    {
        if (! $nodeId) {
            return null;
        }

        return $conversation->flow?->graph['nodes'][$nodeId] ?? null;
    }

    /** @param  array<string, mixed>  $context */
    private function tokenMap(array $context): array
    {
        $map = [];
        foreach ($context as $key => $value) {
            $map['{{'.$key.'}}'] = (string) $value;
        }

        return $map;
    }
}
