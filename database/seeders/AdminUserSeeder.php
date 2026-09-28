<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $adminPassword = $this->seededPassword('SEEDED_ADMIN_PASSWORD');

        $data = [
            'registration_type' => 'admin',
            'academic_year' => null,
            'name' => 'System Admin',
            'name_latin' => 'System Admin',
            'username' => 'admin',
            'email' => 'admin@gmail.com',
            'email_verified_at' => now(),
            'phone' => '010000000',
            'date_of_birth' => '2000-01-01',
            'seat_number' => null,
            'avatar' => null,
            'is_active' => true,
            'password' => Hash::make($adminPassword),
        ];

        if (Schema::hasColumn('users', 'locale')) {
            $data['locale'] = 'km';
        }

        $admin = User::query()->updateOrCreate(
            [
                'username' => 'admin',
            ],
            $data,
        );

        Role::query()->updateOrCreate(
            [
                'name' => 'admin',
                'guard_name' => 'web',
            ],
            [
                'name_kh' => 'អ្នកគ្រប់គ្រង',
                'role_type_key' => 'staff',
            ]
        );

        if (method_exists($admin, 'assignRole') && ! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }

    }

    private function seededPassword(string $environmentKey): string
    {
        $password = app()->environment('production')
            ? env($environmentKey)
            : '12345678';

        if (blank($password)) {
            throw new \RuntimeException("{$environmentKey} must be configured before running seeders in production.");
        }

        return (string) $password;
    }
}
