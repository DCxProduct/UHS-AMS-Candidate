<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\EmailTemplates\EmailTemplateResource;
use App\Filament\Admin\Resources\SmsTemplates\SmsTemplateResource;
use App\Filament\Admin\Resources\WorkflowNotifications\WorkflowNotificationResource;
use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\CreateWorkflowStageType;
use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\EditWorkflowStageType;
use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\ListWorkflowStageTypes;
use App\Filament\Admin\Resources\WorkflowStageTypes\WorkflowStageTypeResource;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Models\WorkflowStageType;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\EditRole;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\CustomFormResource;
use Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use ReflectionProperty;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkflowStageTypeCrudPermissionsTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISSIONS = ['ViewAny:WorkflowStageType', 'Create:WorkflowStageType', 'Update:WorkflowStageType', 'Delete:WorkflowStageType'];

    private const FORM_BUILDER_RESOURCES = [CustomFormResource::class, DocumentTemplateResource::class, WorkflowNotificationResource::class, WorkflowStageTypeResource::class, EmailTemplateResource::class, SmsTemplateResource::class];

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        Notification::fake();
        foreach ([...self::PERMISSIONS, 'ViewAny:Role', 'Create:Role', 'Update:Role', 'Delete:Role'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
        Artisan::shouldReceive('call')->once()->andReturn(0);
        (new RolesAndPermissionsSeeder)->run();
    }

    public function test_seeded_non_admin_crud_role_can_create_edit_and_delete_unused_types_but_cannot_delete_protected_types(): void
    {
        $staff = $this->actor('stage-crud-user', 'crud');
        foreach (self::PERMISSIONS as $permission) {
            $this->assertTrue($staff->can($permission));
        }
        $this->assertTrue(WorkflowStageTypeResource::shouldRegisterNavigation());
        Livewire::test(ListWorkflowStageTypes::class)->assertActionVisible('create');
        Livewire::test(CreateWorkflowStageType::class)
            ->fillForm(['label_en' => 'CRUD configured review', 'label_km' => 'ការពិនិត្យសម្រាប់ CRUD', 'is_active' => true])
            ->set('data.enabled_actions', ['accept'])
            ->call('create')->assertHasNoFormErrors();
        $type = WorkflowStageType::query()->where('label_en', 'CRUD configured review')->firstOrFail();
        $list = Livewire::test(ListWorkflowStageTypes::class)
            ->assertTableActionVisible('edit', $type)
            ->assertTableActionVisible('delete', $type)
            ->assertTableActionEnabled('delete', $type);
        $this->assertSame(WorkflowStageTypeResource::getUrl('edit', ['record' => $type]), $list->instance()->getTable()->getRecordUrl($type));
        Livewire::test(EditWorkflowStageType::class, ['record' => $type->getRouteKey()])
            ->set('data.label_en', 'Renamed CRUD review')
            ->set('data.enabled_actions', ['accept', 'reject'])
            ->set('data.is_active', false)
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('Renamed CRUD review', $type->fresh()->label_en);
        $this->assertSame(['accept', 'reject'], $type->fresh()->enabled_actions);
        $this->assertFalse($type->fresh()->is_active);
        Livewire::test(ListWorkflowStageTypes::class)->callTableAction('delete', $type);
        $this->assertModelMissing($type);

        $used = $this->type('Referenced CRUD review');
        WorkflowNotification::query()->create(['name' => 'Referenced CRUD workflow', 'stages' => [['type' => 'stage', 'data' => ['stage_name' => 'Review', 'stage_type_id' => $used->getKey(), 'stage_type' => 'review']]]]);
        $builtin = WorkflowStageType::query()->where('is_system', true)->firstOrFail();
        foreach ([$builtin, $used] as $protected) {
            $this->assertFalse(WorkflowStageTypeResource::canDelete($protected));
            Livewire::test(ListWorkflowStageTypes::class)
                ->assertTableActionHidden('delete', $protected)
                ->mountTableAction('delete', $protected)->callMountedTableAction();
            $this->assertModelExists($protected);
            Livewire::test(EditWorkflowStageType::class, ['record' => $protected->getRouteKey()])
                ->assertActionHidden('delete')->call('mountAction', 'delete')->call('callMountedAction');
            $this->assertModelExists($protected);
        }
    }

    public function test_read_only_staff_access_and_delete_permission_are_separate_without_changing_other_roles(): void
    {
        foreach (['viewer', 'data_entry', 'delete', 'cashier_manager', 'cashier_officer', 'registrar_manager', 'registrar_officer'] as $name) {
            $this->assertSame([], Role::findByName($name, 'web')->permissions->filter(fn ($permission): bool => str_ends_with($permission->name, ':WorkflowStageType'))->pluck('name')->all());
        }
        $viewer = $this->actor('stage-viewer-user', 'viewer');
        $this->assertFalse(WorkflowStageTypeResource::shouldRegisterNavigation());
        $this->get(WorkflowStageTypeResource::getUrl('index'))->assertForbidden();
        $viewer->givePermissionTo(Permission::findOrCreate('ViewAny:WorkflowStageType', 'web'));
        $type = $this->type('Read only review');
        $this->assertTrue(WorkflowStageTypeResource::shouldRegisterNavigation());
        $list = Livewire::test(ListWorkflowStageTypes::class)
            ->assertCanSeeTableRecords([$type])
            ->assertActionHidden('create')
            ->assertTableActionHidden('edit', $type)
            ->assertTableActionHidden('delete', $type)
            ->mountTableAction('delete', $type)->callMountedTableAction();
        $this->assertModelExists($type);
        $this->assertNull($list->instance()->getTable()->getRecordUrl($type));
        $this->get(WorkflowStageTypeResource::getUrl('create'))->assertForbidden();
        $this->get(WorkflowStageTypeResource::getUrl('edit', ['record' => $type]))->assertForbidden();
        $viewer->givePermissionTo(Permission::findOrCreate('Delete:WorkflowStageType', 'web'));
        $this->assertFalse($viewer->can('Update:WorkflowStageType'));
        Livewire::test(ListWorkflowStageTypes::class)
            ->assertTableActionHidden('edit', $type)
            ->assertTableActionVisible('delete', $type)
            ->assertTableActionEnabled('delete', $type)
            ->callTableAction('delete', $type);
        $this->assertModelMissing($type);
    }

    public function test_update_permission_allows_editing_but_direct_delete_invocation_stays_blocked_until_delete_is_granted(): void
    {
        $role = Role::findOrCreate('stage_updater', 'web');
        $role->givePermissionTo(array_map(fn (string $permission) => Permission::findOrCreate($permission, 'web'), ['ViewAny:WorkflowStageType', 'Update:WorkflowStageType']));
        $this->actor('stage-update-user', 'stage_updater');
        $type = $this->type('Updater review');
        $this->get(WorkflowStageTypeResource::getUrl('create'))->assertForbidden();
        $this->get(WorkflowStageTypeResource::getUrl('edit', ['record' => $type]))->assertOk();
        Livewire::test(ListWorkflowStageTypes::class)
            ->assertTableActionVisible('edit', $type)
            ->assertTableActionHidden('delete', $type)
            ->mountTableAction('delete', $type)->callMountedTableAction();
        $this->assertModelExists($type);
        Livewire::test(EditWorkflowStageType::class, ['record' => $type->getRouteKey()])
            ->assertActionHidden('delete')->call('mountAction', 'delete')->call('callMountedAction');
        $this->assertModelExists($type);
        $role->givePermissionTo(Permission::findOrCreate('Delete:WorkflowStageType', 'web'));
        Livewire::test(ListWorkflowStageTypes::class)->assertTableActionVisible('delete', $type)->callTableAction('delete', $type);
        $this->assertModelMissing($type);
    }

    public function test_role_form_exposes_exactly_four_stage_type_permissions_and_saves_them_to_a_staff_role(): void
    {
        $staff = $this->actor('stage-role-editor', 'crud');
        $this->assertFalse($staff->can('ViewAny:WorkflowNotification'));
        $role = Role::query()->create(['name' => 'stage_permissions_target', 'guard_name' => 'web', 'label_en' => 'Stage permissions target', 'name_kh' => 'សិទ្ធិដំណាក់កាល', 'role_type_key' => 'staff']);
        foreach (['en', 'km'] as $locale) {
            app()->setLocale($locale);
            $component = Livewire::test(EditRole::class, ['record' => $role->getRouteKey()]);
            $this->assertSame(self::FORM_BUILDER_RESOURCES, $this->formBuilderPermissionOrder($component));
        }
        $field = collect($component->instance()->form->getFlatFields())->first(fn ($field): bool => $field instanceof CheckboxList && $field->getName() === WorkflowStageTypeResource::class);
        $this->assertNotNull($field);
        $this->assertEqualsCanonicalizing(self::PERMISSIONS, array_keys($field->getOptions()));
        $this->assertSame(['viewAny', 'create', 'update', 'delete'], config('filament-shield.resources.manage')[WorkflowStageTypeResource::class]);
        $component->set($field->getStatePath(), self::PERMISSIONS)->call('save')->assertHasNoFormErrors();
        $this->assertEqualsCanonicalizing(self::PERMISSIONS, $role->fresh()->permissions->pluck('name')->all());
        $staff = $this->actor('stage-assigned-user', 'stage_permissions_target');
        foreach (self::PERMISSIONS as $permission) {
            $this->assertTrue($staff->can($permission));
        }
    }

    public function test_role_permission_sections_follow_changed_resource_navigation_sort_without_a_fixed_model_order(): void
    {
        $this->actor('stage-navigation-editor', 'crud');
        $role = Role::query()->create(['name' => 'navigation_permissions_target', 'guard_name' => 'web', 'label_en' => 'Navigation permissions target', 'role_type_key' => 'staff']);
        $sort = new ReflectionProperty(WorkflowStageTypeResource::class, 'navigationSort');
        $original = $sort->getValue();

        try {
            $sort->setValue(null, 200);
            $component = Livewire::test(EditRole::class, ['record' => $role->getRouteKey()]);
            $this->assertSame([CustomFormResource::class, DocumentTemplateResource::class, WorkflowNotificationResource::class, EmailTemplateResource::class, SmsTemplateResource::class, WorkflowStageTypeResource::class], $this->formBuilderPermissionOrder($component));
        } finally {
            $sort->setValue(null, $original);
        }

        $this->assertSame($original, WorkflowStageTypeResource::getNavigationSort());
    }

    private function formBuilderPermissionOrder($component): array
    {
        return collect($component->instance()->form->getFlatFields())
            ->filter(fn ($field): bool => $field instanceof CheckboxList)
            ->map(fn (CheckboxList $field): string => $field->getName())
            ->filter(fn (string $resource): bool => in_array($resource, self::FORM_BUILDER_RESOURCES, true))
            ->values()->all();
    }

    private function actor(string $username, string $roleName): User
    {
        $user = User::query()->create(['registration_type' => 'admin', 'name' => $username, 'username' => $username, 'email' => $username.'@example.test', 'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true]);
        $user->assignRole(Role::findByName($roleName, 'web'));
        $this->assertFalse($user->hasEffectiveRole('admin'));
        $this->actingAs($user);

        return $user;
    }

    private function type(string $label): WorkflowStageType
    {
        return WorkflowStageType::query()->create(['label_en' => $label, 'label_km' => 'ខ្មែរ '.$label, 'behavior' => 'review', 'enabled_actions' => ['accept']]);
    }
}
