<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\WorkflowButtons\Pages\CreateWorkflowButton;
use App\Filament\Admin\Resources\WorkflowButtons\Pages\EditWorkflowButton;
use App\Filament\Admin\Resources\WorkflowButtons\Pages\ListWorkflowButtons;
use App\Filament\Admin\Resources\WorkflowButtons\WorkflowButtonResource;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowButton;
use App\Models\WorkflowStageType;
use App\Support\FilamentActionPermissions;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\EditRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkflowButtonCrudPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISSIONS = ['ViewAny:WorkflowButton', 'Create:WorkflowButton', 'Update:WorkflowButton', 'Delete:WorkflowButton'];

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        Notification::fake();
        FilamentActionPermissions::sync();
        Artisan::shouldReceive('call')->once()->andReturn(0);
        (new RolesAndPermissionsSeeder)->run();
    }

    public function test_permission_sync_registers_exactly_the_four_button_crud_permissions(): void
    {
        $this->assertSame(['view_any', 'create', 'update', 'delete'], FilamentActionPermissions::resourceActions()[WorkflowButtonResource::class]);
        $this->assertSame(['viewAny', 'create', 'update', 'delete'], config('filament-shield.resources.manage')[WorkflowButtonResource::class]);
        $this->assertEqualsCanonicalizing(self::PERMISSIONS, Permission::query()->where('name', 'like', '%:WorkflowButton')->pluck('name')->all());

        FilamentActionPermissions::sync();
        $this->assertEqualsCanonicalizing(self::PERMISSIONS, Permission::query()->where('name', 'like', '%:WorkflowButton')->pluck('name')->all());
    }

    public function test_registrar_accountant_and_student_roles_receive_no_button_grants_or_direct_route_access(): void
    {
        $button = $this->button('Private workflow button');
        foreach (['registrar_officer', 'registrar_manager', 'cashier_officer', 'cashier_manager', 'accountant', 'candidate'] as $name) {
            $role = Role::findOrCreate($name, 'web');
            $this->assertSame([], $role->permissions->filter(fn ($permission): bool => str_ends_with($permission->name, ':WorkflowButton'))->pluck('name')->all());
            $user = $this->actor('button-ungranted-'.$name, $name, $name === 'candidate' ? 'student' : 'admin');
            foreach (self::PERMISSIONS as $permission) {
                $this->assertFalse($user->can($permission));
            }
            $this->assertFalse(WorkflowButtonResource::shouldRegisterNavigation());
            $response = $this->get(WorkflowButtonResource::getUrl('index'));
            $this->assertSame(403, $response->getStatusCode(), $name.' redirected to '.$response->headers->get('Location'));
            $this->get(WorkflowButtonResource::getUrl('create'))->assertForbidden();
            $this->get(WorkflowButtonResource::getUrl('edit', ['record' => $button]))->assertForbidden();
        }
    }

    public function test_view_only_staff_can_list_buttons_but_cannot_create_edit_or_delete_them(): void
    {
        $this->actorWithPermissions('button-read-only', ['ViewAny']);
        $button = $this->button('Read only button');
        $this->assertTrue(WorkflowButtonResource::shouldRegisterNavigation());
        $this->get(WorkflowButtonResource::getUrl('index'))->assertOk();
        $list = Livewire::test(ListWorkflowButtons::class)
            ->assertCanSeeTableRecords([$button])
            ->assertActionHidden('create')
            ->assertTableActionHidden('edit', $button)
            ->assertTableActionHidden('delete', $button)
            ->mountTableAction('delete', $button)->callMountedTableAction();
        $this->assertNull($list->instance()->getTable()->getRecordUrl($button));
        $this->assertModelExists($button);
        $this->get(WorkflowButtonResource::getUrl('create'))->assertForbidden();
        $this->get(WorkflowButtonResource::getUrl('edit', ['record' => $button]))->assertForbidden();
    }

    public function test_create_permission_allows_creation_without_implicitly_allowing_update_or_delete(): void
    {
        $this->actorWithPermissions('button-create-only', ['ViewAny', 'Create']);
        $this->get(WorkflowButtonResource::getUrl('create'))->assertOk();
        Livewire::test(ListWorkflowButtons::class)->assertActionVisible('create');
        Livewire::test(CreateWorkflowButton::class)
            ->fillForm(['label_en' => 'Created by button creator', 'action_key' => 'accept'])
            ->call('create')->assertHasNoFormErrors();
        $button = WorkflowButton::query()->where('label_en', 'Created by button creator')->sole();

        Livewire::test(ListWorkflowButtons::class)
            ->assertTableActionHidden('edit', $button)
            ->assertTableActionHidden('delete', $button)
            ->mountTableAction('delete', $button)->callMountedTableAction();
        $this->assertModelExists($button);
        $this->get(WorkflowButtonResource::getUrl('edit', ['record' => $button]))->assertForbidden();
    }

    public function test_update_permission_allows_editing_without_creation_or_direct_deletion(): void
    {
        $this->actorWithPermissions('button-update-only', ['ViewAny', 'Update']);
        $button = $this->button('Editable button');
        $this->get(WorkflowButtonResource::getUrl('create'))->assertForbidden();
        $this->get(WorkflowButtonResource::getUrl('edit', ['record' => $button]))->assertOk();
        $list = Livewire::test(ListWorkflowButtons::class)
            ->assertActionHidden('create')
            ->assertTableActionVisible('edit', $button)
            ->assertTableActionHidden('delete', $button)
            ->mountTableAction('delete', $button)->callMountedTableAction();
        $this->assertSame(WorkflowButtonResource::getUrl('edit', ['record' => $button]), $list->instance()->getTable()->getRecordUrl($button));

        Livewire::test(EditWorkflowButton::class, ['record' => $button->getRouteKey()])
            ->set('data.label_en', 'Updated by button updater')
            ->call('save')->assertHasNoFormErrors()
            ->assertActionHidden('delete')->call('mountAction', 'delete')->call('callMountedAction');
        $this->assertModelExists($button);
        $this->assertSame('Updated by button updater', $button->fresh()->label_en);
    }

    public function test_delete_permission_is_independent_of_update_and_still_protects_referenced_buttons(): void
    {
        $user = $this->actorWithPermissions('button-delete-only', ['ViewAny', 'Delete']);
        $unused = $this->button('Unused removable button');
        $this->assertFalse($user->can('Update:WorkflowButton'));
        $this->get(WorkflowButtonResource::getUrl('create'))->assertForbidden();
        $this->get(WorkflowButtonResource::getUrl('edit', ['record' => $unused]))->assertForbidden();
        Livewire::test(ListWorkflowButtons::class)
            ->assertActionHidden('create')
            ->assertTableActionHidden('edit', $unused)
            ->assertTableActionVisible('delete', $unused)
            ->assertTableActionEnabled('delete', $unused)
            ->callTableAction('delete', $unused);
        $this->assertModelMissing($unused);

        $used = $this->button('Referenced protected button');
        WorkflowStageType::query()->create([
            'label_en' => 'Referenced protected stage', 'label_km' => 'ដំណាក់កាលការពារ',
            'behavior' => 'review', 'workflow_button_ids' => [$used->getKey()],
        ]);
        $this->assertFalse(WorkflowButtonResource::canDelete($used));
        Livewire::test(ListWorkflowButtons::class)
            ->assertTableActionHidden('delete', $used)
            ->mountTableAction('delete', $used)->callMountedTableAction();
        $this->assertModelExists($used);
    }

    public function test_role_form_exposes_only_four_button_permissions_and_saves_only_the_selected_grants(): void
    {
        $editorRole = Role::findOrCreate('button-role-editor', 'web');
        foreach (['ViewAny:Role', 'Update:Role'] as $permission) {
            $editorRole->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $this->actor('button-role-editor-user', $editorRole->name);
        $target = Role::query()->create(['name' => 'button-permission-target', 'guard_name' => 'web', 'label_en' => 'Button permission target', 'role_type_key' => 'staff']);
        foreach (['en', 'km'] as $locale) {
            app()->setLocale($locale);
            $component = Livewire::test(EditRole::class, ['record' => $target->getRouteKey()]);
            $field = collect($component->instance()->form->getFlatFields())->first(fn ($field): bool => $field instanceof CheckboxList && $field->getName() === WorkflowButtonResource::class);
            $this->assertNotNull($field);
            $this->assertEqualsCanonicalizing(self::PERMISSIONS, array_keys($field->getOptions()));
        }

        $selected = ['ViewAny:WorkflowButton', 'Update:WorkflowButton'];
        $component->set($field->getStatePath(), $selected)->call('save')->assertHasNoFormErrors();
        $this->assertEqualsCanonicalizing($selected, $target->fresh()->permissions->pluck('name')->all());
        $assigned = $this->actor('button-assigned-updater', $target->name);
        foreach (self::PERMISSIONS as $permission) {
            $this->assertSame(in_array($permission, $selected, true), $assigned->can($permission));
        }
    }

    private function actorWithPermissions(string $username, array $verbs): User
    {
        $role = Role::findOrCreate($username.'-role', 'web');
        foreach ($verbs as $verb) {
            $role->givePermissionTo(Permission::findOrCreate($verb.':WorkflowButton', 'web'));
        }

        return $this->actor($username, $role->name);
    }

    private function actor(string $username, string $roleName, string $registrationType = 'admin'): User
    {
        $user = User::query()->create([
            'registration_type' => $registrationType, 'name' => $username, 'username' => $username,
            'email' => $username.'@example.test', 'password' => 'password',
            'date_of_birth' => '2000-01-01', 'is_active' => true,
        ]);
        $user->assignRole(Role::findByName($roleName, 'web'));
        $this->assertFalse($user->hasEffectiveRole('admin'));
        $this->flushSession();
        $this->actingAs($user);

        return $user;
    }

    private function button(string $label): WorkflowButton
    {
        return WorkflowButton::query()->create(['label_en' => $label, 'action_key' => 'accept']);
    }
}
