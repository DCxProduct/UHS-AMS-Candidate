<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\AuditLogs\AuditLogResource;
use App\Filament\Admin\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AuditLogsClearDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        $this->actingAs($this->staff(canClear: true));
    }

    public function test_clear_permanently_deletes_audit_history_and_logs_the_cleanup(): void
    {
        $log = $this->audit('Payments', 'created');
        $component = Livewire::test(ListAuditLogs::class)
            ->assertActionVisible('clearAuditLogs')
            ->assertCanSeeTableRecords([$log]);
        $this->assertTrue($component->instance()->getAction('clearAuditLogs')->isConfirmationRequired());

        $component->callAction('clearAuditLogs')->assertCanNotSeeTableRecords([$log]);

        $this->assertNull($log->fresh());
        $this->assertFalse(AuditLogResource::getEloquentQuery()->whereKey($log->id)->exists());
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'cleared',
            'module' => 'Audit Logs',
            'actor_id' => auth()->id(),
            'records_cleared_at' => null,
        ]);

    }

    public function test_clear_respects_active_filters_and_keeps_logs_outside_the_visible_scope(): void
    {
        $matching = $this->audit('Payments', 'created');
        $differentAction = $this->audit('Payments', 'updated');
        $differentModule = $this->audit('Custom Forms', 'created');
        $candidate = $this->audit('Payments', 'created', 'student');
        $archivedMatching = $this->audit('Payments', 'created');
        $archivedMatching->forceFill(['records_cleared_at' => now()->subMonth()])->save();
        $archivedDifferentModule = $this->audit('Custom Forms', 'created');
        $archivedDifferentModule->forceFill(['records_cleared_at' => now()->subMonth()])->save();

        Livewire::test(ListAuditLogs::class)
            ->filterTable('audit_log_filters', ['module' => 'Payments', 'action' => 'created'])
            ->assertCanSeeTableRecords([$matching])
            ->assertCanNotSeeTableRecords([$differentAction, $differentModule, $candidate, $archivedMatching])
            ->callAction('clearAuditLogs')
            ->assertCanNotSeeTableRecords([$matching]);

        $this->assertNull($matching->fresh());
        $this->assertNull($archivedMatching->fresh());
        $this->assertNull($differentAction->fresh()->records_cleared_at);
        $this->assertNull($differentModule->fresh()->records_cleared_at);
        $this->assertNull($candidate->fresh()->records_cleared_at);
        $this->assertNotNull($archivedDifferentModule->fresh()->records_cleared_at);
        $this->assertDatabaseCount('audit_logs', 5);
        $this->assertSame(4, AuditLogResource::getEloquentQuery()->count());
    }

    public function test_audit_view_permission_does_not_grant_clear_data(): void
    {
        auth()->logout();
        $viewer = $this->staff(canClear: false);
        $this->actingAs($viewer);
        $log = $this->audit('Payments', 'created');
        $before = $log->fresh()->getAttributes();

        $component = Livewire::test(ListAuditLogs::class)
            ->assertActionHidden('clearAuditLogs');
        $action = $component->instance()->getAction('clearAuditLogs')->getActionFunction();
        try {
            $action();
            $this->fail('Clear Data must reject an unauthorized direct invocation.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertFalse($viewer->can('ClearData:AuditLog'));
        $this->assertSame($before, $log->fresh()->getAttributes());
        $this->assertDatabaseCount('audit_logs', 1);
    }

    private function audit(string $module, string $action, string $actorRole = 'registrar'): AuditLog
    {
        return AuditLog::query()->forceCreate([
            'actor_type' => User::class,
            'actor_id' => auth()->id(),
            'actor_name' => 'Original staff member',
            'actor_role' => $actorRole,
            'module' => $module,
            'action' => $action,
            'description' => 'Original audit evidence',
            'old_values' => ['status' => 'pending'],
            'new_values' => ['status' => 'approved'],
            'metadata' => ['reference' => 12],
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
    }

    private function staff(bool $canClear): User
    {
        $name = $canClear ? 'registrar' : 'audit_viewer';
        $role = Role::findOrCreate($name, 'web');
        $role->givePermissionTo(Permission::findOrCreate('ViewAny:AuditLog', 'web'));
        if ($canClear) {
            $role->givePermissionTo(Permission::findOrCreate('ClearData:AuditLog', 'web'));
        }
        $user = User::query()->forceCreate([
            'registration_type' => 'admin',
            'name' => $name,
            'username' => $name,
            'email' => $name.'@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }
}
