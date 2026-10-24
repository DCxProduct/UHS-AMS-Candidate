<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Policies\CustomFormEntryPolicy;
use App\Support\CustomFormRoleAccess;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CustomFormRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_role_can_be_restricted_to_selected_candidate_forms(): void
    {
        $role = Role::query()->create([
            'name' => 'form_reviewer',
            'guard_name' => 'web',
            'role_type_key' => 'staff',
            'restrict_custom_form_access' => true,
            'allowed_custom_form_ids' => [12, 15],
        ]);

        $user = $this->user('form_reviewer_user');
        $user->assignRole($role);

        $this->assertSame([12, 15], CustomFormRoleAccess::allowedFormIds($user));
        $this->assertTrue(CustomFormRoleAccess::canAccessForm($user, 12));
        $this->assertFalse(CustomFormRoleAccess::canAccessForm($user, 21));
    }

    public function test_unrestricted_roles_and_admin_keep_existing_full_form_access(): void
    {
        $unrestrictedRole = Role::query()->create([
            'name' => 'legacy_staff',
            'guard_name' => 'web',
            'role_type_key' => 'staff',
        ]);
        $unrestrictedUser = $this->user('unrestricted_user');
        $unrestrictedUser->assignRole($unrestrictedRole);

        $adminRole = Role::query()->create([
            'name' => 'admin',
            'guard_name' => 'web',
            'role_type_key' => 'staff',
            'restrict_custom_form_access' => true,
            'allowed_custom_form_ids' => [],
        ]);
        $admin = $this->user('admin_user');
        $admin->assignRole($adminRole);

        $this->assertNull(CustomFormRoleAccess::allowedFormIds($unrestrictedUser));
        $this->assertNull(CustomFormRoleAccess::canAccessForm($unrestrictedUser, 42));
        $this->assertNull(CustomFormRoleAccess::allowedFormIds($admin));
    }

    public function test_restricted_role_cannot_open_an_entry_from_an_unselected_form(): void
    {
        $role = Role::query()->create([
            'name' => 'limited_reviewer',
            'guard_name' => 'web',
            'role_type_key' => 'staff',
            'restrict_custom_form_access' => true,
            'allowed_custom_form_ids' => [12],
        ]);
        $permission = Permission::query()->create([
            'name' => 'View:CustomFormEntry',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo($permission);

        $user = $this->user('limited_reviewer_user');
        $user->assignRole($role);
        $policy = new CustomFormEntryPolicy;

        $this->assertTrue($policy->view($user, new CustomFormEntry(['custom_form_id' => 12])));
        $this->assertFalse($policy->view($user, new CustomFormEntry(['custom_form_id' => 21])));
    }

    private function user(string $username): User
    {
        return User::query()->create([
            'registration_type' => 'admin',
            'name' => $username,
            'username' => $username,
            'email' => $username.'@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => 'password',
            'is_active' => true,
        ]);
    }
}
