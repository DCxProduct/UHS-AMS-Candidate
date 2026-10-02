<?php

namespace Database\Seeders;

use App\Models\SystemUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class StaffRoleAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $password = Hash::make($this->seededPassword('SEEDED_STAFF_PASSWORD'));

        foreach ($this->accounts() as $account) {
            $systemUser = SystemUser::query()->updateOrCreate(
                [
                    'username' => $account['username'],
                ],
                [
                    'name' => $account['name'],
                    'email' => $account['email'],
                    'phone' => $account['phone'],
                    'password' => $password,
                    'roles' => [$account['role']],
                    'permissions' => null,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );

            $systemUser->syncLoginUser();
        }
    }

    /**
     * Resolve the password used when seeding staff accounts.
     *
     * Production must configure the password explicitly: seeders run on every
     * deployment, so a hard-coded value would silently reset live accounts.
     */
    protected function seededPassword(string $environmentKey): string
    {
        $password = env($environmentKey);

        if (filled($password)) {
            return (string) $password;
        }

        if (app()->environment('production')) {
            throw new RuntimeException(
                "The {$environmentKey} environment variable must be set before seeding staff accounts in production."
            );
        }

        return '12345678';
    }

    protected function accounts(): array
    {
        return [
            [
                'username' => 'dataentry',
                'email' => 'dataentry@gmail.com',
                'name' => 'Data Entry',
                'role' => 'data_entry',
                'phone' => '010999009',
            ],
            [
                'username' => 'delete',
                'email' => 'delete@gmail.com',
                'name' => 'Delete',
                'role' => 'delete',
                'phone' => '010999008',
            ],
            [
                'username' => 'viewer',
                'email' => 'viewer@gmail.com',
                'name' => 'Viewer',
                'role' => 'viewer',
                'phone' => '010999007',
            ],
            [
                'username' => 'crud',
                'email' => 'crud@gmail.com',
                'name' => 'CRUD',
                'role' => 'crud',
                'phone' => '010999006',
            ],
            [
                'username' => 'cashmg',
                'email' => 'cashmg@gmail.com',
                'name' => 'Cashier Manager',
                'role' => 'cashier_manager',
                'phone' => '010999005',
            ],
            [
                'username' => 'cashofficer',
                'email' => 'cashofficer@gmail.com',
                'name' => 'Cashier Officer',
                'role' => 'cashier_officer',
                'phone' => '010999004',
            ],
            [
                'username' => 'regmg',
                'email' => 'regmg@gmail.com',
                'name' => 'Registrar Manager',
                'role' => 'registrar_manager',
                'phone' => '010999003',
            ],
            [
                'username' => 'regofficer',
                'email' => 'regofficer@gmail.com',
                'name' => 'Registrar Officer',
                'role' => 'registrar_officer',
                'phone' => '010999002',
            ],
        ];
    }
}
