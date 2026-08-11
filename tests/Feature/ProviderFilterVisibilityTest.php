<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\Provider;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The provider-filter dropdown (calendar + appointments list) is only useful
 * to roles that see more than one provider's schedule. A plain provider only
 * ever sees their own, so the dropdown is noise for them; system_admin,
 * front_desk and clinic_admin keep it.
 */
class ProviderFilterVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function clinicWithTwoProviders(): Clinic
    {
        $clinic = Clinic::create(['slug' => 'alpha', 'name' => 'Alpha', 'timezone' => 'UTC', 'is_active' => true]);

        foreach (['Dr One', 'Dr Two'] as $name) {
            $doctor = User::factory()->create(['clinic_id' => $clinic->id, 'name' => $name]);
            $doctor->syncRoles('provider');
            Provider::create([
                'user_id' => $doctor->id, 'clinic_id' => $clinic->id,
                'specialty' => 'General', 'default_slot_minutes' => 30, 'is_active' => true,
            ]);
        }

        return $clinic;
    }

    public function test_provider_role_does_not_see_the_filter_on_calendar_or_appointments(): void
    {
        $clinic = $this->clinicWithTwoProviders();
        $doctor = User::factory()->create(['clinic_id' => $clinic->id]);
        $doctor->syncRoles('provider');
        Provider::create([
            'user_id' => $doctor->id, 'clinic_id' => $clinic->id,
            'specialty' => 'General', 'default_slot_minutes' => 30, 'is_active' => true,
        ]);

        $this->actingAs($doctor)->get(route('calendar'))
            ->assertOk()->assertDontSee('Filter by provider');

        $this->actingAs($doctor)->get(route('appointments.index'))
            ->assertOk()->assertDontSee('id="provider_id"', false)
            ->assertDontSee('name="provider_id"', false);
    }

    public function test_front_desk_clinic_admin_and_system_admin_still_see_the_filter(): void
    {
        $clinic = $this->clinicWithTwoProviders();

        foreach (['front_desk', 'clinic_admin', 'system_admin'] as $role) {
            $staff = User::factory()->create(['clinic_id' => $clinic->id]);
            $staff->syncRoles($role);

            $this->actingAs($staff)->get(route('calendar'))
                ->assertOk()->assertSee('Filter by provider');

            $this->actingAs($staff)->get(route('appointments.index'))
                ->assertOk()->assertSee('name="provider_id"', false);
        }
    }
}
