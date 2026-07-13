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
 * Past appointments that were never completed must auto-settle:
 * booked/confirmed → no-show, checked-in → completed. Completed, cancelled and
 * future appointments are left untouched.
 */
class AppointmentSettleTest extends TestCase
{
    use RefreshDatabase;

    private int $clinicId;

    private int $providerId;

    private int $patientId;

    // One provider can't hold two simultaneous appointments — each call to
    // appt() gets its own slot, staggered by this counter.
    private int $slot = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        $clinic = Clinic::create(['slug' => 'c', 'name' => 'C', 'timezone' => 'UTC', 'is_active' => true]);
        $doc = User::factory()->create(['clinic_id' => $clinic->id]);
        $doc->syncRoles('provider');
        $provider = Provider::create([
            'user_id' => $doc->id, 'clinic_id' => $clinic->id,
            'specialty' => 'General', 'default_slot_minutes' => 30, 'is_active' => true,
        ]);
        $patient = User::factory()->create(['clinic_id' => $clinic->id]);
        $patient->syncRoles('patient');

        $this->clinicId = $clinic->id;
        $this->providerId = $provider->id;
        $this->patientId = $patient->id;
    }

    private function appt(string $status, string $when): Appointment
    {
        $start = $when === 'past' ? now()->subDays(2) : now()->addDays(2);
        $start = $start->addMinutes(30 * $this->slot++);

        return Appointment::create([
            'patient_id' => $this->patientId,
            'provider_id' => $this->providerId,
            'clinic_id' => $this->clinicId,
            'start_at' => $start,
            'end_at' => (clone $start)->addMinutes(30),
            'status' => $status,
            'channel' => 'web',
        ]);
    }

    public function test_it_settles_overdue_statuses_correctly(): void
    {
        $pastBooked = $this->appt(Appointment::STATUS_BOOKED, 'past');
        $pastConfirmed = $this->appt(Appointment::STATUS_CONFIRMED, 'past');
        $pastCheckedIn = $this->appt(Appointment::STATUS_CHECKED_IN, 'past');
        $pastCompleted = $this->appt(Appointment::STATUS_COMPLETED, 'past');
        $pastCancelled = $this->appt(Appointment::STATUS_CANCELLED, 'past');
        $futureBooked = $this->appt(Appointment::STATUS_BOOKED, 'future');

        $result = Appointment::settleOverdue();

        $this->assertSame(2, $result['missed']);
        $this->assertSame(1, $result['completed']);

        $this->assertSame(Appointment::STATUS_NO_SHOW, $pastBooked->fresh()->status);
        $this->assertSame(Appointment::STATUS_NO_SHOW, $pastConfirmed->fresh()->status);
        $this->assertSame(Appointment::STATUS_COMPLETED, $pastCheckedIn->fresh()->status);
        // Unchanged:
        $this->assertSame(Appointment::STATUS_COMPLETED, $pastCompleted->fresh()->status);
        $this->assertSame(Appointment::STATUS_CANCELLED, $pastCancelled->fresh()->status);
        $this->assertSame(Appointment::STATUS_BOOKED, $futureBooked->fresh()->status);
    }

    public function test_settle_command_runs(): void
    {
        $this->appt(Appointment::STATUS_BOOKED, 'past');

        $this->artisan('appointments:settle')
            ->expectsOutputToContain('Marked 1 appointment(s) as no-show')
            ->assertSuccessful();
    }
}
