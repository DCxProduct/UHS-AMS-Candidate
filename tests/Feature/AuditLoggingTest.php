<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Filament\Admin\Resources\AuditLogs\AuditLogResource;
use App\Support\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
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
            metadata: ['module' => 'Entrance Exam Statistics'],
        );

        $audit = AuditLog::query()->latest('id')->firstOrFail();

        $this->assertSame('registrar', $audit->actor_role);
        $this->assertSame(['status' => 'pending'], $audit->old_values);
        $this->assertSame(['status' => 'approved'], $audit->new_values);
        $this->assertSame('Entrance Exam Statistics', $audit->metadata['module']);
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

    public function test_login_and_logout_events_do_not_create_audit_records(): void
    {
        $user = $this->createStaffUser('admin', 'auth_audit_admin');

        Event::dispatch(new Login('web', $user, false));
        Event::dispatch(new Logout('web', $user));

        $this->assertDatabaseMissing('audit_logs', [
            'actor_id' => $user->getKey(),
            'action' => 'login',
        ]);
        $this->assertDatabaseMissing('audit_logs', [
            'actor_id' => $user->getKey(),
            'action' => 'logout',
        ]);

    }

    public function test_authorized_admin_can_open_audit_logs(): void
    {
        $admin = $this->createStaffUser('admin', 'audit_admin');

        $this->actingAs($admin)
            ->get('/audit-logs')
            ->assertOk();
    }

    public function test_registrar_and_cashier_cannot_open_admin_audit_logs(): void
    {
        foreach (['registrar', 'cashier'] as $role) {
            $user = $this->createStaffUser($role, $role.'_audit_access');

            $this->actingAs($user);

            $this->assertFalse(AuditLogResource::canAccess());
            $this->assertFalse(AuditLogResource::canViewAny());
        }
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
