<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\WorkflowStageTypes\WorkflowStageTypeResource;
use App\Models\Role;
use App\Models\User;
use App\Support\AdminRolePermissions;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\CreateRole;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\EditRole;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\ViewRole;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminRolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    private const STAGE_PERMISSIONS = ['ViewAny:WorkflowStageType', 'Create:WorkflowStageType', 'Update:WorkflowStageType', 'Delete:WorkflowStageType'];

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        Notification::fake();
    }

    public function test_admin_and_permission_creation_events_work_in_both_orders_and_only_with_matching_guards(): void
    {
        $web = $this->role('admin');
        $late = Permission::findOrCreate('Export:LateWebReport', 'web');
        $this->assertTrue($web->fresh()->hasPermissionTo($late));
        $apiPermission = Permission::findOrCreate('Export:ApiOnlyReport', 'api');
        $api = $this->role('AdMiN', 'api');
        $this->assertTrue($api->fresh()->hasPermissionTo($apiPermission));
        $this->assertSame($this->permissionNames('web'), $web->fresh()->permissions->pluck('name')->sort()->values()->all());
        $this->assertSame($this->permissionNames('api'), $api->fresh()->permissions->pluck('name')->sort()->values()->all());
        $this->assertFalse($web->fresh()->permissions->contains('id', $apiPermission->getKey()));
        $this->assertFalse($api->fresh()->permissions->contains('id', $late->getKey()));
    }

    public function test_sync_is_additive_idempotent_refreshes_loaded_permissions_and_leaves_non_admin_roles_unchanged(): void
    {
        $extra = Permission::findOrCreate('Export:PrivateReport', 'web');
        $admin = $this->role('admin');
        $viewer = $this->role('viewer');
        $viewer->givePermissionTo($extra);
        $viewerBefore = $viewer->fresh()->permissions->pluck('id')->all();
        $admin->syncPermissions([$extra]);
        $admin->load('permissions');
        AdminRolePermissions::sync($admin, ['Manage:RegisteredAfterSeed']);
        $this->assertSame($this->permissionNames('web'), $admin->permissions->pluck('name')->sort()->values()->all());
        $this->assertTrue($admin->permissions->contains('name', 'Manage:RegisteredAfterSeed'));
        $this->assertTrue($admin->permissions->contains('id', $extra->getKey()));
        $before = $admin->permissions->pluck('id')->sort()->values()->all();
        AdminRolePermissions::sync($admin, ['Manage:RegisteredAfterSeed']);
        $this->assertSame($before, $admin->permissions->pluck('id')->sort()->values()->all());
        $this->assertSame($viewerBefore, $viewer->fresh()->permissions->pluck('id')->all());
        $this->assertSame(1, Permission::query()->where('guard_name', 'web')->where('name', 'Manage:RegisteredAfterSeed')->count());
    }

    public function test_permission_creation_and_non_admin_sync_never_create_or_upgrade_an_admin_role(): void
    {
        $permission = Permission::findOrCreate('ViewAny:NoAdminYet', 'web');
        $custom = $this->role('administrator');
        $custom->givePermissionTo($permission);
        $before = $custom->fresh()->permissions->pluck('id')->all();
        AdminRolePermissions::sync($custom, ['Manage:NotForThisRole']);
        $this->assertFalse(Role::query()->whereRaw('LOWER(name) = ?', ['admin'])->exists());
        $this->assertSame($before, $custom->fresh()->permissions->pluck('id')->all());
        $this->assertFalse(AdminRolePermissions::isAdmin($custom));
        $this->assertFalse(Permission::query()->where('name', 'Manage:NotForThisRole')->exists());
    }

    public function test_admin_view_repairs_missing_grants_and_shows_every_permission_checked_and_disabled(): void
    {
        $admin = $this->role('admin');
        $this->authenticate($admin, 'admin-permissions-viewer');
        $admin->syncPermissions([]);
        $component = Livewire::test(ViewRole::class, ['record' => $admin->getRouteKey()])->assertSet('data.select_all', true);
        $permissionFields = collect($component->instance()->form->getFlatFields(withHidden: true))
            ->filter(fn ($field): bool => $field instanceof CheckboxList && $field->getName() !== 'allowed_custom_form_ids' && $field->getOptions() !== []);
        $this->assertGreaterThan(1, $permissionFields->count());
        foreach ($permissionFields as $field) {
            $this->assertEqualsCanonicalizing(array_keys($field->getOptions()), $field->getState(), $field->getName());
            $this->assertTrue($field->isDisabled(), $field->getName());
        }
        $stage = $permissionFields->first(fn (CheckboxList $field): bool => $field->getName() === WorkflowStageTypeResource::class);
        $this->assertNotNull($stage);
        $this->assertEqualsCanonicalizing(self::STAGE_PERMISSIONS, $stage->getState());
        $this->assertSame($this->permissionNames('web'), $admin->fresh()->permissions->pluck('name')->sort()->values()->all());
    }

    public function test_admin_metadata_save_ignores_omitted_permissions_and_posted_identity_changes_without_losing_custom_rights(): void
    {
        $custom = Permission::findOrCreate('Export:UnlistedPrivateReport', 'web');
        $apiPermission = Permission::findOrCreate('Manage:OtherGuardOnly', 'api');
        $admin = $this->role('admin');
        $this->authenticate($admin, 'admin-permissions-editor');
        Livewire::test(EditRole::class, ['record' => $admin->getRouteKey()])
            ->set('data', ['name' => 'renamed_admin', 'guard_name' => 'api', 'label_en' => 'Updated admin label', 'name_kh' => 'អ្នកគ្រប់គ្រងថ្មី', 'role_type_key' => 'staff', 'select_all' => false])
            ->call('save')->assertHasNoFormErrors();
        $admin->refresh();
        $this->assertSame('admin', $admin->name);
        $this->assertSame('web', $admin->guard_name);
        $this->assertSame('Updated admin label', $admin->label_en);
        $this->assertSame($this->permissionNames('web'), $admin->permissions->pluck('name')->sort()->values()->all());
        $this->assertTrue($admin->permissions->contains('id', $custom->getKey()));
        $this->assertFalse($admin->permissions->contains('id', $apiPermission->getKey()));
        $this->assertDatabaseMissing('roles', ['name' => 'renamed_admin']);
    }

    public function test_creating_admin_through_the_role_form_keeps_all_permissions_after_the_create_lifecycle(): void
    {
        $custom = Permission::findOrCreate('Export:BeforeAdminCreation', 'web');
        $manager = $this->role('role_manager');
        foreach (['ViewAny:Role', 'Create:Role'] as $permission) {
            $manager->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $actor = $this->authenticate($manager, 'non-admin-role-creator');
        $this->assertFalse($actor->hasEffectiveRole('admin'));
        Livewire::test(CreateRole::class)
            ->fillForm(['name' => 'admin', 'label_en' => 'Admin created in form', 'name_kh' => 'អ្នកគ្រប់គ្រង', 'role_type_key' => 'staff'])
            ->call('create')->assertHasNoFormErrors();
        $admin = Role::query()->where('name', 'admin')->where('guard_name', 'web')->sole();
        $this->assertSame($this->permissionNames('web'), $admin->permissions->pluck('name')->sort()->values()->all());
        $this->assertTrue($admin->permissions->contains('id', $custom->getKey()));
        foreach (self::STAGE_PERMISSIONS as $permission) {
            $this->assertTrue($admin->hasPermissionTo($permission));
        }
        $this->assertFalse($actor->fresh()->hasEffectiveRole('admin'));
    }

    private function role(string $name, string $guard = 'web'): Role
    {
        return Role::query()->create(['name' => $name, 'guard_name' => $guard, 'label_en' => $name, 'role_type_key' => 'staff']);
    }

    private function authenticate(Role $role, string $username): User
    {
        $user = User::query()->create(['registration_type' => 'admin', 'name' => $username, 'username' => $username, 'email' => $username.'@example.test', 'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true]);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function permissionNames(string $guard): array
    {
        return Permission::query()->where('guard_name', $guard)->pluck('name')->sort()->values()->all();
    }
}
