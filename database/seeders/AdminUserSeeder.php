<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            [
                'username' => 'admin',
            ],
            [
                'registration_type' => 'admin',
                'academic_year' => null,
                'name' => 'System Admin',
                'name_latin' => 'System Admin',
                'email' => 'admin@gmail.com',
                'email_verified_at' => now(),
                'phone' => '010000000',
                'date_of_birth' => '2000-01-01',
                'seat_number' => null,
                'avatar' => null,
                'is_active' => true,
                'password' => Hash::make('1234567a'),
            ]
        );
    }
}
