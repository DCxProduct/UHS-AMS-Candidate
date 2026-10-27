<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class MefInvoiceCodeClearPermissionMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_upgrade_copies_existing_delete_grants_without_changing_other_permissions_or_settings(): void
    {
        $migration = $this->migration();
        $migration->down();
        $delete = Permission::findOrCreate('Delete:MefInvoiceCode', 'web');
        $view = Permission::findOrCreate('ViewAny:MefInvoiceCode', 'web');
        $editor = Role::findOrCreate('migration_code_editor', 'web');
        $editor->forceFill(['label_en' => 'Code Editor', 'restrict_custom_form_access' => true, 'allowed_custom_form_ids' => [7]])->save();
        $editor->givePermissionTo([$delete, $view]);
        $viewer = Role::findOrCreate('migration_code_viewer', 'web');
        $viewer->givePermissionTo($view);
        $directEditor = $this->user('migration_direct_editor');
        $directEditor->givePermissionTo([$delete, $view]);
        $directViewer = $this->user('migration_direct_viewer');
        $directViewer->givePermissionTo($view);

        $settings = $editor->fresh()->getAttributes();
        $rolesBefore = $this->grants('role_has_permissions');
        $usersBefore = $this->grants('model_has_permissions');
        $migration->up();
        $clear = Permission::findByName('ClearData:MefInvoiceCode', 'web');

        $this->assertTrue($editor->fresh()->hasPermissionTo($clear));
        $this->assertTrue($directEditor->fresh()->hasDirectPermission($clear));
        $this->assertFalse($viewer->fresh()->hasPermissionTo($clear));
        $this->assertFalse($directViewer->fresh()->hasDirectPermission($clear));
        $this->assertSame($settings, $editor->fresh()->getAttributes());
        $this->assertSame($rolesBefore, $this->grants('role_has_permissions', $clear->getKey()));
        $this->assertSame($usersBefore, $this->grants('model_has_permissions', $clear->getKey()));

        $rolesAfter = $this->grants('role_has_permissions');
        $usersAfter = $this->grants('model_has_permissions');
        $migration->up();
        $this->assertSame($rolesAfter, $this->grants('role_has_permissions'));
        $this->assertSame($usersAfter, $this->grants('model_has_permissions'));
        $this->assertSame(1, Permission::query()->where('name', 'ClearData:MefInvoiceCode')->where('guard_name', 'web')->count());
    }

    public function test_rollback_removes_only_the_clear_permission_and_keeps_delete_grants(): void
    {
        $migration = $this->migration();
        $delete = Permission::findOrCreate('Delete:MefInvoiceCode', 'web');
        $view = Permission::findOrCreate('ViewAny:MefInvoiceCode', 'web');
        $role = Role::findOrCreate('migration_rollback_editor', 'web');
        $role->givePermissionTo([$delete, $view]);
        $user = $this->user('migration_rollback_direct');
        $user->givePermissionTo([$delete, $view]);
        $migration->up();
        $clearId = Permission::findByName('ClearData:MefInvoiceCode', 'web')->getKey();

        $migration->down();

        $this->assertDatabaseMissing('permissions', ['name' => 'ClearData:MefInvoiceCode', 'guard_name' => 'web']);
        $this->assertDatabaseMissing('role_has_permissions', ['permission_id' => $clearId]);
        $this->assertDatabaseMissing('model_has_permissions', ['permission_id' => $clearId]);
        $this->assertTrue($role->fresh()->hasPermissionTo($delete));
        $this->assertTrue($role->fresh()->hasPermissionTo($view));
        $this->assertTrue($user->fresh()->hasDirectPermission($delete));
        $this->assertTrue($user->fresh()->hasDirectPermission($view));
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_27_000002_add_clear_data_permission_to_mef_invoice_codes.php');
    }

    private function grants(string $table, int|string|null $exceptPermission = null): string
    {
        return DB::table($table)
            ->when($exceptPermission !== null, fn ($query) => $query->where('permission_id', '!=', $exceptPermission))
            ->orderBy('permission_id')
            ->orderBy($table === 'role_has_permissions' ? 'role_id' : 'model_id')
            ->get()->toJson();
    }

    private function user(string $username): User
    {
        return User::query()->forceCreate([
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
