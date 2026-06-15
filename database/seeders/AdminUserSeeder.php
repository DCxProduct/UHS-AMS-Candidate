<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
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
            'password' => Hash::make('1234567a'),
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

        if (method_exists($admin, 'assignRole') && ! $admin->hasRole('Admin')) {
            $admin->assignRole('Admin');
        }
    }
}
