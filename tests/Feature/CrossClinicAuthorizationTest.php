<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Payment;
use App\Models\Provider;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the operational-audit findings: staff from one
 * clinic must not be able to reach another clinic's appointments/patients/
 * providers by guessing an id, a clinic_admin must not be able to grant
 * themselves system_admin, and a payment refund must be state/clinic-guarded.
 */
class CrossClinicAuthorizationTest extends TestCase
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

    private function provider(Clinic $clinic): Provider
    {
        $doctor = $this->user($clinic->id, 'provider');

        return Provider::create([
            'user_id' => $doctor->id, 'clinic_id' => $clinic->id,
            'specialty' => 'General', 'default_slot_minutes' => 30, 'is_active' => true,
        ]);
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

    public function test_front_desk_cannot_view_another_clinics_appointment(): void
    {
        $clinicA = $this->clinic('alpha');
        $clinicB = $this->clinic('beta');
        $appointment = $this->appointment($clinicA, $this->provider($clinicA), $this->user($clinicA->id, 'patient'));
        $foreignFrontDesk = $this->user($clinicB->id, 'front_desk');

        $this->actingAs($foreignFrontDesk)
            ->get(route('appointments.show', $appointment))
            ->assertNotFound();
    }

    public function test_front_desk_cannot_update_another_clinics_appointment(): void
    {
        $clinicA = $this->clinic('alpha');
        $clinicB = $this->clinic('beta');
        $provider = $this->provider($clinicA);
        $service = \App\Models\Service::create(['clinic_id' => $clinicA->id, 'name' => 'Visit', 'duration' => 30, 'is_active' => true]);
        $appointment = $this->appointment($clinicA, $provider, $this->user($clinicA->id, 'patient'));
        $foreignAdmin = $this->user($clinicB->id, 'clinic_admin');

        $this->actingAs($foreignAdmin)
            ->put(route('appointments.update', $appointment), [
                'patient_id' => $appointment->patient_id,
                'provider_id' => $provider->id,
                'service_id' => $service->id,
                'start_at' => $appointment->start_at->toDateTimeString(),
            ])
            ->assertNotFound();

        $this->assertSame(Appointment::STATUS_BOOKED, $appointment->fresh()->status);
    }

    public function test_provider_cannot_view_another_providers_appointment(): void
    {
        $clinic = $this->clinic('alpha');
        $provider = $this->provider($clinic);
        $otherDoctor = $this->user($clinic->id, 'provider');
        $appointment = $this->appointment($clinic, $provider, $this->user($clinic->id, 'patient'));

        $this->actingAs($otherDoctor)
            ->get(route('appointments.show', $appointment))
            ->assertForbidden();
    }

    public function test_front_desk_cannot_view_another_clinics_patient(): void
    {
        $clinicA = $this->clinic('alpha');
        $clinicB = $this->clinic('beta');
        $patient = $this->user($clinicA->id, 'patient');
        $foreignFrontDesk = $this->user($clinicB->id, 'front_desk');

        $this->actingAs($foreignFrontDesk)
            ->get(route('patients.edit', $patient))
            ->assertNotFound();
    }

    public function test_clinic_admin_cannot_view_another_clinics_provider(): void
    {
        $clinicA = $this->clinic('alpha');
        $clinicB = $this->clinic('beta');
        $provider = $this->provider($clinicA);
        $foreignAdmin = $this->user($clinicB->id, 'clinic_admin');

        $this->actingAs($foreignAdmin)
            ->get(route('providers.show', $provider))
            ->assertNotFound();
    }

    public function test_clinic_admin_cannot_self_escalate_to_system_admin(): void
    {
        $clinic = $this->clinic('alpha');
        $admin = $this->user($clinic->id, 'clinic_admin');

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'New Superuser',
                'email' => 'escalate@example.com',
                'phone' => '1234567890',
                'password' => 'password123',
                'role' => 'system_admin',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'escalate@example.com']);
    }

    public function test_clinic_admin_cannot_demote_or_delete_a_system_admin(): void
    {
        $clinic = $this->clinic('alpha');
        $admin = $this->user($clinic->id, 'clinic_admin');
        $superuser = $this->user($clinic->id, 'system_admin');

        $this->actingAs($admin)
            ->put(route('users.update', $superuser), [
                'name' => $superuser->name,
                'email' => $superuser->email,
                'role' => 'front_desk',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->delete(route('users.destroy', $superuser))
            ->assertRedirect(); // back() with an error flash, not a hard failure

        $this->assertNotNull($superuser->fresh());
    }

    public function test_clinic_admin_cannot_manage_another_clinics_users(): void
    {
        $clinicA = $this->clinic('alpha');
        $clinicB = $this->clinic('beta');
        $foreignAdmin = $this->user($clinicB->id, 'clinic_admin');
        $target = $this->user($clinicA->id, 'front_desk');

        $this->actingAs($foreignAdmin)
            ->get(route('users.edit', $target))
            ->assertNotFound();
    }

    public function test_refund_rejected_on_pending_payment(): void
    {
        $clinic = $this->clinic('alpha');
        $patient = $this->user($clinic->id, 'patient');
        $billing = $this->user($clinic->id, 'billing');
        $payment = Payment::create([
            'patient_id' => $patient->id, 'amount' => 500, 'currency' => 'INR',
            'type' => 'deposit', 'method' => 'cash', 'status' => 'pending',
        ]);

        $this->actingAs($billing)
            ->post(route('payments.refund', $payment))
            ->assertStatus(422);

        $this->assertSame('pending', $payment->fresh()->status);
    }

    public function test_refund_cannot_be_applied_twice(): void
    {
        $clinic = $this->clinic('alpha');
        $patient = $this->user($clinic->id, 'patient');
        $billing = $this->user($clinic->id, 'billing');
        $payment = Payment::create([
            'patient_id' => $patient->id, 'amount' => 500, 'currency' => 'INR',
            'type' => 'deposit', 'method' => 'cash', 'status' => 'paid', 'paid_at' => now(),
        ]);

        $this->actingAs($billing)->post(route('payments.refund', $payment))->assertRedirect();
        $this->assertSame('refunded', $payment->fresh()->status);

        $this->actingAs($billing)
            ->post(route('payments.refund', $payment))
            ->assertStatus(422);

        $this->assertSame(1, Payment::where('type', 'refund')->count());
    }

    public function test_billing_cannot_refund_another_clinics_payment(): void
    {
        $clinicA = $this->clinic('alpha');
        $clinicB = $this->clinic('beta');
        $patient = $this->user($clinicA->id, 'patient');
        $foreignBilling = $this->user($clinicB->id, 'billing');
        $payment = Payment::create([
            'patient_id' => $patient->id, 'amount' => 500, 'currency' => 'INR',
            'type' => 'copay', 'method' => 'cash', 'status' => 'paid', 'paid_at' => now(),
        ]);

        $this->actingAs($foreignBilling)
            ->post(route('payments.refund', $payment))
            ->assertNotFound();
    }

    public function test_audit_log_index_excludes_other_clinics_entries(): void
    {
        $clinicA = $this->clinic('alpha');
        $clinicB = $this->clinic('beta');
        $adminA = $this->user($clinicA->id, 'clinic_admin');
        $adminB = $this->user($clinicB->id, 'clinic_admin');

        \App\Models\AuditLog::create(['user_id' => $adminA->id, 'action' => 'created', 'entity' => 'Appointment', 'entity_id' => 1]);
        \App\Models\AuditLog::create(['user_id' => $adminB->id, 'action' => 'created', 'entity' => 'Appointment', 'entity_id' => 2]);

        $response = $this->actingAs($adminA)->get(route('audit.index'));

        $response->assertOk();
        $response->assertSee($adminA->name);
        $response->assertDontSee($adminB->name);
    }
}
