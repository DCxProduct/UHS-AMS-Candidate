<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuditLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_registrar_action_is_logged_with_role_and_sanitized_values(): void
    {
        $user = $this->createStaffUser('registrar', 'registrar_test');

        $this->actingAs($user);
        AuditLogger::log(
            action: 'review',
            description: 'Reviewed candidate application',
            oldValues: ['status' => 'pending', 'password' => 'hidden'],
            newValues: ['status' => 'approved', 'token' => 'hidden'],
            metadata: ['module' => 'Candidate Requested'],
        );

        $audit = AuditLog::query()->latest('id')->firstOrFail();

        $this->assertSame('registrar', $audit->actor_role);
        $this->assertSame(['status' => 'pending'], $audit->old_values);
        $this->assertSame(['status' => 'approved'], $audit->new_values);
        $this->assertSame('Candidate Requested', $audit->metadata['module']);
    }

    public function test_cashier_action_is_logged(): void
    {
        $user = $this->createStaffUser('cashier', 'cashier_test');

        $this->actingAs($user);
        AuditLogger::log(
            action: 'payment_create',
            description: 'Created payment',
            metadata: ['module' => 'Payments'],
        );

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->getKey(),
            'actor_role' => 'cashier',
            'action' => 'payment_create',
        ]);
    }

    private function createStaffUser(string $role, string $username): User
    {
        $roleModel = Role::query()->create([
            'name' => $role,
            'guard_name' => 'web',
        ]);

        $user = User::query()->create([
            'registration_type' => 'admin',
            'name' => $username,
            'username' => $username,
            'email' => $username.'@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $user->assignRole($roleModel);

        return $user;
    }
}
