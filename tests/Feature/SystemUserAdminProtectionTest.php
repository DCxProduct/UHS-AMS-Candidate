<?php

namespace Tests\Feature;

use App\Models\SystemUser;
use App\Models\User;
use App\Policies\SystemUserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SystemUserAdminProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_role_system_user_cannot_be_updated(): void
    {
        $authUser = User::query()->create([
            'name' => 'Staff Manager',
            'username' => 'staff-manager',
            'email' => 'staff-manager@example.test',
            'password' => 'password',
            'registration_type' => 'admin',
            'date_of_birth' => '1990-01-01',
            'is_active' => true,
        ]);
        Permission::findOrCreate('Update:SystemUser', 'web');
        $authUser->givePermissionTo('Update:SystemUser');

        $adminAccount = new SystemUser(['roles' => ['admin']]);
        $regularAccount = new SystemUser(['roles' => ['registrar']]);
        $policy = new SystemUserPolicy;

        $this->assertFalse($policy->update($authUser, $adminAccount));
        $this->assertTrue($policy->update($authUser, $regularAccount));
    }
}
