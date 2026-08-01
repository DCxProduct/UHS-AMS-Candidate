<?php

namespace Database\Seeders;

use App\Models\SystemUser;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

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

        Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        if (method_exists($admin, 'assignRole') && ! $admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }

        Role::firstOrCreate([
            'name' => 'cashier',
            'guard_name' => 'web',
        ]);

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
