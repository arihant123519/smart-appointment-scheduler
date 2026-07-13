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
 * The Reports page's new conversion-engine block: the appointment status
 * funnel, and WhatsApp flow performance — with "rescued" counting only
 * conversations whose appointment survived (isn't cancelled/no-show).
 */
class ReportFunnelMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_page_exposes_the_funnel_and_flow_metrics(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $clinic = Clinic::create(['slug' => 'c', 'name' => 'Clinic', 'timezone' => 'UTC', 'is_active' => true]);
        $doctor = User::factory()->create(['clinic_id' => $clinic->id]);
        $doctor->syncRoles('provider');
        $provider = Provider::create([
            'user_id' => $doctor->id, 'clinic_id' => $clinic->id,
            'specialty' => 'General', 'default_slot_minutes' => 30, 'is_active' => true,
        ]);
        $patient = User::factory()->create(['clinic_id' => $clinic->id]);
        $patient->syncRoles('patient');
        $admin = User::factory()->create(['clinic_id' => $clinic->id]);
        $admin->syncRoles('clinic_admin');

        // Distinct start times — one provider can't hold three simultaneous
        // appointments, so each status gets its own slot on the same day.
        $slot = 0;
        $make = function (string $status) use (&$slot, $patient, $provider, $clinic) {
            $start = now()->subDays(2)->addMinutes(30 * $slot++);

            return Appointment::create([
                'patient_id' => $patient->id, 'provider_id' => $provider->id, 'clinic_id' => $clinic->id,
                'start_at' => $start, 'end_at' => (clone $start)->addMinutes(30),
                'status' => $status, 'channel' => 'web',
            ]);
        };

        $completed = $make(Appointment::STATUS_COMPLETED);
        $cancelled = $make(Appointment::STATUS_CANCELLED);
        $make(Appointment::STATUS_NO_SHOW);

        $flow = WhatsappFlow::create([
            'clinic_id' => $clinic->id, 'name' => 'F', 'trigger_event' => 'reschedule', 'status' => 'active',
            'graph' => ['start' => '1', 'nodes' => []],
        ]);

        // Rescued: linked to the completed appointment.
        WhatsappConversation::create([
            'whatsapp_flow_id' => $flow->id, 'appointment_id' => $completed->id, 'patient_id' => $patient->id,
            'phone' => '91', 'status' => 'completed', 'outcome' => 'confirmed', 'started_at' => now()->subDay(),
        ]);
        // Lost: linked to the cancelled appointment.
        WhatsappConversation::create([
            'whatsapp_flow_id' => $flow->id, 'appointment_id' => $cancelled->id, 'patient_id' => $patient->id,
            'phone' => '91', 'status' => 'timed_out', 'started_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($admin)->get(route('reports.index'));

        $response->assertOk();
        $funnel = $response->viewData('funnel');
        $this->assertSame(1, $funnel['Completed']);
        $this->assertSame(1, $funnel['Cancelled']);
        $this->assertSame(1, $funnel['No Show']);

        $flowMetrics = $response->viewData('flow');
        $this->assertSame(2, $flowMetrics['started']);
        $this->assertSame(1, $flowMetrics['completed']);
        $this->assertSame(1, $flowMetrics['timed_out']);
        $this->assertSame(1, $flowMetrics['rescued']);
        $this->assertSame(1, $flowMetrics['lost']);
    }
}
