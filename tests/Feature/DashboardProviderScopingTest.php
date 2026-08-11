<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Provider;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A provider's dashboard must reflect only their own patients/appointments,
 * not the whole clinic's — the stat blocks previously queried Appointment/
 * User unscoped by provider_id (only the "missed" section was filtered),
 * so a doctor with 1 appointment saw the clinic-wide patient count and
 * no-show rate instead of their own.
 */
class DashboardProviderScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_provider_dashboard_counts_are_scoped_to_their_own_appointments(): void
    {
        $clinic = Clinic::create(['slug' => 'alpha', 'name' => 'Alpha', 'timezone' => 'UTC', 'is_active' => true]);

        $drA = User::factory()->create(['clinic_id' => $clinic->id]);
        $drA->syncRoles('provider');
        $providerA = Provider::create([
            'user_id' => $drA->id, 'clinic_id' => $clinic->id,
            'specialty' => 'General', 'default_slot_minutes' => 30, 'is_active' => true,
        ]);

        $drB = User::factory()->create(['clinic_id' => $clinic->id]);
        $drB->syncRoles('provider');
        $providerB = Provider::create([
            'user_id' => $drB->id, 'clinic_id' => $clinic->id,
            'specialty' => 'General', 'default_slot_minutes' => 30, 'is_active' => true,
        ]);

        $patient1 = User::factory()->create(['clinic_id' => $clinic->id]);
        $patient1->syncRoles('patient');
        $patient2 = User::factory()->create(['clinic_id' => $clinic->id]);
        $patient2->syncRoles('patient');

        // Dr A has 1 appointment; Dr B has 2 — with different patients.
        Appointment::create([
            'patient_id' => $patient1->id, 'provider_id' => $providerA->id, 'clinic_id' => $clinic->id,
            'start_at' => today()->addHours(10), 'end_at' => today()->addHours(10)->addMinutes(30),
            'status' => Appointment::STATUS_BOOKED, 'channel' => 'web',
        ]);
        Appointment::create([
            'patient_id' => $patient2->id, 'provider_id' => $providerB->id, 'clinic_id' => $clinic->id,
            'start_at' => today()->addHours(11), 'end_at' => today()->addHours(11)->addMinutes(30),
            'status' => Appointment::STATUS_BOOKED, 'channel' => 'web',
        ]);
        Appointment::create([
            'patient_id' => $patient1->id, 'provider_id' => $providerB->id, 'clinic_id' => $clinic->id,
            'start_at' => today()->addHours(12), 'end_at' => today()->addHours(12)->addMinutes(30),
            'status' => Appointment::STATUS_BOOKED, 'channel' => 'web',
        ]);

        $response = $this->actingAs($drA)->get(route('dashboard'));

        $response->assertOk();
        $stats = $response->viewData('stats');

        $this->assertSame(1, $stats['today'], "Dr A's today count should only include their own appointment.");
        $this->assertSame(1, $stats['patients'], "Dr A's patient count should only include patients they've seen.");
    }
}
