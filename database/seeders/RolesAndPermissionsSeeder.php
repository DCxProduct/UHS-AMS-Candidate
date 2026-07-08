<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Generate permissions using Filament Shield automatically
        Artisan::call('shield:generate', [
            '--all' => true,
            '--ignore-existing-policies' => true,
            '--panel' => 'app',
            '--no-interaction' => true,
        ]);

        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $student = Role::firstOrCreate([
            'name' => 'student',
            'guard_name' => 'web',
        ]);

        $admin->syncPermissions(Permission::all());

        $adminUser = User::updateOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'username' => 'admin',
                'name' => 'Admin',
                'password' => Hash::make('1234567a'),
                'registration_type' => 'admin',
                'date_of_birth' => '2000-01-01',
            ]
        );

        $adminUser->syncRoles(['admin']);

        $studentUser = User::updateOrCreate(
            ['email' => 'student@gmail.com'],
            [
                'username' => 'student',
                'name' => 'Student',
                'name_latin' => 'STUDENT',
                'password' => Hash::make('1234567a'),
                'registration_type' => 'student',
                'academic_year' => '2025-2026',
                'phone' => '010000099',
                'date_of_birth' => '2001-01-01',
                'seat_number' => 'STU-0099',
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        $studentUser->syncRoles(['student']);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
