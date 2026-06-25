<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Availability;
use App\Models\Clinic;
use App\Models\Provider;
use App\Models\Resource;
use App\Models\Service;
use App\Models\User;
use App\Services\NoShowPredictor;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $clinic = Clinic::firstOrCreate(
            ['slug' => 'sunrise-health'],
            [
                'name' => 'Sunrise Health Clinic',
                'email' => 'hello@sunrisehealth.test',
                'phone' => '+1 (555) 010-2030',
                'address' => '120 Wellness Ave',
                'city' => 'Austin',
                'state' => 'TX',
                'country' => 'USA',
                'timezone' => 'America/Chicago',
                'is_active' => true,
            ]
        );

        // --- Staff & admin users -------------------------------------------------
        $sysAdmin = $this->user('System Admin', 'admin@scheduler.test', $clinic->id);
        $sysAdmin->syncRoles('system_admin');

        $clinicAdmin = $this->user('Clara Admin', 'clinicadmin@scheduler.test', $clinic->id);
        $clinicAdmin->syncRoles('clinic_admin');

        $frontDesk = $this->user('Frieda Desk', 'frontdesk@scheduler.test', $clinic->id);
        $frontDesk->syncRoles('front_desk');

        $billing = $this->user('Bill Ledger', 'billing@scheduler.test', $clinic->id);
        $billing->syncRoles('billing');

        // --- Services ------------------------------------------------------------
        $services = collect([
            ['name' => 'General Consultation', 'specialty' => 'General', 'duration' => 30, 'buffer' => 5, 'price' => 80, 'color' => '#5955D1'],
            ['name' => 'Follow-up Visit', 'specialty' => 'General', 'duration' => 20, 'buffer' => 5, 'price' => 50, 'color' => '#22C55E'],
            ['name' => 'Dental Cleaning', 'specialty' => 'Dental', 'duration' => 45, 'buffer' => 10, 'price' => 120, 'color' => '#0EA5E9'],
            ['name' => 'Therapy Session', 'specialty' => 'Mental Health', 'duration' => 60, 'buffer' => 0, 'price' => 150, 'color' => '#F59E0B', 'telehealth' => true],
            ['name' => 'Pediatric Checkup', 'specialty' => 'Pediatrics', 'duration' => 30, 'buffer' => 5, 'price' => 90],
        ])->map(fn ($s) => Service::firstOrCreate(
            ['clinic_id' => $clinic->id, 'name' => $s['name']],
            array_merge(['clinic_id' => $clinic->id], $s)
        ));

        // --- Providers -----------------------------------------------------------
        $providerSpecs = [
            ['name' => 'Dr. Sarah Chen', 'email' => 'sarah.chen@scheduler.test', 'specialty' => 'General Practitioner', 'credentials' => 'MD'],
            ['name' => 'Dr. James Okafor', 'email' => 'james.okafor@scheduler.test', 'specialty' => 'Dentist', 'credentials' => 'DDS'],
            ['name' => 'Dr. Maria Lopez', 'email' => 'maria.lopez@scheduler.test', 'specialty' => 'Therapist', 'credentials' => 'PsyD', 'telehealth' => true],
        ];

        $providers = collect($providerSpecs)->map(function ($spec) use ($clinic, $services) {
            $user = $this->user($spec['name'], $spec['email'], $clinic->id);
            $user->syncRoles('provider');

            $provider = Provider::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'clinic_id' => $clinic->id,
                    'specialty' => $spec['specialty'],
                    'credentials' => $spec['credentials'],
                    'bio' => $spec['name'].' is an experienced '.$spec['specialty'].'.',
                    'accepts_telehealth' => $spec['telehealth'] ?? false,
                    'default_slot_minutes' => 30,
                ]
            );

            // Working hours Mon-Fri 9:00-17:00
            for ($dow = 1; $dow <= 5; $dow++) {
                Availability::firstOrCreate([
                    'provider_id' => $provider->id,
                    'day_of_week' => $dow,
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                ], ['recurring' => true]);
            }

            // Attach a few services
            $provider->services()->syncWithoutDetaching($services->random(min(3, $services->count()))->pluck('id'));

            return $provider;
        });

        // --- Resources -----------------------------------------------------------
        foreach (['Room A', 'Room B', 'Telehealth Suite'] as $i => $room) {
            Resource::firstOrCreate(
                ['clinic_id' => $clinic->id, 'name' => $room],
                ['type' => $i === 2 ? 'device' : 'room', 'capacity' => 1]
            );
        }

        // --- Patients ------------------------------------------------------------
        $patientNames = [
            'John Smith', 'Emily Johnson', 'Michael Brown', 'Jessica Davis', 'David Wilson',
            'Sophia Martinez', 'Daniel Garcia', 'Olivia Rodriguez', 'James Anderson', 'Ava Thomas',
            'Robert Taylor', 'Mia Hernandez', 'William Moore', 'Isabella Jackson', 'Charvi Patel',
        ];

        $patients = collect($patientNames)->map(function ($name, $i) use ($clinic) {
            $email = 'patient'.($i + 1).'@scheduler.test';
            $user = $this->user($name, $email, $clinic->id, [
                'phone' => '+1 (555) 02'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).'-1000',
                'date_of_birth' => now()->subYears(rand(18, 75))->subDays(rand(0, 360))->toDateString(),
                'gender' => ['male', 'female', 'other'][rand(0, 2)],
            ]);
            $user->syncRoles('patient');

            return $user;
        });

        // --- Appointments (past + upcoming) -------------------------------------
        $predictor = app(NoShowPredictor::class);
        $statusesPast = [
            Appointment::STATUS_COMPLETED, Appointment::STATUS_COMPLETED,
            Appointment::STATUS_COMPLETED, Appointment::STATUS_NO_SHOW, Appointment::STATUS_CANCELLED,
        ];

        foreach (range(1, 60) as $n) {
            $provider = $providers->random();
            $patient = $patients->random();
            $service = $services->random();
            $isPast = $n <= 35;

            $day = $isPast
                ? now()->subDays(rand(1, 40))
                : now()->addDays(rand(0, 21));

            // Snap to a Mon-Fri working hour
            while (in_array($day->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY], true)) {
                $day->addDay();
            }
            $start = $day->copy()->setTime(rand(9, 15), [0, 30][rand(0, 1)]);
            $end = $start->copy()->addMinutes($service->duration);

            // Skip exact-slot collisions to respect the unique index.
            $exists = Appointment::where('provider_id', $provider->id)
                ->where('start_at', $start)->exists();
            if ($exists) {
                continue;
            }

            $status = $isPast
                ? $statusesPast[array_rand($statusesPast)]
                : [Appointment::STATUS_BOOKED, Appointment::STATUS_CONFIRMED][rand(0, 1)];

            Appointment::create([
                'patient_id' => $patient->id,
                'provider_id' => $provider->id,
                'clinic_id' => $clinic->id,
                'service_id' => $service->id,
                'start_at' => $start,
                'end_at' => $end,
                'status' => $status,
                'channel' => ['web', 'app', 'phone', 'ai'][rand(0, 3)],
                'is_telehealth' => (bool) $service->telehealth,
                'no_show_score' => $predictor->score([
                    'patient_id' => $patient->id,
                    'start_at' => $start->toDateTimeString(),
                ]),
                'reason' => $service->name,
                'confirmed_at' => $status === Appointment::STATUS_CONFIRMED ? now() : null,
            ]);
        }
    }

    private function user(string $name, string $email, int $clinicId, array $extra = []): User
    {
        return User::firstOrCreate(
            ['email' => $email],
            array_merge([
                'name' => $name,
                'clinic_id' => $clinicId,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ], $extra)
        );
    }
}
