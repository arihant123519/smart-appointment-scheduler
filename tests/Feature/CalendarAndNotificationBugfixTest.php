<?php

namespace Tests\Feature;

use App\Console\Commands\NotifyTodaysAppointments;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Provider;
use App\Models\Service;
use App\Models\User;
use App\Notifications\GenericNotification;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two live-reported bugs:
 *  1. The calendar's color legend (Booked/Confirmed/.../Cancelled) implied
 *     status-based coloring, but events were colored by the appointment's
 *     *service*, so a completed visit could show as "confirmed" and vice versa.
 *  2. "Appointment today" in-app notifications never expired, so a
 *     three-week-old unread reminder kept showing "today" for a date that
 *     had long since passed.
 */
class CalendarAndNotificationBugfixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function clinic(): Clinic
    {
        return Clinic::create(['slug' => 'alpha', 'name' => 'Alpha', 'timezone' => 'UTC', 'is_active' => true]);
    }

    private function provider(Clinic $clinic): Provider
    {
        $doctor = User::factory()->create(['clinic_id' => $clinic->id]);
        $doctor->syncRoles('provider');

        return Provider::create([
            'user_id' => $doctor->id, 'clinic_id' => $clinic->id,
            'specialty' => 'General', 'default_slot_minutes' => 30, 'is_active' => true,
        ]);
    }

    public function test_calendar_event_color_reflects_status_not_service(): void
    {
        $clinic = $this->clinic();
        $provider = $this->provider($clinic);
        $patient = User::factory()->create(['clinic_id' => $clinic->id]);
        $patient->syncRoles('patient');
        // A service with its own bold branding color that must NOT leak onto the calendar dot.
        $service = Service::create(['clinic_id' => $clinic->id, 'name' => 'Therapy', 'duration' => 30, 'is_active' => true, 'color' => '#000000']);

        $completed = Appointment::create([
            'patient_id' => $patient->id, 'provider_id' => $provider->id, 'clinic_id' => $clinic->id,
            'service_id' => $service->id,
            'start_at' => now()->subDay(), 'end_at' => now()->subDay()->addMinutes(30),
            'status' => Appointment::STATUS_COMPLETED, 'channel' => 'web',
        ]);
        $confirmed = Appointment::create([
            'patient_id' => $patient->id, 'provider_id' => $provider->id, 'clinic_id' => $clinic->id,
            'service_id' => $service->id,
            'start_at' => now()->addDay(), 'end_at' => now()->addDay()->addMinutes(30),
            'status' => Appointment::STATUS_CONFIRMED, 'channel' => 'web',
        ]);

        $admin = User::factory()->create(['clinic_id' => $clinic->id]);
        $admin->syncRoles('clinic_admin');

        $response = $this->actingAs($admin)->getJson(route('calendar.events', [
            'start' => now()->subDays(2)->toIso8601String(),
            'end' => now()->addDays(2)->toIso8601String(),
        ]));

        $response->assertOk();
        $events = collect($response->json())->keyBy('id');

        $this->assertSame('#17c653', $events[$completed->id]['color'], 'Completed appointment should use the green "Completed" legend color.');
        $this->assertSame('#7239ea', $events[$confirmed->id]['color'], 'Confirmed appointment should use the purple "Confirmed" legend color.');
        $this->assertNotSame('#000000', $events[$completed->id]['color'], 'Event color must not leak the service branding color.');
    }

    public function test_stale_today_notification_is_auto_expired_by_next_days_run(): void
    {
        $clinic = $this->clinic();
        $provider = $this->provider($clinic);
        $doctor = $provider->user;

        // Simulate an unread "appointment today" notification created 3 weeks ago.
        $doctor->notify(new GenericNotification('Appointment today', 'Today at 1:30 PM: Mia Hernandez.', null, 'fi-rr-calendar-clock', 'provider-today-999'));
        $doctor->notifications()->first()->update(['created_at' => now()->subWeeks(3), 'updated_at' => now()->subWeeks(3)]);

        $this->assertSame(1, $doctor->fresh()->unreadNotifications()->count());

        $this->artisan(NotifyTodaysAppointments::class)->assertSuccessful();

        $this->assertSame(0, $doctor->fresh()->unreadNotifications()->count(), 'A 3-week-old "today" reminder must be auto-expired, not still shown as new.');
    }
}
