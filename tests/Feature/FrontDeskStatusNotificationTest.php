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
 * When any signed-in user changes an appointment's status, the front-desk staff
 * of that appointment's clinic are notified — but not the actor themselves, and
 * not front desk from other clinics.
 */
class FrontDeskStatusNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function clinic(string $slug): Clinic
    {
        return Clinic::create(['slug' => $slug, 'name' => ucfirst($slug), 'timezone' => 'UTC', 'is_active' => true]);
    }

    private function user(int $clinicId, string $role): User
    {
        $u = User::factory()->create(['clinic_id' => $clinicId]);
        $u->syncRoles($role);

        return $u;
    }

    private function appointment(Clinic $clinic, Provider $provider, User $patient): Appointment
    {
        return Appointment::create([
            'patient_id' => $patient->id,
            'provider_id' => $provider->id,
            'clinic_id' => $clinic->id,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addMinutes(30),
            'status' => Appointment::STATUS_BOOKED,
            'channel' => 'web',
        ]);
    }

    public function test_front_desk_of_same_clinic_is_notified_on_status_change(): void
    {
        $clinic = $this->clinic('alpha');
        $doctor = $this->user($clinic->id, 'provider');
        $provider = Provider::create([
            'user_id' => $doctor->id, 'clinic_id' => $clinic->id,
            'specialty' => 'General', 'default_slot_minutes' => 30, 'is_active' => true,
        ]);
        $patient = $this->user($clinic->id, 'patient');
        $frontDesk = $this->user($clinic->id, 'front_desk');
        $otherClinicDesk = $this->user($this->clinic('beta')->id, 'front_desk');

        $appointment = $this->appointment($clinic, $provider, $patient);

        // A clinic admin changes the status.
        $admin = $this->user($clinic->id, 'clinic_admin');
        $this->actingAs($admin);
        $appointment->update(['status' => Appointment::STATUS_CONFIRMED]);

        $this->assertSame(1, $frontDesk->fresh()->unreadNotifications()->count());
        $this->assertSame(0, $otherClinicDesk->fresh()->unreadNotifications()->count());
    }

    public function test_actor_is_not_notified_of_their_own_change(): void
    {
        $clinic = $this->clinic('alpha');
        $doctor = $this->user($clinic->id, 'provider');
        $provider = Provider::create([
            'user_id' => $doctor->id, 'clinic_id' => $clinic->id,
            'specialty' => 'General', 'default_slot_minutes' => 30, 'is_active' => true,
        ]);
        $patient = $this->user($clinic->id, 'patient');
        $frontDesk = $this->user($clinic->id, 'front_desk');

        $appointment = $this->appointment($clinic, $provider, $patient);

        // The front desk user makes the change themselves.
        $this->actingAs($frontDesk);
        $appointment->update(['status' => Appointment::STATUS_CHECKED_IN]);

        $this->assertSame(0, $frontDesk->fresh()->unreadNotifications()->count());
    }

    public function test_system_settle_does_not_notify(): void
    {
        $clinic = $this->clinic('alpha');
        $doctor = $this->user($clinic->id, 'provider');
        $provider = Provider::create([
            'user_id' => $doctor->id, 'clinic_id' => $clinic->id,
            'specialty' => 'General', 'default_slot_minutes' => 30, 'is_active' => true,
        ]);
        $patient = $this->user($clinic->id, 'patient');
        $frontDesk = $this->user($clinic->id, 'front_desk');

        // A past, unconfirmed appointment that the settle job will mark no-show.
        Appointment::create([
            'patient_id' => $patient->id,
            'provider_id' => $provider->id,
            'clinic_id' => $clinic->id,
            'start_at' => now()->subDay(),
            'end_at' => now()->subDay()->addMinutes(30),
            'status' => Appointment::STATUS_BOOKED,
            'channel' => 'web',
        ]);

        Appointment::settleOverdue();

        // No auth user / bulk update → no front-desk notifications.
        $this->assertSame(0, $frontDesk->fresh()->unreadNotifications()->count());
    }
}
