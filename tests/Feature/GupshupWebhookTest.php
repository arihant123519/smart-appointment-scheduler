<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\MessageLog;
use App\Models\Provider;
use App\Models\Setting;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappFlow;
use App\Services\AppointmentNotificationService;
use App\Support\WhatsappTemplate;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The full conversion-engine round trip: a reschedule starts an active
 * WhatsApp flow, a "no" reply asks for a preferred time, a follow-up reply
 * with a parseable time actually reschedules the appointment — and the
 * existing dual-channel notifyRescheduled() path fires exactly once, with no
 * parallel notification path and no runaway flow loop.
 */
class GupshupWebhookTest extends TestCase
{
    use RefreshDatabase;

    private Appointment $appointment;

    private string $patientPhone = '919876543210';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $clinic = Clinic::create(['slug' => 'c', 'name' => 'Clinic', 'timezone' => 'UTC', 'is_active' => true]);
        Setting::setForClinic($clinic->id, 'whatsapp.gupshup_webhook_secret', 'test-secret', 'whatsapp', true);
        $doctor = User::factory()->create(['clinic_id' => $clinic->id]);
        $doctor->syncRoles('provider');
        $provider = Provider::create([
            'user_id' => $doctor->id, 'clinic_id' => $clinic->id,
            'specialty' => 'General', 'default_slot_minutes' => 30, 'is_active' => true,
        ]);
        $patient = User::factory()->create(['clinic_id' => $clinic->id, 'phone' => $this->patientPhone]);
        $patient->syncRoles('patient');

        $this->appointment = Appointment::create([
            'patient_id' => $patient->id,
            'provider_id' => $provider->id,
            'clinic_id' => $clinic->id,
            'start_at' => now()->addDays(3)->setTime(10, 0),
            'end_at' => now()->addDays(3)->setTime(10, 30),
            'status' => Appointment::STATUS_CONFIRMED,
            'channel' => 'web',
        ]);

        WhatsappFlow::create([
            'clinic_id' => $clinic->id,
            'name' => 'Reschedule confirm',
            'trigger_event' => 'reschedule',
            'status' => 'active',
            'graph' => [
                'start' => '1',
                'nodes' => [
                    '1' => ['type' => 'send_message', 'data' => ['mode' => 'text', 'text' => 'Does the new time work?'], 'next' => ['default' => '2']],
                    '2' => ['type' => 'branch_yes_no', 'data' => ['unclear_message' => 'Please reply yes or no.'], 'next' => ['yes' => '3', 'no' => '4']],
                    '3' => ['type' => 'action', 'data' => ['action' => 'confirm'], 'next' => ['default' => '5']],
                    '4' => ['type' => 'send_message', 'data' => ['mode' => 'text', 'text' => 'What time works for you?'], 'next' => ['default' => '6']],
                    '5' => ['type' => 'end', 'data' => ['outcome' => 'confirmed'], 'next' => []],
                    '6' => ['type' => 'capture_text', 'data' => ['variable' => 'requested_time'], 'next' => ['default' => '7']],
                    '7' => ['type' => 'action', 'data' => ['action' => 'reschedule_to_captured', 'variable' => 'requested_time'], 'next' => ['default' => '8']],
                    '8' => ['type' => 'end', 'data' => ['outcome' => 'rescheduled'], 'next' => []],
                ],
            ],
        ]);
    }

    private function webhookInbound(string $text): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('webhooks.gupshup', ['token' => 'test-secret']), [
            'type' => 'message',
            'payload' => [
                'id' => 'wa-msg-'.uniqid(),
                'sender' => ['phone' => $this->patientPhone],
                'payload' => ['text' => $text],
            ],
        ]);
    }

    public function test_webhook_rejects_a_wrong_token(): void
    {
        $this->postJson(route('webhooks.gupshup', ['token' => 'wrong']), ['type' => 'message'])
            ->assertForbidden();
    }

    public function test_full_reschedule_conversation_round_trip(): void
    {
        // A WhatsApp template for the (non-flow) reschedule fallback path, so
        // the final notifyRescheduled() dual-channel send is fully exercised.
        WhatsappTemplate::saveSection($this->appointment->clinic_id, 'reschedule', [
            'template_id' => 'wa-resched-tmpl',
            'variables' => ['patient_name'],
        ]);

        // --- Step 1: an external reschedule notification starts the flow ---
        app(AppointmentNotificationService::class)->notifyRescheduled($this->appointment);

        $this->assertSame(1, WhatsappConversation::count());
        $conversation = WhatsappConversation::first();
        $this->assertSame('active', $conversation->status);
        $this->assertSame('2', $conversation->current_node_id); // parked at the yes/no branch

        // The flow's first message (whatsapp) + the unconditional reschedule email both fired.
        $this->assertDatabaseHas('message_logs', ['channel' => 'whatsapp', 'source' => 'flow', 'appointment_id' => $this->appointment->id]);
        $this->assertDatabaseHas('message_logs', ['channel' => 'email', 'source' => 'appointment_notification', 'event_key' => 'reschedule']);

        // --- Step 2: patient replies "no" — asked for a preferred time ---
        $this->webhookInbound("no, can't make it")
            ->assertOk()
            ->assertJson(['ok' => true, 'matched_conversation' => true]);

        $conversation->refresh();
        $this->assertSame('6', $conversation->current_node_id); // parked at capture_text
        $this->assertDatabaseHas('message_logs', ['direction' => 'inbound', 'channel' => 'whatsapp', 'body' => "no, can't make it"]);

        // --- Step 3: patient replies with a parseable time — reschedules for real ---
        $newTime = now()->addDays(5)->setTime(14, 0)->format('Y-m-d H:i:s');
        $this->webhookInbound($newTime)->assertOk();

        $conversation->refresh();
        $this->appointment->refresh();

        $this->assertSame('completed', $conversation->status);
        $this->assertSame('rescheduled', $conversation->outcome);
        $this->assertSame($newTime, $this->appointment->start_at->format('Y-m-d H:i:s'));
        $this->assertSame(Appointment::STATUS_BOOKED, $this->appointment->status);

        // The reschedule action's own notifyRescheduled(viaFlow: true) call fired
        // both channels via the ordinary (non-flow) path…
        $this->assertDatabaseHas('message_logs', [
            'channel' => 'email', 'source' => 'appointment_notification', 'event_key' => 'reschedule',
        ]);
        $this->assertDatabaseHas('message_logs', [
            'channel' => 'whatsapp', 'source' => 'appointment_notification', 'event_key' => 'reschedule', 'template_id' => 'wa-resched-tmpl',
        ]);

        // …and crucially did NOT start a second instance of the same flow
        // (viaFlow guards against the reschedule-triggers-itself loop).
        $this->assertSame(1, WhatsappConversation::count());
    }

    public function test_unclear_reply_reprompts_without_advancing(): void
    {
        app(AppointmentNotificationService::class)->notifyRescheduled($this->appointment);
        $conversation = WhatsappConversation::first();

        $this->webhookInbound('maybe next week sometime?')->assertOk();

        $conversation->refresh();
        $this->assertSame('active', $conversation->status);
        $this->assertSame('2', $conversation->current_node_id); // unchanged — still waiting at the branch
    }

    public function test_yes_reply_confirms_the_appointment(): void
    {
        app(AppointmentNotificationService::class)->notifyRescheduled($this->appointment);

        $this->webhookInbound('yes that works')->assertOk();

        $this->appointment->refresh();
        $conversation = WhatsappConversation::first()->refresh();

        $this->assertSame(Appointment::STATUS_CONFIRMED, $this->appointment->status);
        $this->assertSame('completed', $conversation->status);
        $this->assertSame('confirmed', $conversation->outcome);
    }

    public function test_a_branch_nodes_own_question_is_sent_when_the_flow_reaches_it(): void
    {
        // A single-node flow: the branch step asks its own question directly
        // (no separate preceding "send message" step needed).
        $flow = WhatsappFlow::create([
            'clinic_id' => $this->appointment->clinic_id,
            'name' => 'Self-contained branch',
            'trigger_event' => 'confirmation',
            'status' => 'active',
            'graph' => [
                'start' => '1',
                'nodes' => [
                    '1' => ['type' => 'branch_yes_no', 'data' => ['question' => 'Does 10am still work for you?'], 'next' => ['yes' => '2', 'no' => '2']],
                    '2' => ['type' => 'end', 'data' => ['outcome' => 'done'], 'next' => []],
                ],
            ],
        ]);

        app(\App\Services\Flows\FlowEngine::class)->start($flow, $this->appointment);

        $this->assertDatabaseHas('message_logs', [
            'channel' => 'whatsapp', 'source' => 'flow', 'body' => 'Does 10am still work for you?',
        ]);
    }

    public function test_a_branch_nodes_own_question_can_use_an_approved_template_instead_of_plain_text(): void
    {
        WhatsappTemplate::saveSection($this->appointment->clinic_id, 'confirmation', [
            'template_id' => 'wa-confirm-tmpl',
            'variables' => ['patient_name'],
        ]);

        $flow = WhatsappFlow::create([
            'clinic_id' => $this->appointment->clinic_id,
            'name' => 'Template-backed branch',
            'trigger_event' => 'confirmation',
            'status' => 'active',
            'graph' => [
                'start' => '1',
                'nodes' => [
                    '1' => ['type' => 'branch_yes_no', 'data' => ['mode' => 'template', 'event_key' => 'confirmation', 'question' => 'unused plain fallback'], 'next' => ['yes' => '2', 'no' => '2']],
                    '2' => ['type' => 'end', 'data' => ['outcome' => 'done'], 'next' => []],
                ],
            ],
        ]);

        app(\App\Services\Flows\FlowEngine::class)->start($flow, $this->appointment);

        // The approved template fired (not the plain-text "question" fallback).
        $this->assertDatabaseHas('message_logs', [
            'channel' => 'whatsapp', 'source' => 'flow', 'event_key' => 'confirmation', 'template_id' => 'wa-confirm-tmpl',
        ]);
        $this->assertDatabaseMissing('message_logs', ['body' => 'unused plain fallback']);
    }

    public function test_unparseable_requested_time_escalates_to_staff_instead_of_guessing(): void
    {
        $frontDesk = User::factory()->create(['clinic_id' => $this->appointment->clinic_id]);
        $frontDesk->syncRoles('front_desk');

        app(AppointmentNotificationService::class)->notifyRescheduled($this->appointment);
        $this->webhookInbound('no')->assertOk();
        $this->webhookInbound('zzqxflibbertigibbet not a real time at all')->assertOk();

        $conversation = WhatsappConversation::first()->refresh();
        $this->assertSame('escalated', $conversation->status);
        $this->assertSame(1, $frontDesk->fresh()->unreadNotifications()->count());

        // The appointment must NOT have been rescheduled to a guessed time.
        $this->appointment->refresh();
        $this->assertSame(Appointment::STATUS_CONFIRMED, $this->appointment->status);
    }
}
