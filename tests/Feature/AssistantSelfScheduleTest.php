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
 * A provider asking about their OWN schedule ("aaj meri konsi appointment hai",
 * "what appointments do I have today") must get their own appointments — not the
 * "whose record would you like to see?" name prompt.
 */
class AssistantSelfScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.ai.provider' => 'rule_based']);
        $this->seed(RolePermissionSeeder::class);
    }

    private function makeProviderWithTodayAppointment(): User
    {
        $clinic = Clinic::create([
            'slug' => 'test-clinic', 'name' => 'Test Clinic', 'timezone' => 'UTC', 'is_active' => true,
        ]);

        $doctor = User::factory()->create(['clinic_id' => $clinic->id]);
        $doctor->syncRoles('provider');

        $provider = Provider::create([
            'user_id' => $doctor->id, 'clinic_id' => $clinic->id,
            'specialty' => 'General', 'default_slot_minutes' => 30, 'is_active' => true,
        ]);

        $patient = User::factory()->create(['clinic_id' => $clinic->id]);
        $patient->syncRoles('patient');

        Appointment::create([
            'patient_id' => $patient->id,
            'provider_id' => $provider->id,
            'clinic_id' => $clinic->id,
            'start_at' => today()->setTime(10, 0),
            'end_at' => today()->setTime(10, 30),
            'status' => Appointment::STATUS_CONFIRMED,
            'channel' => 'web',
        ]);

        return $doctor;
    }

    public function test_provider_asking_in_hindi_gets_their_own_appointments(): void
    {
        $doctor = $this->makeProviderWithTodayAppointment();

        $response = $this->actingAs($doctor)
            ->postJson(route('ai.command'), ['text' => 'aaj meri konsi appointment hai']);

        $response->assertOk();
        $reply = $response->json('reply');

        $this->assertStringContainsString('appointment(s)', $reply);
        $this->assertStringNotContainsString('Whose record', $reply);
        // One appointment today for this provider.
        $this->assertStringContainsString('You have 1', $reply);
    }

    public function test_provider_asking_in_english_gets_their_own_appointments(): void
    {
        $doctor = $this->makeProviderWithTodayAppointment();

        $reply = $this->actingAs($doctor)
            ->postJson(route('ai.command'), ['text' => 'what appointments do I have today'])
            ->assertOk()
            ->json('reply');

        $this->assertStringContainsString('You have 1', $reply);
        $this->assertStringNotContainsString('Whose record', $reply);
    }

    public function test_asking_about_another_provider_still_requires_a_name_lookup(): void
    {
        $doctor = $this->makeProviderWithTodayAppointment();

        // "show me Dr. Chen's appointments" must NOT be treated as self.
        $reply = $this->actingAs($doctor)
            ->postJson(route('ai.command'), ['text' => "show me Dr. Nonexistent's appointments today"])
            ->assertOk()
            ->json('reply');

        $this->assertStringNotContainsString('You have 1', $reply);
    }
}
