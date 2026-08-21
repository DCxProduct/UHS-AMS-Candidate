<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource as ShieldRoleResource;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Artisan;
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

        $systemAdminRoles = collect([
            'admin' => [
                'label_en' => 'Admin',
                'name_kh' => 'អ្នកគ្រប់គ្រង',
            ],
            'cashier' => [
                'label_en' => 'Cashier',
                'name_kh' => 'បេឡា',
            ],
        ])->mapWithKeys(fn (array $attributes, string $role): array => [
            $role => Role::query()->updateOrCreate(
                [
                    'name' => $role,
                    'guard_name' => 'web',
                ],
                [
                    'label_en' => $attributes['label_en'],
                    'name_kh' => $attributes['name_kh'],
                    'role_type_key' => 'staff',
                ]
            ),
        ]);

        $userRoles = collect([
            'candidate' => [
                'label_en' => 'Candidate',
                'name_kh' => 'បេក្ខជន',
            ],
        ])->mapWithKeys(fn (array $attributes, string $role): array => [
            $role => Role::query()->updateOrCreate(
                [
                    'name' => $role,
                    'guard_name' => 'web',
                ],
                [
                    'label_en' => $attributes['label_en'],
                    'name_kh' => $attributes['name_kh'],
                    'role_type_key' => 'candidate',
                ]
            ),
        ]);

        $admin = $systemAdminRoles['admin'];
        $cashier = $systemAdminRoles['cashier'];
        $candidate = $userRoles['candidate'];

        $adminExcludedPermissions = [
            'Create:CustomFormEntry',
        ];

        $manageablePermissionNames = ShieldRoleResource::manageablePermissionNames();

        $admin->syncPermissions(
            Permission::query()
                ->where('guard_name', 'web')
                ->whereIn('name', $manageablePermissionNames)
                ->whereNotIn('name', $adminExcludedPermissions)
                ->get()
        );
        $cashier->syncPermissions(
            Permission::query()
                ->where('guard_name', 'web')
                ->whereIn('name', $manageablePermissionNames)
                ->where(function ($query): void {
                    $query->where('name', 'like', '%:Payment')
                        ->orWhere('name', 'like', '%:UnpaidApplication');
                })
                ->get()
        );

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
                'name' => 'Candidate',
                'name_latin' => 'CANDIDATE',
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

        $studentUser->syncRoles(['candidate']);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}
