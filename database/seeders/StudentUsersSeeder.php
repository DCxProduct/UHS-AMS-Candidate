<?php

namespace Database\Seeders;

use App\Models\SystemUser;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class StudentUsersSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            for ($i = 1; $i <= 10; $i++) {
                $username = 'student' . $i;
                $email = 'student' . $i . '@gmail.com';
                $phone = '01000000' . $i;
                $hashedPassword = Hash::make('1234567a');

                $user = User::query()->updateOrCreate(
                    [
                        'username' => $username,
                    ],
                    [
                        'registration_type' => 'student',
                        'academic_year' => '2025-2026',
                        'name' => $username,
                        'name_latin' => 'CANDIDATE ' . $i,
                        'email' => $email,
                        'phone' => $phone,
                        'date_of_birth' => '2001-01-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                        'seat_number' => 'STU-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                        'avatar' => null,
                        'email_verified_at' => now(),
                        'is_active' => true,
                        'password' => $hashedPassword,
                    ]
                );

                SystemUser::query()->updateOrCreate(
                    [
                        'username' => $username,
                    ],
                    [
                        'name' => $username,
                        'email' => $email,
                        'phone' => $phone,
                        'password' => $hashedPassword,
                        'avatar' => null,
                        'roles' => [
                            'candidate',
                        ],
                        'permissions' => null,
                        'is_active' => true,
                        'email_verified_at' => now(),
                        'last_login_at' => null,
                    ]
                );
            }
        });
    }
}
