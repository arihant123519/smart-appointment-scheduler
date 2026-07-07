<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Provider;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappFlow;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A WhatsApp conversation that's gone quiet for too long must be marked
 * timed out (not left "active" forever) and the clinic's front desk alerted
 * so a human follows up.
 */
class FlowTimeoutSweepTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_conversation_times_out_and_alerts_the_front_desk(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $clinic = Clinic::create(['slug' => 'c', 'name' => 'Clinic', 'timezone' => 'UTC', 'is_active' => true]);
        $doctor = User::factory()->create(['clinic_id' => $clinic->id]);
        $doctor->syncRoles('provider');
        $provider = Provider::create([
            'user_id' => $doctor->id, 'clinic_id' => $clinic->id,
            'specialty' => 'General', 'default_slot_minutes' => 30, 'is_active' => true,
        ]);
        $patient = User::factory()->create(['clinic_id' => $clinic->id, 'phone' => '919876500000']);
        $patient->syncRoles('patient');
        $frontDesk = User::factory()->create(['clinic_id' => $clinic->id]);
        $frontDesk->syncRoles('front_desk');

        $appointment = Appointment::create([
            'patient_id' => $patient->id, 'provider_id' => $provider->id, 'clinic_id' => $clinic->id,
            'start_at' => now()->addDays(2), 'end_at' => now()->addDays(2)->addMinutes(30),
            'status' => Appointment::STATUS_CONFIRMED, 'channel' => 'web',
        ]);

        $flow = WhatsappFlow::create([
            'clinic_id' => $clinic->id, 'name' => 'Test flow', 'trigger_event' => 'reschedule', 'status' => 'active',
            'graph' => ['start' => '1', 'nodes' => ['1' => ['type' => 'branch_yes_no', 'data' => [], 'next' => ['yes' => '2', 'no' => '2']]]],
        ]);

        $stale = WhatsappConversation::create([
            'whatsapp_flow_id' => $flow->id, 'appointment_id' => $appointment->id, 'patient_id' => $patient->id,
            'phone' => '919876500000', 'current_node_id' => '1', 'context' => [], 'status' => 'active',
            'started_at' => now()->subHours(30), 'last_message_at' => now()->subHours(26),
        ]);

        $fresh = WhatsappConversation::create([
            'whatsapp_flow_id' => $flow->id, 'appointment_id' => $appointment->id, 'patient_id' => $patient->id,
            'phone' => '919876500000', 'current_node_id' => '1', 'context' => [], 'status' => 'active',
            'started_at' => now()->subHour(), 'last_message_at' => now()->subMinutes(10),
        ]);

        $this->artisan('flows:sweep-timeouts')
            ->expectsOutputToContain('Timed out 1 stale WhatsApp conversation(s)')
            ->assertSuccessful();

        $this->assertSame('timed_out', $stale->fresh()->status);
        $this->assertSame('active', $fresh->fresh()->status); // untouched — still within the window
        $this->assertSame(1, $frontDesk->fresh()->unreadNotifications()->count());
    }
}
