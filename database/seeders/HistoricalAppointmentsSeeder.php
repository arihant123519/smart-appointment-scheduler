<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Provider;
use App\Models\Service;
use App\Models\User;
use App\Services\NoShowPredictor;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Bulk-fills ~3 months of realistic appointment history (past + a couple
 * weeks upcoming) for the demo clinic, on top of whatever DemoDataSeeder
 * already created.
 *
 * Deliberately DB-only: every row is bulk-inserted via Appointment::insert()
 * (raw query builder, bypasses Eloquent events entirely) with its final
 * historical status already baked in, so Appointment::booted()'s `updated`
 * listener (ClinicDeskNotifier / RetentionService) never fires, and no
 * WhatsApp/email/payment/AI service is touched. Safe to run as many times
 * as you like — it only ever adds rows, never mutates existing ones.
 *
 * Run with: php artisan db:seed --class=HistoricalAppointmentsSeeder
 */
class HistoricalAppointmentsSeeder extends Seeder
{
    /** How far back the history should go. */
    private int $pastDays = 90;

    /** How far into the future to keep booking (upcoming appointments). */
    private int $futureDays = 14;

    /** Appointments generated per provider per weekday (min, max). */
    private array $perProviderPerDay = [2, 6];

    private array $cancellationReasons = [
        'Patient requested reschedule',
        'Provider unavailable',
        'Patient feeling better',
        'Scheduling conflict',
        'Transportation issue',
    ];

    public function run(): void
    {
        // Ensure the base clinic/providers/services/patients exist. Every
        // call in DemoDataSeeder is firstOrCreate, so re-running it here is
        // a no-op if it already ran and safe if it hasn't.
        $this->call(DemoDataSeeder::class);

        $clinic = Clinic::where('slug', 'sunrise-health')->firstOrFail();
        $providers = Provider::where('clinic_id', $clinic->id)->with('services')->get();
        $services = Service::where('clinic_id', $clinic->id)->get();
        $patients = User::where('clinic_id', $clinic->id)->role('patient')->get();

        if ($providers->isEmpty() || $services->isEmpty() || $patients->isEmpty()) {
            $this->command?->error('Base clinic data missing — DemoDataSeeder did not produce providers/services/patients.');

            return;
        }

        // Pull every (provider_id, start_at) pair that already exists so we
        // never violate the unique index, without hammering the DB with a
        // query per candidate slot.
        $existingSlots = Appointment::query()
            ->where('clinic_id', $clinic->id)
            ->get(['provider_id', 'start_at'])
            ->map(fn ($a) => $a->provider_id.'|'.$a->start_at->format('Y-m-d H:i:s'))
            ->flip();

        $predictor = app(NoShowPredictor::class);

        $statusesPast = [
            Appointment::STATUS_COMPLETED, Appointment::STATUS_COMPLETED,
            Appointment::STATUS_COMPLETED, Appointment::STATUS_COMPLETED,
            Appointment::STATUS_NO_SHOW, Appointment::STATUS_CANCELLED,
        ];
        $statusesFuture = [Appointment::STATUS_BOOKED, Appointment::STATUS_CONFIRMED];

        $rows = [];
        $today = Carbon::today();
        $start = $today->copy()->subDays($this->pastDays);
        $end = $today->copy()->addDays($this->futureDays);

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use (
            $providers, $services, $patients, $existingSlots, $predictor,
            $statusesPast, $statusesFuture, $today, $start, $end, &$created, &$skipped, &$rows
        ) {
            for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
                if ($day->isWeekend()) {
                    continue;
                }

                $isPast = $day->lt($today);

                foreach ($providers as $provider) {
                    $providerServices = $provider->services->isNotEmpty() ? $provider->services : $services;
                    $count = rand($this->perProviderPerDay[0], $this->perProviderPerDay[1]);

                    // 30-minute slots inside working hours (09:00-17:00),
                    // shuffled so the picked appointments land at varied times.
                    $slots = [];
                    for ($h = 9; $h < 17; $h++) {
                        foreach ([0, 30] as $m) {
                            $slots[] = $day->copy()->setTime($h, $m);
                        }
                    }
                    shuffle($slots);

                    $bookedToday = 0;
                    foreach ($slots as $slotStart) {
                        if ($bookedToday >= $count) {
                            break;
                        }

                        $service = $providerServices->random();
                        $slotEnd = $slotStart->copy()->addMinutes($service->duration);
                        if ($slotEnd->format('H:i') > '17:00') {
                            continue;
                        }

                        $key = $provider->id.'|'.$slotStart->format('Y-m-d H:i:s');
                        if (isset($existingSlots[$key])) {
                            $skipped++;

                            continue;
                        }
                        $existingSlots[$key] = true;

                        $patient = $patients->random();
                        $status = $isPast
                            ? $statusesPast[array_rand($statusesPast)]
                            : $statusesFuture[array_rand($statusesFuture)];

                        $noShowScore = $predictor->score([
                            'patient_id' => $patient->id,
                            'start_at' => $slotStart->toDateTimeString(),
                        ]);

                        $confirmedAt = null;
                        $checkedInAt = null;
                        $cancelledAt = null;
                        $cancellationReason = null;

                        if (in_array($status, [Appointment::STATUS_COMPLETED, Appointment::STATUS_CONFIRMED], true)) {
                            $confirmedAt = $slotStart->copy()->subHours(rand(2, 48));
                        }
                        if ($status === Appointment::STATUS_COMPLETED) {
                            $checkedInAt = $slotStart->copy()->subMinutes(rand(1, 10));
                        }
                        if ($status === Appointment::STATUS_CANCELLED) {
                            $cancelledAt = $slotStart->copy()->subHours(rand(1, 72));
                            $cancellationReason = $this->cancellationReasons[array_rand($this->cancellationReasons)];
                        }

                        $rows[] = [
                            'patient_id' => $patient->id,
                            'provider_id' => $provider->id,
                            'clinic_id' => $provider->clinic_id,
                            'service_id' => $service->id,
                            'start_at' => $slotStart->format('Y-m-d H:i:s'),
                            'end_at' => $slotEnd->format('Y-m-d H:i:s'),
                            'status' => $status,
                            'channel' => ['web', 'app', 'phone', 'walk_in', 'ai'][array_rand(['web', 'app', 'phone', 'walk_in', 'ai'])],
                            'is_telehealth' => (int) $service->telehealth,
                            'no_show_score' => $noShowScore,
                            'reason' => $service->name,
                            'confirmed_at' => $confirmedAt?->format('Y-m-d H:i:s'),
                            'checked_in_at' => $checkedInAt?->format('Y-m-d H:i:s'),
                            'cancelled_at' => $cancelledAt?->format('Y-m-d H:i:s'),
                            'cancellation_reason' => $cancellationReason,
                            'created_at' => $slotStart->copy()->subDays(rand(1, 14))->format('Y-m-d H:i:s'),
                            'updated_at' => now()->format('Y-m-d H:i:s'),
                        ];

                        $bookedToday++;
                        $created++;

                        // Flush in batches to keep memory sane over ~3 months of data.
                        if (count($rows) >= 500) {
                            Appointment::insert($rows);
                            $rows = [];
                        }
                    }
                }
            }

            if (! empty($rows)) {
                Appointment::insert($rows);
            }
        });

        $this->command?->info("Historical appointments seeded: {$created} created, {$skipped} slot collisions skipped.");
    }
}
