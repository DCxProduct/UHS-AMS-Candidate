<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\SystemUser;
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

        Role::query()->updateOrCreate(
            [
                'name' => 'cashier',
                'guard_name' => 'web',
            ],
            [
                'name_kh' => 'បេឡា',
                'role_type_key' => 'staff',
            ]
        );

        $cashier = SystemUser::query()->updateOrCreate(
            [
                'username' => 'cashier',
            ],
            [
                'name' => 'Cashier',
                'username' => 'cashier',
                'email' => 'cashier@gmail.com',
                'phone' => '010000098',
                'password' => Hash::make('1234567a'),
                'roles' => ['cashier'],
                'permissions' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $cashier->syncLoginUser();
    }
}
