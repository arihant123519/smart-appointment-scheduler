<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()['cache']->forget('spatie.permission.cache');

        $permissions = [
            'view dashboard',
            'manage appointments',
            'view appointments',
            'manage calendar',
            'manage patients',
            'manage providers',
            'manage services',
            'manage clinics',
            'manage resources',
            'manage waitlist',
            'manage reminders',
            'view billing',
            'manage billing',
            'view reports',
            'manage users',
            'manage roles',
            'manage settings',
            'view audit logs',
            'use ai',
            'manage flows',
            'export patient data',
            'manage walk_in_queue',
            'manage consultations',
            'view consultations',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $roles = [
            'patient' => [
                // Patients use the dedicated patient dashboard + self-service booking,
                // not the staff scheduling screens, so they hold no staff permissions.
            ],
            'front_desk' => [
                'view dashboard', 'manage appointments', 'view appointments', 'manage calendar',
                'manage patients', 'manage waitlist', 'manage reminders', 'use ai',
                'manage walk_in_queue', 'view consultations',
            ],
            'provider' => [
                // Providers write consultations/prescriptions only on their own
                // appointments (ownership enforced in ConsultationController), but
                // `view consultations` also lets them read a shared patient's
                // history across other doctors in the clinic (continuity of care).
                'view dashboard', 'view appointments', 'manage calendar', 'use ai',
                'manage consultations', 'view consultations',
            ],
            'billing' => [
                'view dashboard', 'view billing', 'manage billing', 'view reports', 'view appointments',
            ],
            'clinic_admin' => [
                'view dashboard', 'manage appointments', 'view appointments', 'manage calendar',
                'manage patients', 'manage providers', 'manage services', 'manage resources',
                'manage waitlist', 'manage reminders', 'view billing', 'manage billing',
                'view reports', 'manage users', 'manage settings', 'view audit logs', 'use ai', 'manage flows',
                'export patient data', 'manage walk_in_queue', 'view consultations',
            ],
            'system_admin' => $permissions, // everything
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($rolePermissions);
        }
    }
}
