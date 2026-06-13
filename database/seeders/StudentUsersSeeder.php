<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StudentUsersSeeder extends Seeder
{
    public function run(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            User::updateOrCreate(
                [
                    'username' => 'student' . $i,
                ],
                [
                    'registration_type' => 'student',
                    'academic_year' => '2025-2026',

                    'name' => 'student' . $i,
                    'name_latin' => 'STUDENT ' . $i,

                    'email' => 'student' . $i . '@gmail.com',
                    'phone' => '01000000' . $i,

                    'date_of_birth' => '2001-01-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                    'seat_number' => 'STU-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),

                    'avatar' => null,
                    'is_active' => true,
                    'password' => Hash::make('1234567a'),
                ]
            );
        }
    }
}
