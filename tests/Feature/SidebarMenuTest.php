<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\CandidateEntranceStatistics\CandidateEntranceStatisticResource;
use App\Filament\Admin\Resources\CandidateExitStatistics\CandidateExitStatisticResource;
use App\Filament\Admin\Resources\Payments\PaymentResource;
use App\Filament\Admin\Resources\PaymentStatusLists\PaymentStatusListResource;
use App\Filament\Admin\Resources\SidebarMenus\Pages\EditSidebarMenu;
use App\Filament\Admin\Resources\SidebarMenus\Pages\ListSidebarMenus;
use App\Filament\Admin\Resources\SidebarMenus\SidebarMenuResource;
use App\Filament\Pages\Dashboard;
use App\Models\Role;
use App\Models\SidebarMenu;
use App\Models\SidebarMenuName;
use App\Models\User;
use App\Support\FilamentActionPermissions;
use App\Support\SidebarMenuIcons;
use App\Support\SidebarNavigation;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\EditRole;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SidebarMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        FilamentActionPermissions::sync();
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public function test_resource_keeps_system_menus_and_groups_protected_and_view_disabled(): void
    {
        $admin = $this->actor('admin');
        $role = $admin->roles()->first();
        $record = $this->setting($role, 'item:'.Dashboard::class, ['label_en' => 'Overview', 'label_km' => 'ទិដ្ឋភាពទូទៅ']);
        Livewire::test(EditSidebarMenu::class, ['record' => $record->id])
            ->assertFormFieldDoesNotExist('role_id')
            ->assertSee(__('sidebar_menus.form_section'))
            ->fillForm(['label_en' => 'Updated overview'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Updated overview', $record->fresh()->label_en);
        Livewire::test(ListSidebarMenus::class)->assertActionHidden('create')
            ->assertActionHidden(TestAction::make('delete')->table($record));
        $heading = $this->setting($role, 'group:dashboard');
        Livewire::test(ListSidebarMenus::class)->set('activeTab', 'groups')
            ->assertActionVisible('create')
            ->assertActionHidden(TestAction::make('delete')->table($heading));
        foreach ([$record, $heading] as $setting) {
            $this->assertFalse(SidebarMenuResource::canDelete($setting));
            $this->assertModelExists($setting);
        }
        $this->assertFalse(SidebarMenuResource::canDeleteAny());
        $this->assertFalse(SidebarMenuResource::canView($record));
        $this->assertArrayNotHasKey('view', SidebarMenuResource::getPages());
        $this->assertTrue(SidebarMenuResource::canCreate());
        $this->assertArrayNotHasKey('create', SidebarMenuResource::getPages());
        $this->get(SidebarMenuResource::getUrl('index').'/create')->assertNotFound();
    }

    public function test_group_create_delete_and_navigation_permissions_are_registered_and_assignable_in_role_resource(): void
    {
        $this->actor('admin');
        $expected = ['ViewAny:SidebarMenu', 'Create:SidebarMenu', 'Update:SidebarMenu', 'Delete:SidebarMenu', 'Reorder:SidebarMenu'];
        $this->assertEqualsCanonicalizing($expected, array_keys(FilamentShield::getResourcePermissionsWithLabels(SidebarMenuResource::class)));
        $this->assertFalse(config('filament-shield.shield_resource.tabs.custom_permissions'));
        $this->assertSame([], config('filament-shield.custom_permissions'));
        $role = Role::query()->create(['name' => 'sidebar_operator', 'label_en' => 'Sidebar Operator', 'guard_name' => 'web', 'role_type_key' => 'staff']);
        Livewire::test(EditRole::class, ['record' => $role->id])
            ->set('data.'.SidebarMenuResource::class, $expected)->call('save')->assertHasNoFormErrors();
        foreach ($expected as $permission) {
            $this->assertTrue($role->fresh()->hasPermissionTo($permission));
        }
    }

    public function test_permission_sync_retires_view_and_bulk_delete_but_keeps_group_create_and_delete_grants(): void
    {
        $admin = $this->actor('admin');
        $role = Role::findOrCreate('legacy_sidebar_creator', 'web');
        $retired = ['View:SidebarMenu', 'DeleteAny:SidebarMenu'];
        $role->givePermissionTo(Permission::findOrCreate('Create:SidebarMenu', 'web'));
        $role->givePermissionTo(Permission::findOrCreate('Delete:SidebarMenu', 'web'));
        $permissionIds = [];
        foreach ($retired as $name) {
            $permission = Permission::findOrCreate($name, 'web');
            $role->givePermissionTo($permission);
            $permissionIds[] = $permission->id;
        }
        FilamentActionPermissions::sync();
        foreach ($permissionIds as $permissionId) {
            $this->assertDatabaseMissing('permissions', ['id' => $permissionId]);
            $this->assertDatabaseMissing('role_has_permissions', ['role_id' => $role->id, 'permission_id' => $permissionId]);
        }
        foreach ($retired as $name) {
            $this->assertNotContains($name, FilamentActionPermissions::all());
        }
        $this->assertTrue(SidebarMenuResource::canCreate());
        $this->assertTrue($role->fresh()->hasPermissionTo('Create:SidebarMenu'));
        $this->assertTrue($role->fresh()->hasPermissionTo('Delete:SidebarMenu'));
        $this->assertTrue($admin->hasEffectiveRole('admin'));
    }

    public function test_authorized_role_can_delete_empty_custom_group_globally_from_groups_tab(): void
    {
        $admin = $this->actor('admin');
        $navigation = app(SidebarNavigation::class);
        $created = $navigation->createGroup(['label_en' => 'Disposable group', 'label_km' => 'ក្រុមថ្មី', 'sort_order' => 1]);
        $copy = $this->setting(Role::findOrCreate('legacy_copy', 'web'), $created->target_key);
        $menu = $this->setting($admin->roles()->first(), 'item:'.Dashboard::class);
        $this->assertArrayHasKey($created->target_key, $navigation->groupOptions());
        $this->actor('group_deleter', ['ViewAny:SidebarMenu', 'Delete:SidebarMenu']);
        $action = TestAction::make('delete')->table($created);
        Livewire::test(ListSidebarMenus::class)->assertActionHidden(TestAction::make('delete')->table($menu));
        Livewire::test(ListSidebarMenus::class)->set('activeTab', 'groups')
            ->assertActionVisible($action)->assertActionEnabled($action)
            ->assertActionExists($action, fn ($action): bool => $action->isConfirmationRequired())
            ->callAction($action)->assertHasNoActionErrors()
            ->assertRedirect(SidebarMenuResource::getUrl('index', ['tab' => 'groups']));
        $this->assertModelMissing($created);
        $this->assertModelMissing($copy);
        $this->assertModelExists($menu);
        $this->assertDatabaseMissing('sidebar_menu_names', ['target_key' => $created->target_key]);
        $this->assertArrayNotHasKey($created->target_key, $navigation->groupOptions());
        $this->assertSame([], $navigation->apply([], $admin));
        $this->assertFalse(SidebarMenuResource::canDeleteAny());
    }

    public function test_delete_group_requires_delete_permission(): void
    {
        $this->actor('admin');
        $created = app(SidebarNavigation::class)->createGroup(['label_en' => 'Reports', 'label_km' => 'របាយការណ៍', 'sort_order' => 1]);
        $this->actor('group_editor', ['ViewAny:SidebarMenu', 'Create:SidebarMenu', 'Update:SidebarMenu']);
        $this->assertFalse(SidebarMenuResource::canDelete($created));
        Livewire::test(ListSidebarMenus::class)->set('activeTab', 'groups')
            ->assertActionHidden(TestAction::make('delete')->table($created))
            ->call('mountAction', 'delete', [], ['table' => true, 'recordKey' => (string) $created->id])
            ->assertSet('mountedActions', []);
        $this->assertModelExists($created);
        $this->expectException(AuthorizationException::class);
        app(SidebarNavigation::class)->deleteGroup($created);
    }

    public function test_occupied_groups_cannot_be_deleted_and_system_targets_are_protected_even_for_admin(): void
    {
        $admin = $this->actor('admin');
        $navigation = app(SidebarNavigation::class);
        $created = $navigation->createGroup(['label_en' => 'Reports', 'label_km' => 'របាយការណ៍', 'sort_order' => 1]);
        $menu = $this->setting($admin->roles()->first(), 'item:'.Dashboard::class, ['navigation_group' => $created->target_key]);
        $systemGroup = $this->setting($admin->roles()->first(), 'group:dashboard');
        Livewire::test(ListSidebarMenus::class)->set('activeTab', 'groups')
            ->assertActionDisabled(TestAction::make('delete')->table($created));
        $this->assertFalse($navigation->deleteGroup($created));
        $this->assertModelExists($created);
        foreach ([$menu, $systemGroup] as $protected) {
            $this->assertFalse(SidebarMenuResource::canDelete($protected));
            try {
                $navigation->deleteGroup($protected);
                $this->fail('System targets must not be deletable.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
            $this->assertModelExists($protected);
        }
        $navigation->updateAtPosition($menu, ['navigation_group' => 'group:dashboard', 'sort_order' => 1]);
        $this->assertTrue($navigation->deleteGroup($created));
        $this->assertSame('group:dashboard', $menu->fresh()->navigation_group);
        $this->assertModelExists($menu);
    }

    public function test_group_creation_is_bilingual_inserts_at_position_and_ignores_forged_menu_and_role_fields(): void
    {
        $user = $this->actor('group_creator', ['ViewAny:SidebarMenu', 'Create:SidebarMenu']);
        $role = $user->roles()->first();
        $dashboard = $this->setting($role, 'group:dashboard', ['sort_order' => 1]);
        $settings = $this->setting($role, 'group:settings', ['sort_order' => 2]);
        $navigation = app(SidebarNavigation::class);
        $navigation->catalog(); // Creation must also invalidate metadata already loaded by the table.
        $component = Livewire::test(ListSidebarMenus::class)->set('activeTab', 'groups')
            ->callAction('create', data: ['label_en' => '', 'label_km' => '', 'sort_order' => 0])
            ->assertHasActionErrors(['label_en' => 'required', 'label_km' => 'required', 'sort_order' => 'min']);
        $component->fillForm([
            'label_en' => 'New reports', 'label_km' => 'របាយការណ៍ថ្មី', 'sort_order' => 2,
            'target_key' => 'item:forged-menu', 'role_id' => 999999, 'navigation_group' => 'group:settings',
        ])->callMountedAction()->assertHasNoActionErrors()
            ->assertRedirect(SidebarMenuResource::getUrl('index', ['tab' => 'groups']));
        $created = SidebarMenu::query()->where('target_key', 'like', SidebarMenu::CUSTOM_GROUP_TARGET_PREFIX.'%')->sole();
        $this->assertSame($role->id, $created->role_id);
        $this->assertNull($created->navigation_group);
        $this->assertSame(2, $navigation->displayPosition($created));
        $this->assertSame(3, $settings->fresh()->sort_order);
        $this->assertDatabaseMissing('sidebar_menus', ['target_key' => 'item:forged-menu']);
        Livewire::test(ListSidebarMenus::class)->set('activeTab', 'groups')
            ->assertCanSeeTableRecords([$dashboard, $created, $settings], inOrder: true);
        foreach (['en' => 'New reports', 'km' => 'របាយការណ៍ថ្មី'] as $locale => $label) {
            app()->setLocale($locale);
            $this->assertSame($label, $navigation->groupOptions()[$created->target_key]);
        }
    }

    public function test_create_group_requires_permission_and_cannot_be_called_from_menus_tab(): void
    {
        foreach ([['ViewAny:SidebarMenu'], ['ViewAny:SidebarMenu', 'Update:SidebarMenu']] as $index => $permissions) {
            $this->actor('without_create_'.$index, $permissions);
            Livewire::test(ListSidebarMenus::class)->set('activeTab', 'groups')->assertActionHidden('create')
                ->call('mountAction', 'create')->assertSet('mountedActions', []);
            $this->assertDatabaseCount('sidebar_menus', 0);
        }
        $this->actor('creator', ['ViewAny:SidebarMenu', 'Create:SidebarMenu']);
        Livewire::test(ListSidebarMenus::class)->assertActionHidden('create')
            ->call('mountAction', 'create')->assertSet('mountedActions', []);
        $this->assertDatabaseCount('sidebar_menus', 0);
    }

    public function test_custom_groups_support_moving_renaming_reordering_and_all_roles_without_adding_unauthorized_menus(): void
    {
        $admin = $this->actor('admin');
        $role = $admin->roles()->first();
        $dashboard = $this->setting($role, 'group:dashboard', ['sort_order' => 1]);
        $menu = $this->setting($role, 'item:'.Dashboard::class);
        $navigation = app(SidebarNavigation::class);
        $created = $navigation->createGroup(['label_en' => 'Dashboard', 'label_km' => 'ក្រុមថ្មី', 'sort_order' => 2]);
        Livewire::test(EditSidebarMenu::class, ['record' => $menu->id])
            ->fillForm(['navigation_group' => $created->target_key, 'sort_order' => 1])->call('save')->assertHasNoFormErrors();
        Livewire::test(EditSidebarMenu::class, ['record' => $created->id])
            ->assertFormFieldDoesNotExist('navigation_group')->assertFormFieldDoesNotExist('target_key')
            ->fillForm(['label_en' => 'Shared reports', 'label_km' => 'របាយការណ៍រួម', 'sort_order' => 1])->call('save')->assertHasNoFormErrors();
        Livewire::test(ListSidebarMenus::class)->set('activeTab', 'groups')
            ->assertCanSeeTableRecords([$created, $dashboard], inOrder: true);
        $registrar = $this->actor('registrar');
        $newUser = $this->actor('new_role');
        $groups = [NavigationGroup::make(__('navigation.groups.dashboard'))->items([
            NavigationItem::make('Overview')->key(Dashboard::class)->url('/dashboard')->badge('7'),
        ])];
        foreach (['en' => 'Shared reports', 'km' => 'របាយការណ៍រួម'] as $locale => $label) {
            app()->setLocale($locale);
            foreach ([$admin, $registrar, $newUser] as $user) {
                $result = $navigation->apply($groups, $user);
                $this->assertCount(1, $result);
                $this->assertSame($label, $result[0]->getLabel());
                $this->assertSame('/dashboard', $result[0]->getItems()[0]->getUrl());
                $this->assertSame('7', $result[0]->getItems()[0]->getBadge());
                $emptyGroups = $navigation->apply([], $user);
                $this->assertCount(1, $emptyGroups);
                $this->assertSame($label, $emptyGroups[0]->getLabel());
                $this->assertSame([], $emptyGroups[0]->getItems());
            }
        }
        $this->assertSame($created->target_key, $menu->fresh()->navigation_group);
        $this->assertDatabaseMissing('sidebar_menus', ['role_id' => $newUser->roles()->first()->id]);
    }

    public function test_new_eighth_group_is_rendered_in_sidebar_without_assigning_any_menus(): void
    {
        $admin = $this->actor('admin');
        $navigation = app(SidebarNavigation::class);
        $navigation->populateForRole($admin->roles()->first()->id);
        $created = $navigation->createGroup([
            'label_en' => 'Empty reports', 'label_km' => 'របាយការណ៍ថ្មី', 'sort_order' => 8,
        ]);
        $this->assertSame(8, $navigation->displayPosition($created));
        $this->assertDatabaseMissing('sidebar_menus', ['navigation_group' => $created->target_key]);

        foreach (['en' => 'Empty reports', 'km' => 'របាយការណ៍ថ្មី'] as $locale => $label) {
            app()->setLocale($locale);
            $groups = $navigation->apply([
                NavigationGroup::make(__('navigation.groups.dashboard'))->items([
                    NavigationItem::make('Overview')->key(Dashboard::class)->url('/dashboard'),
                ]),
            ], $admin);
            $this->assertSame([$navigation->targetLabel('group:dashboard'), $label], array_map(fn (NavigationGroup $group): ?string => $group->getLabel(), $groups));
            $this->assertSame([], $groups[1]->getItems());
            $this->assertFalse($groups[1]->isCollapsible());
            $this->get(SidebarMenuResource::getUrl('index', ['tab' => 'groups']))
                ->assertOk()->assertSee('data-group-label="'.$label.'"', escape: false);
        }
    }

    public function test_edit_keeps_group_selector_enabled_but_locks_menu_and_ignores_forged_target_changes(): void
    {
        $admin = $this->actor('admin');
        $record = $this->setting($admin->roles()->first(), 'item:'.Dashboard::class);
        $other = $this->setting($admin->roles()->first(), 'item:'.SidebarMenuResource::class, ['label_en' => 'Sidebar settings']);
        $component = Livewire::test(EditSidebarMenu::class, ['record' => $record->id])
            ->assertFormFieldIsEnabled('navigation_group')
            ->assertFormFieldIsDisabled('target_key')
            ->assertFormFieldIsEnabled('label_en')
            ->assertFormFieldIsEnabled('label_km')
            ->assertFormFieldIsEnabled('sort_order')
            ->assertSet('data.navigation_group', 'group:dashboard')
            ->assertSet('data.target_key', 'item:'.Dashboard::class)
            ->set('data.navigation_group', 'group:settings')
            ->set('data.target_key', $other->target_key)
            ->call('save')->assertHasFormErrors(['target_key']);
        $this->assertSame('item:'.Dashboard::class, $record->fresh()->target_key);
        $this->assertSame(10, $record->fresh()->sort_order);
        // A different valid choice in the same group is excluded from saved data too.
        $component->set('data.target_key', 'group:dashboard')
            ->fillForm(['label_en' => 'Overview', 'label_km' => 'ទិដ្ឋភាពទូទៅ', 'sort_order' => 7])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('item:'.Dashboard::class, $record->fresh()->target_key);
        $this->assertSame('Overview', $record->fresh()->label_en);
        $this->assertSame('ទិដ្ឋភាពទូទៅ', $record->fresh()->label_km);
        $this->assertSame(2, $record->fresh()->sort_order);
        $this->assertSame('Sidebar settings', $other->fresh()->label_en);
    }

    public function test_dashboard_has_a_localized_editable_group_and_keeps_existing_menu_configuration(): void
    {
        $admin = $this->actor('admin');
        $role = $admin->roles()->first();
        $dashboard = $this->setting($role, 'item:'.Dashboard::class, ['label_en' => 'Saved Dashboard', 'sort_order' => -100]);
        $migration = require database_path('migrations/2026_11_25_000004_add_dashboard_sidebar_group.php');
        $migration->up();
        $migration->up();
        $heading = SidebarMenu::query()->where('role_id', $role->id)->where('target_key', 'group:dashboard')->sole();

        foreach (['en' => 'Dashboard', 'km' => 'ផ្ទាំងព័ត៌មាន'] as $locale => $label) {
            app()->setLocale($locale);
            $this->assertSame($label, Dashboard::getNavigationGroup());
            $this->assertSame('group:dashboard', SidebarNavigation::groupKey($label));
            $this->assertSame('group:dashboard', app(SidebarNavigation::class)->groupForTarget('item:'.Dashboard::class));
            $this->assertArrayHasKey('group:dashboard', app(SidebarNavigation::class)->groupOptions());
        }
        app()->setLocale('en');
        Livewire::test(ListSidebarMenus::class)->assertCanSeeTableRecords([$dashboard])
            ->assertCanNotSeeTableRecords([$heading])
            ->set('activeTab', 'groups')->assertCanSeeTableRecords([$heading])
            ->assertCanNotSeeTableRecords([$dashboard]);
        Livewire::test(EditSidebarMenu::class, ['record' => $heading->id])
            ->assertSet('data.navigation_group', 'group:dashboard')
            ->fillForm(['label_en' => 'Overview'])->call('save')->assertHasNoFormErrors()
            ->assertRedirect(SidebarMenuResource::getUrl('index', ['tab' => 'groups']));
        $this->assertSame('Overview', $heading->fresh()->label_en);
        $this->assertSame('Saved Dashboard', $dashboard->fresh()->label_en);
        $this->assertSame(-100, $dashboard->fresh()->sort_order);
    }

    public function test_existing_group_heading_configuration_reopens_with_the_correct_group(): void
    {
        $admin = $this->actor('admin');
        $record = $this->setting($admin->roles()->first(), 'group:settings', ['label_en' => 'Shared settings', 'sort_order' => 5]);
        Livewire::test(EditSidebarMenu::class, ['record' => $record->id])
            ->assertFormFieldIsHidden('navigation_group')
            ->assertFormFieldIsHidden('target_key')
            ->assertSet('data.navigation_group', 'group:settings')
            ->assertSet('data.target_key', 'group:settings')
            ->set('data.navigation_group', 'group:dashboard')
            ->set('data.target_key', 'item:'.SidebarMenuResource::class)
            ->fillForm(['label_en' => 'Updated settings'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Updated settings', $record->fresh()->label_en);
        $this->assertSame('group:settings', $record->fresh()->target_key);
        $this->assertNull($record->fresh()->navigation_group);
    }

    public function test_moving_a_menu_to_dashboard_updates_table_and_navigation_for_every_role(): void
    {
        $admin = $this->actor('admin');
        $role = $admin->roles()->first();
        $key = 'item:'.CandidateEntranceStatisticResource::class;
        $dashboard = $this->setting($role, 'item:'.Dashboard::class, ['sort_order' => -100]);
        $menu = $this->setting($role, $key, ['sort_order' => 1]);
        $sibling = $this->setting($role, 'item:'.CandidateExitStatisticResource::class, ['sort_order' => 99]);
        $otherRole = Role::findOrCreate('other_group_editor', 'web');
        $otherMenu = $this->setting($otherRole, $key, ['sort_order' => 77]);
        Livewire::test(EditSidebarMenu::class, ['record' => $menu->id])
            ->fillForm(['navigation_group' => 'group:dashboard', 'sort_order' => 2])->call('save')->assertHasNoFormErrors();
        $this->assertSame('group:dashboard', $menu->fresh()->navigation_group);
        $this->assertSame($key, $menu->fresh()->target_key);
        $this->assertSame('group:dashboard', $otherMenu->fresh()->navigation_group);
        Livewire::test(EditSidebarMenu::class, ['record' => $menu->id])
            ->assertSet('data.navigation_group', 'group:dashboard')
            ->assertFormFieldIsDisabled('target_key');
        Livewire::test(ListSidebarMenus::class)
            ->assertCanSeeTableRecords([$dashboard, $menu, $sibling], inOrder: true)
            ->assertCanNotSeeTableRecords([$otherMenu])
            ->assertTableColumnStateSet('navigation_group', 'Dashboard', $menu);

        foreach (['en', 'km'] as $locale) {
            app()->setLocale($locale);
            $source = NavigationItem::make('Statistics')->key(CandidateEntranceStatisticResource::class)
                ->url('/entrance-exam-statistics')->icon('heroicon-o-chart-bar')->badge('20')->isActiveWhen(fn () => true);
            $groups = [
                NavigationGroup::make(__('navigation.groups.dashboard'))->items([
                    NavigationItem::make('Dashboard')->key(Dashboard::class)->url('/dashboard')->sort(-100),
                ]),
                NavigationGroup::make(__('navigation.groups.candidates'))->items([
                    $source, NavigationItem::make('Exit')->key(CandidateExitStatisticResource::class)->url('/exit'),
                ]),
            ];
            $result = app(SidebarNavigation::class)->apply($groups, $admin);
            $this->assertSame(__('navigation.groups.dashboard'), $result[0]->getLabel());
            $this->assertSame(['/dashboard', '/entrance-exam-statistics'], collect($result[0]->getItems())->map->getUrl()->all());
            $moved = $result[0]->getItems()[1];
            $this->assertSame('heroicon-o-chart-bar', $moved->getIcon());
            $this->assertSame('20', $moved->getBadge());
            $this->assertTrue($moved->isActive());
            $this->assertCount(1, $result[1]->getItems());
            $this->assertSame('/exit', $result[1]->getItems()[0]->getUrl());
            // A destination group is created even if it originally had no authorized items.
            $emptyDestination = app(SidebarNavigation::class)->apply([
                NavigationGroup::make(__('navigation.groups.candidates'))->items([$source]),
            ], $admin);
            $this->assertCount(1, $emptyDestination);
            $this->assertSame(__('navigation.groups.dashboard'), $emptyDestination[0]->getLabel());
            $this->assertCount(1, $emptyDestination[0]->getItems());
        }
        app()->setLocale('en');
        $otherUser = $this->actor('other_group_editor');
        $shared = app(SidebarNavigation::class)->apply($groups, $otherUser);
        $this->assertCount(2, $shared[0]->getItems());
        $this->assertCount(1, $shared[1]->getItems());
        $this->actingAs($admin);
        // Dragging the moved item now reorders its new Dashboard siblings only.
        Livewire::test(ListSidebarMenus::class)->call('reorderTable', [$menu->id, $dashboard->id, $sibling->id], $menu->id);
        $this->assertSame(1, $menu->fresh()->sort_order);
        $this->assertSame(2, $dashboard->fresh()->sort_order);
        $this->assertSame(99, $sibling->fresh()->sort_order);
        $this->assertSame(1, $otherMenu->fresh()->sort_order);
    }

    public function test_menu_group_must_be_an_existing_navigation_group(): void
    {
        $admin = $this->actor('admin');
        $menu = $this->setting($admin->roles()->first(), 'item:'.CandidateEntranceStatisticResource::class);
        foreach (['group:ungrouped', 'group:missing', 'item:'.Dashboard::class, null] as $invalidGroup) {
            Livewire::test(EditSidebarMenu::class, ['record' => $menu->id])
                ->fillForm(['navigation_group' => $invalidGroup])->call('save')->assertHasFormErrors(['navigation_group']);
            $this->assertNull($menu->fresh()->navigation_group);
        }
    }

    public function test_saved_bilingual_names_show_in_tables_and_dropdowns_for_groups_and_menus(): void
    {
        $admin = $this->actor('admin');
        $role = $admin->roles()->first();
        $group = $this->setting($role, 'group:cashier', ['sort_order' => 3]);
        $menu = $this->setting($role, 'item:'.PaymentResource::class, ['sort_order' => 20]);
        $navigation = app(SidebarNavigation::class);
        // Reading the original options before editing must not leave stale names afterward.
        $this->assertSame('Cashier', $navigation->groupOptions()['group:cashier']);
        Livewire::test(EditSidebarMenu::class, ['record' => $group->id])
            ->fillForm(['label_en' => 'Cashiersad', 'label_km' => 'បេឡាdsad'])
            ->call('save')->assertHasNoFormErrors();
        Livewire::test(EditSidebarMenu::class, ['record' => $menu->id])
            ->fillForm(['label_en' => 'Payment desk', 'label_km' => 'បញ្ជរទូទាត់'])
            ->call('save')->assertHasNoFormErrors();

        foreach (['en' => ['Cashiersad', 'Payment desk'], 'km' => ['បេឡាdsad', 'បញ្ជរទូទាត់']] as $locale => [$groupName, $menuName]) {
            app()->setLocale($locale);
            Livewire::test(ListSidebarMenus::class)->set('activeTab', 'groups')
                ->assertTableColumnStateSet('navigation_group', $groupName, $group);
            Livewire::test(ListSidebarMenus::class)
                ->assertTableColumnStateSet('navigation_group', $groupName, $menu)
                ->assertTableColumnFormattedStateSet('target_key', $menuName, $menu);
            $fields = Livewire::test(EditSidebarMenu::class, ['record' => $menu->id])->instance()->form->getFlatFields();
            $this->assertSame($groupName, $fields['navigation_group']->getOptions()['group:cashier']);
            $this->assertSame($menuName, $fields['target_key']->getOptions()[$menu->target_key]);
            $this->assertSame($groupName, $navigation->groupOptions()['group:cashier']);
            $this->assertSame($menuName, $navigation->targetLabel($menu->target_key));
        }
        $group->update(['label_en' => null]);
        app()->setLocale('en');
        $this->assertSame('Cashier', $navigation->groupOptions()['group:cashier']);
        Livewire::test(ListSidebarMenus::class)->set('activeTab', 'groups')
            ->assertTableColumnStateSet('navigation_group', 'Cashier', $group);
        $this->assertSame(3, $group->fresh()->sort_order);
        $this->assertSame(20, $menu->fresh()->sort_order);
    }

    public function test_read_only_and_unpermitted_users_cannot_modify_or_reorder(): void
    {
        $user = $this->actor('reader', ['ViewAny:SidebarMenu']);
        $record = $this->setting($user->roles()->first(), 'item:'.Dashboard::class);
        Livewire::test(ListSidebarMenus::class)->assertSuccessful();
        $this->assertFalse(SidebarMenuResource::canCreate());
        Livewire::test(EditSidebarMenu::class, ['record' => $record->id])->assertForbidden();
        Livewire::test(ListSidebarMenus::class)->call('reorderTable', [$record->id])->assertForbidden();
        $this->assertSame(10, $record->fresh()->sort_order);
        $this->actor('no_access');
        Livewire::test(ListSidebarMenus::class)->assertForbidden();
    }

    public function test_table_has_no_default_import_description_or_filters_and_shows_every_role(): void
    {
        $admin = $this->actor('admin');
        $role = $admin->roles()->first();
        $other = Role::findOrCreate('cashier_officer', 'web');
        $a = $this->setting($role, 'item:'.Dashboard::class);
        $b = $this->setting($other, 'item:'.PaymentResource::class);
        $component = Livewire::test(ListSidebarMenus::class)
            ->assertActionDoesNotExist('importDefaults')
            ->assertCanSeeTableRecords([$a, $b]);
        $this->assertSame([], $component->instance()->getTable()->getFilters());
        $this->assertEmpty($component->get('tableFilters'));
        $this->assertNull($component->instance()->getTable()->getDescription());
        $this->assertSame(2, SidebarMenu::query()->count());
    }

    public function test_populating_sidebar_adds_system_menus_and_groups_but_excludes_custom_forms(): void
    {
        $admin = $this->actor('admin');
        $role = $admin->roles()->first();
        $dashboardKey = 'item:'.Dashboard::class;
        $saved = $this->setting($role, $dashboardKey, ['label_en' => 'Dashboard Candidate', 'sort_order' => 77]);
        $form = CustomForm::query()->create(['name' => json_encode(['en' => 'Admission', 'km' => 'ការចូលរៀន']), 'slug' => 'sidebar-admission', 'is_active' => true]);
        $inactive = CustomForm::query()->create(['name' => 'Archived', 'slug' => 'sidebar-archived', 'is_active' => false]);
        $service = app(SidebarNavigation::class);
        $catalog = $service->catalog();
        $created = $service->populateForRole($role->id);
        $this->assertSame(count($catalog) - 1, $created);
        $this->assertEqualsCanonicalizing(array_keys($catalog), SidebarMenu::query()->where('role_id', $role->id)->pluck('target_key')->all());
        $this->assertDatabaseMissing('sidebar_menus', ['target_key' => 'item:custom-form-entry-'.$form->id]);
        $this->assertArrayNotHasKey('item:custom-form-entry-'.$form->id, $catalog);
        $this->assertDatabaseMissing('sidebar_menus', ['target_key' => 'item:custom-form-entry-'.$inactive->id]);
        $this->assertDatabaseHas('sidebar_menus', ['target_key' => 'group:settings']);
        $this->assertDatabaseHas('sidebar_menus', ['target_key' => 'item:'.SidebarMenuResource::class]);
        $this->assertSame('Dashboard Candidate', $saved->fresh()->label_en);
        $this->assertSame(77, $saved->fresh()->sort_order);
        $this->assertSame(0, $service->populateForRole($role->id));
        // The table does not recreate a deleted configuration on the next visit.
        $saved->delete();
        Livewire::test(ListSidebarMenus::class)->assertSuccessful();
        $this->assertModelMissing($saved);
    }

    public function test_reordering_is_shared_across_roles_and_does_not_duplicate_table_rows(): void
    {
        $admin = $this->actor('admin');
        $role = $admin->roles()->first();
        $other = Role::findOrCreate('other', 'web');
        $a = $this->setting($role, 'item:'.Dashboard::class, ['sort_order' => 10]);
        $b = $this->setting($role, 'group:settings', ['sort_order' => 20]);
        $c = $this->setting($other, 'group:settings', ['sort_order' => 30]);
        $d = $this->setting($other, 'group:candidates', ['sort_order' => 40]);
        $this->actor('registrar_manager', ['ViewAny:SidebarMenu', 'Reorder:SidebarMenu']);
        Livewire::test(ListSidebarMenus::class)->set('activeTab', 'groups')->assertCanSeeTableRecords([$b, $d])
            ->assertCanNotSeeTableRecords([$c])
            ->call('reorderTable', [$d->id, $b->id, $a->id], $b->id);
        $this->assertSame(2, $b->fresh()->sort_order);
        $this->assertSame(2, $c->fresh()->sort_order);
        $this->assertSame(1, $d->fresh()->sort_order);
        $this->assertSame(10, $a->fresh()->sort_order);
        Livewire::test(ListSidebarMenus::class)->call('reorderTable', [$a->id, $b->id], 9999999)->assertStatus(422);
        $this->assertSame(2, $c->fresh()->sort_order);
        $this->assertSame(10, $a->fresh()->sort_order);
    }

    public function test_custom_form_menus_are_not_managed_here_and_follow_custom_form_names_and_order(): void
    {
        $admin = $this->actor('admin');
        $role = $admin->roles()->first();
        $form = CustomForm::query()->create(['name' => 'Original form', 'slug' => 'sidebar-form', 'is_active' => true, 'display_order' => 4]);
        $key = 'item:custom-form-entry-'.$form->id;
        $legacy = $this->setting($role, $key, ['label_en' => 'Old sidebar override', 'label_km' => 'ឈ្មោះចាស់', 'sort_order' => -1000]);
        $heading = $this->setting($role, 'group:form_entry');
        $dashboard = $this->setting($role, 'item:'.Dashboard::class);
        $service = app(SidebarNavigation::class);
        $this->assertArrayNotHasKey($key, $service->catalog());
        $this->assertArrayNotHasKey($key, $service->menuOptions('group:form_entry'));
        Livewire::test(ListSidebarMenus::class)->assertCanSeeTableRecords([$dashboard])
            ->assertCanNotSeeTableRecords([$legacy])
            ->set('activeTab', 'groups')->assertCanSeeTableRecords([$heading]);
        Livewire::test(EditSidebarMenu::class, ['record' => $legacy->id])->assertNotFound();
        Livewire::test(ListSidebarMenus::class)->call('reorderTable', [$legacy->id], $legacy->id)->assertStatus(422);

        $form->update(['name' => json_encode(['en' => 'Updated application', 'km' => 'ពាក្យស្នើសុំថ្មី']), 'display_order' => 9]);
        foreach (['en' => 'Updated application', 'km' => 'ពាក្យស្នើសុំថ្មី'] as $locale => $label) {
            app()->setLocale($locale);
            $items = $service->apply([
                NavigationGroup::make(__('navigation.groups.form_entry'))->items([
                    NavigationItem::make(CustomFormEntryResource::getNavigationTitle($form))
                        ->key('custom-form-entry-'.$form->id)->url('/current-form')->sort(CustomFormEntryResource::getFormSortNumber($form)),
                    NavigationItem::make('Other application')->key('custom-form-entry-other')->url('/other-form')->sort(1),
                ]),
            ], $admin)[0]->getItems();
            $this->assertSame('/other-form', $items[0]->getUrl());
            $this->assertSame($label, $items[1]->getLabel());
            $this->assertSame('/current-form', $items[1]->getUrl());
            $this->assertSame(9, $items[1]->getSort());
        }
        // Existing configuration is left intact; forms and submissions are never deleted.
        $this->assertModelExists($form);
        $this->assertSame(-1000, $legacy->fresh()->sort_order);
    }

    public function test_table_follows_sidebar_groups_in_normal_and_reorder_modes(): void
    {
        $admin = $this->actor('admin');
        $role = $admin->roles()->first();
        // Insert in a different order, with local menu positions smaller than Dashboard's.
        $profileRow = $this->setting($role, 'item:'.CandidateEntranceStatisticResource::class, ['sort_order' => -1000]);
        $settings = $this->setting($role, 'group:settings', ['sort_order' => 3]);
        $payment = $this->setting($role, 'item:'.PaymentResource::class, ['sort_order' => 1]);
        $forms = $this->setting($role, 'group:candidates', ['sort_order' => 1]);
        $sidebar = $this->setting($role, 'item:'.SidebarMenuResource::class, ['sort_order' => 15]);
        $cashier = $this->setting($role, 'group:cashier', ['sort_order' => 2]);
        $admissionRow = $this->setting($role, 'item:'.CandidateExitStatisticResource::class, ['sort_order' => 2]);
        $dashboard = $this->setting($role, 'item:'.Dashboard::class, ['sort_order' => -100]);
        $expected = [$dashboard, $profileRow, $admissionRow, $payment, $sidebar];

        Livewire::test(ListSidebarMenus::class)
            ->assertCanSeeTableRecords($expected, inOrder: true)
            ->assertCanNotSeeTableRecords([$forms, $cashier, $settings])
            ->call('toggleTableReordering')
            ->assertCanSeeTableRecords($expected, inOrder: true)
            ->set('activeTab', 'groups')
            ->assertCanSeeTableRecords([$forms, $cashier, $settings], inOrder: true)
            ->assertCanNotSeeTableRecords($expected)
            ->assertTableColumnHidden('target_key');
        $this->assertSame(-1000, $profileRow->fresh()->sort_order);
    }

    public function test_dragging_group_heading_moves_its_section_without_changing_menu_positions(): void
    {
        $admin = $this->actor('admin');
        $role = $admin->roles()->first();
        $dashboard = $this->setting($role, 'item:'.Dashboard::class, ['sort_order' => -100]);
        $forms = $this->setting($role, 'group:candidates', ['sort_order' => 10]);
        $admission = $this->setting($role, 'item:'.CandidateEntranceStatisticResource::class, ['sort_order' => 20]);
        $cashier = $this->setting($role, 'group:cashier', ['sort_order' => 1]);
        $payment = $this->setting($role, 'item:'.PaymentResource::class, ['sort_order' => 30]);

        Livewire::test(ListSidebarMenus::class)
            ->set('activeTab', 'groups')
            ->call('reorderTable', [$forms->id, $cashier->id], $forms->id)
            ->assertCanSeeTableRecords([$forms, $cashier], inOrder: true)
            ->set('activeTab', 'menus')
            ->assertCanSeeTableRecords([$dashboard, $admission, $payment], inOrder: true);
        $this->assertSame(1, $forms->fresh()->sort_order);
        $this->assertSame(2, $cashier->fresh()->sort_order);
        $this->assertSame(20, $admission->fresh()->sort_order);
        $this->assertSame(30, $payment->fresh()->sort_order);
        $this->assertSame(-100, $dashboard->fresh()->sort_order);
    }

    public function test_dragging_menu_reorders_only_siblings_and_keeps_its_sidebar_group(): void
    {
        $admin = $this->actor('admin');
        $role = $admin->roles()->first();
        $forms = $this->setting($role, 'group:candidates', ['sort_order' => 5]);
        $first = $this->setting($role, 'item:'.CandidateEntranceStatisticResource::class, ['sort_order' => 10]);
        $second = $this->setting($role, 'item:'.CandidateExitStatisticResource::class, ['sort_order' => 20]);
        $settings = $this->setting($role, 'group:settings', ['sort_order' => 10]);
        $sidebar = $this->setting($role, 'item:'.SidebarMenuResource::class, ['sort_order' => 30]);

        // Even a drop above another group only updates the dragged menu's siblings.
        Livewire::test(ListSidebarMenus::class)
            ->call('reorderTable', [$second->id, $first->id, $sidebar->id], $second->id)
            ->assertCanSeeTableRecords([$second, $first, $sidebar], inOrder: true)
            ->assertCanNotSeeTableRecords([$forms, $settings]);
        $this->assertSame(1, $second->fresh()->sort_order);
        $this->assertSame(2, $first->fresh()->sort_order);
        $this->assertSame(5, $forms->fresh()->sort_order);
        $this->assertSame(10, $settings->fresh()->sort_order);
        $this->assertSame(30, $sidebar->fresh()->sort_order);
    }

    public function test_display_numbers_restart_in_each_shared_group_and_continue_across_pages(): void
    {
        $admin = $this->actor('admin');
        $role = $admin->roles()->first();
        $dashboard = $this->setting($role, 'item:'.Dashboard::class, ['sort_order' => -100]);
        $firstPayment = $this->setting($role, 'item:'.PaymentStatusListResource::class, ['sort_order' => 10]);
        $secondPayment = $this->setting($role, 'item:'.PaymentResource::class, ['sort_order' => 50]);
        $firstCandidate = $this->setting($role, 'item:'.CandidateEntranceStatisticResource::class, ['sort_order' => 20]);
        $secondCandidate = $this->setting($role, 'item:'.CandidateExitStatisticResource::class, ['sort_order' => 40]);
        $otherRole = Role::findOrCreate('other_sidebar_role', 'web');
        $otherPayment = $this->setting($otherRole, 'item:'.PaymentResource::class, ['sort_order' => 99]);
        $expected = [$dashboard->id => 1, $firstPayment->id => 1, $secondPayment->id => 2,
            $firstCandidate->id => 1, $secondCandidate->id => 2];
        $savedOrders = SidebarMenu::query()->pluck('sort_order', 'id')->all();
        $component = Livewire::test(ListSidebarMenus::class)->set('tableRecordsPerPage', 2);
        foreach (array_chunk($expected, 2, true) as $page => $pagePositions) {
            $component->call('setPage', $page + 1);
            $records = $component->instance()->getTableRecords()->all();
            $this->assertSame($pagePositions, collect($records)->mapWithKeys(fn (SidebarMenu $row): array => [$row->id => (int) $row->sidebar_position])->all());
            foreach ($records as $row) {
                $column = $component->instance()->getTable()->getColumn('sort_order')->record($row);
                $this->assertSame($pagePositions[$row->id], $column->getState());
            }
        }
        $component->call('toggleTableReordering');
        $this->assertSame($expected, collect($component->instance()->getTableRecords()->all())
            ->mapWithKeys(fn (SidebarMenu $row): array => [$row->id => (int) $row->sidebar_position])->all());
        $this->assertSame($savedOrders, SidebarMenu::query()->pluck('sort_order', 'id')->all());
    }

    public function test_edit_shows_table_position_and_saving_names_preserves_position_until_the_number_changes(): void
    {
        $admin = $this->actor('admin');
        $role = $admin->roles()->first();
        $dashboard = $this->setting($role, 'item:'.Dashboard::class, ['sort_order' => -100]);
        $menu = $this->setting($role, 'item:'.CandidateEntranceStatisticResource::class,
            ['navigation_group' => 'group:dashboard', 'sort_order' => 1]);
        $unrelated = $this->setting($role, 'item:'.PaymentResource::class, ['sort_order' => 50]);
        $otherRole = Role::findOrCreate('unchanged_position_role', 'web');
        $other = $this->setting($otherRole, $menu->target_key, ['sort_order' => 80]);

        Livewire::test(EditSidebarMenu::class, ['record' => $menu->id])->assertSet('data.sort_order', 2)
            ->fillForm(['label_en' => 'Renamed statistics'])->call('save')->assertHasNoFormErrors();
        $this->assertSame(-100, $dashboard->fresh()->sort_order);
        $this->assertSame(1, $menu->fresh()->sort_order);
        $navigation = app(SidebarNavigation::class);
        $this->assertSame(2, $navigation->displayPosition($menu->fresh()));
        Livewire::test(EditSidebarMenu::class, ['record' => $menu->id])->assertSet('data.sort_order', 2)
            ->fillForm(['sort_order' => 1])->call('save')->assertHasNoFormErrors();
        Livewire::test(EditSidebarMenu::class, ['record' => $menu->id])->assertSet('data.sort_order', 1);
        $this->assertSame(1, $navigation->displayPosition($menu->fresh()));
        $this->assertSame(2, $navigation->displayPosition($dashboard->fresh()));
        Livewire::test(ListSidebarMenus::class)->assertCanSeeTableRecords([$menu, $dashboard, $unrelated], inOrder: true)->assertCanNotSeeTableRecords([$other]);
        $this->assertSame(50, $unrelated->fresh()->sort_order);
        $this->assertSame(1, $other->fresh()->sort_order);
    }

    public function test_group_edit_uses_display_position_and_changing_it_reorders_only_group_headings(): void
    {
        $admin = $this->actor('admin');
        $role = $admin->roles()->first();
        $dashboard = $this->setting($role, 'group:dashboard', ['sort_order' => 0]);
        $cashier = $this->setting($role, 'group:cashier', ['sort_order' => 3]);
        $payment = $this->setting($role, 'item:'.PaymentResource::class, ['sort_order' => 75]);
        Livewire::test(EditSidebarMenu::class, ['record' => $cashier->id])->assertSet('data.sort_order', 2)
            ->fillForm(['label_en' => 'Cash desk'])->call('save')->assertHasNoFormErrors();
        $this->assertSame(3, $cashier->fresh()->sort_order);
        Livewire::test(EditSidebarMenu::class, ['record' => $cashier->id])->assertSet('data.sort_order', 2)
            ->fillForm(['sort_order' => 1])->call('save')->assertHasNoFormErrors();
        Livewire::test(ListSidebarMenus::class)->set('activeTab', 'groups')
            ->assertCanSeeTableRecords([$cashier, $dashboard], inOrder: true);
        $this->assertSame(1, app(SidebarNavigation::class)->displayPosition($cashier->fresh()));
        $this->assertSame(2, app(SidebarNavigation::class)->displayPosition($dashboard->fresh()));
        $this->assertSame(75, $payment->fresh()->sort_order);
    }

    public function test_group_dropdown_follows_shared_group_table_order_for_all_roles(): void
    {
        $admin = $this->actor('admin');
        $role = $admin->roles()->first();
        $otherRole = Role::findOrCreate('cashier_officer', 'web');
        $navigation = app(SidebarNavigation::class);
        $headings = [];
        foreach (array_keys($navigation->groupOptions()) as $offset => $key) {
            $headings[$key] = $this->setting($role, $key, ['sort_order' => $offset + 1]);
            $this->setting($otherRole, $key, ['sort_order' => $offset + 1]);
        }
        $menu = $this->setting($role, 'item:'.Dashboard::class);
        $otherMenu = $this->setting($otherRole, 'item:'.Dashboard::class);
        $order = ['group:dashboard', 'group:candidates', 'group:form_entry', 'group:form_builder', 'group:settings', 'group:activity', 'group:cashier'];
        $navigation->reorder(array_map(fn (string $key): int => $headings[$key]->id, $order), $headings['group:cashier']->id);
        $headings['group:cashier']->update(['label_en' => 'Cash desk', 'label_km' => 'បេឡាថ្មី']);
        foreach (['en' => 'Cash desk', 'km' => 'បេឡាថ្មី'] as $locale => $cashierName) {
            app()->setLocale($locale);
            $fields = Livewire::test(EditSidebarMenu::class, ['record' => $menu->id])->instance()->form->getFlatFields();
            $options = $fields['navigation_group']->getOptions();
            $this->assertSame($order, array_keys($options));
            $this->assertSame($cashierName, $options['group:cashier']);
            $tableOrder = $navigation->orderTableQuery(SidebarMenu::query()->where('role_id', $role->id)
                ->where('target_key', 'like', 'group:%')->with('role'))->get()->pluck('target_key')->all();
            $this->assertSame($tableOrder, array_keys($options));
        }
        Livewire::test(EditSidebarMenu::class, ['record' => $otherMenu->id])->assertNotFound();
        $this->assertSame($order, array_keys($navigation->groupOptions()));
        // A later drag changes the shared dropdown and the legacy role copies together.
        $newOrder = ['group:cashier', ...array_slice($order, 0, -1)];
        Livewire::test(ListSidebarMenus::class)->set('activeTab', 'groups')
            ->call('reorderTable', array_map(fn (string $key): int => $headings[$key]->id, $newOrder), $headings['group:cashier']->id);
        $options = Livewire::test(EditSidebarMenu::class, ['record' => $menu->id])
            ->instance()->form->getFlatFields()['navigation_group']->getOptions();
        $this->assertSame($newOrder, array_keys($options));
        $this->assertSame($newOrder, array_keys($navigation->groupOptions()));
        $this->assertSame(1, SidebarMenu::query()->where('role_id', $otherRole->id)->where('target_key', 'group:cashier')->sole()->sort_order);
    }

    public function test_normal_navigation_keeps_permissions_links_icons_badges_and_active_state(): void
    {
        $user = $this->actor('viewer');
        $role = $user->roles()->first();
        $this->setting($role, 'item:profile', ['label_en' => 'My details', 'label_km' => 'ព័ត៌មានខ្ញុំ', 'sort_order' => 20]);
        $this->setting($role, 'item:admission', ['sort_order' => 1]);
        $this->setting($role, 'item:hidden', ['label_en' => 'Private']);
        foreach (['en', 'km'] as $locale) {
            app()->setLocale($locale);
            $profile = NavigationItem::make('Original')->key('profile')->sort(1)->url('/profile')->icon('heroicon-o-user')->badge('3')->isActiveWhen(fn () => true);
            $groups = [NavigationGroup::make(__('navigation.groups.form_entry'))->items([
                $profile, NavigationItem::make('Admission')->key('admission')->url('/admission')->sort(2),
            ])];
            $items = app(SidebarNavigation::class)->apply($groups, $user)[0]->getItems();
            $this->assertCount(2, $items);
            $this->assertSame('/admission', $items[0]->getUrl());
            $this->assertSame($locale === 'km' ? 'ព័ត៌មានខ្ញុំ' : 'My details', $items[1]->getLabel());
            $this->assertSame('/profile', $items[1]->getUrl());
            $this->assertSame('heroicon-o-user', $items[1]->getIcon());
            $this->assertSame('3', $items[1]->getBadge());
            $this->assertTrue($items[1]->isActive());
            $this->assertArrayNotHasKey('data-uhs-sidebar-target', $items[1]->getExtraAttributes());
            $this->assertSame('Original', $profile->getLabel());
        }
    }

    public function test_icon_picker_shows_every_design_once_with_svg_previews(): void
    {
        $admin = $this->actor('admin');
        $record = $this->setting($admin->roles()->first(), 'item:'.Dashboard::class);
        $expected = array_map(fn (string $path): string => 'heroicon-'.basename($path, '.svg'), glob(base_path('vendor/blade-ui-kit/blade-heroicons/resources/svg/*.svg')));
        $all = SidebarMenuIcons::all();
        $this->assertEqualsCanonicalizing($expected, array_values(array_filter(array_keys($all), fn (string $icon): bool => str_starts_with($icon, 'heroicon-'))));
        $catalog = SidebarMenuIcons::options();
        $this->assertArrayHasKey('fi-o-align-center', $catalog);
        $this->assertSame(count(array_unique($all)), count($catalog));
        $this->assertSame(count($catalog), count(array_unique($catalog)));
        $this->assertSame('Arrow Path Rounded Square', $catalog['heroicon-o-arrow-path-rounded-square']);
        foreach (['s', 'm', 'c'] as $variant) {
            $this->assertArrayNotHasKey('heroicon-'.$variant.'-arrow-path-rounded-square', $catalog);
        }
        $field = Livewire::test(EditSidebarMenu::class, ['record' => $record->id])
            ->assertFormFieldIsEnabled('navigation_icon')->instance()->form->getFlatFields()['navigation_icon'];
        $options = $field->getOptionsForJs();
        $this->assertSame(count($catalog), count($options));
        $this->assertSame(count($catalog), $field->getOptionsLimit());
        $this->assertEqualsCanonicalizing(array_keys($catalog), array_column($options, 'value'));
        $this->assertTrue(collect($options)->every(fn (array $option): bool => str_contains($option['label'], '<svg')));
        $this->assertTrue($field->isSearchable());
        foreach (['o', 's', 'm', 'c'] as $variant) {
            $icon = 'heroicon-'.$variant.'-wrench-screwdriver';
            $field->state($icon);
            $this->assertStringContainsString('data-sidebar-icon="'.$icon.'"', $field->getOptionLabel());
        }
        $this->assertNull(SidebarMenuIcons::optionHtml('<script>alert(1)</script>'));
    }

    public function test_icon_field_shows_the_real_default_icon_and_resets_to_it_when_cleared(): void
    {
        $admin = $this->actor('admin');
        $key = 'item:'.CandidateExitStatisticResource::class;
        $record = $this->setting($admin->roles()->first(), $key);
        $navigation = app(SidebarNavigation::class);
        $this->assertSame('heroicon-o-chart-bar', $navigation->defaultIconForTarget($key));
        $this->assertSame('heroicon-o-home', $navigation->defaultIconForTarget('item:'.Dashboard::class));
        foreach (['en', 'km'] as $locale) {
            app()->setLocale($locale);
            $component = Livewire::test(EditSidebarMenu::class, ['record' => $record->id])
                ->assertSet('data.navigation_icon', 'heroicon-o-chart-bar');
            $field = $component->instance()->form->getFlatFields()['navigation_icon'];
            $this->assertStringContainsString('data-sidebar-icon="heroicon-o-chart-bar"', $field->getOptionLabel());
            $this->assertNull($record->fresh()->navigation_icon);
            $component->call('save')->assertHasNoFormErrors();
            $this->assertNull($record->fresh()->navigation_icon);
        }
        $record->update(['navigation_icon' => 'heroicon-s-chart-bar']);
        $component = Livewire::test(EditSidebarMenu::class, ['record' => $record->id])
            ->assertSet('data.navigation_icon', 'heroicon-s-chart-bar')
            ->set('data.navigation_icon', null)
            ->assertSet('data.navigation_icon', 'heroicon-o-chart-bar');
        $options = $component->instance()->form->getFlatFields()['navigation_icon']->getOptionsForJs();
        $variants = array_values(array_filter(array_column($options, 'value'), fn (string $value): bool => (bool) preg_match('/^(?:heroicon|fi)-[osmc]-chart-bar$/', $value)));
        $this->assertSame(['heroicon-o-chart-bar'], $variants);
        $component->call('save')->assertHasNoFormErrors();
        $this->assertNull($record->fresh()->navigation_icon);
        Livewire::test(EditSidebarMenu::class, ['record' => $record->id])
            ->assertSet('data.navigation_icon', 'heroicon-o-chart-bar');
    }

    public function test_previously_saved_icon_variant_is_kept_without_adding_duplicate_designs(): void
    {
        $admin = $this->actor('admin');
        $icon = 'heroicon-s-arrow-path-rounded-square';
        $record = $this->setting($admin->roles()->first(), 'item:'.Dashboard::class, ['navigation_icon' => $icon]);
        $component = Livewire::test(EditSidebarMenu::class, ['record' => $record->id])
            ->assertSet('data.navigation_icon', $icon);
        $options = $component->instance()->form->getFlatFields()['navigation_icon']->getOptionsForJs();
        $variants = array_values(array_filter(array_column($options, 'value'), fn (string $value): bool => str_ends_with($value, '-arrow-path-rounded-square')));
        $this->assertSame([$icon], $variants);
        $component->fillForm(['label_en' => 'Renamed overview'])->call('save')->assertHasNoFormErrors();
        $this->assertSame($icon, $record->fresh()->navigation_icon);
    }

    public function test_selected_menu_icon_is_shared_with_all_roles_and_clearing_restores_original_icons(): void
    {
        $admin = $this->actor('admin');
        $menu = $this->setting($admin->roles()->first(), 'item:'.Dashboard::class);
        $copy = $this->setting(Role::findOrCreate('legacy_icon_role', 'web'), $menu->target_key);
        $registrar = $this->actor('registrar', ['ViewAny:SidebarMenu', 'Update:SidebarMenu']);
        $icon = 'heroicon-o-wrench-screwdriver';
        Livewire::test(EditSidebarMenu::class, ['record' => $menu->id])
            ->fillForm(['navigation_icon' => $icon])->call('save')->assertHasNoFormErrors();
        $this->assertSame($icon, $menu->fresh()->navigation_icon);
        $this->assertSame($icon, $copy->fresh()->navigation_icon);
        $viewer = $this->actor('icon_viewer_without_settings');
        $original = NavigationItem::make('Overview')->key(Dashboard::class)->url('/dashboard')
            ->icon('heroicon-o-home')->activeIcon('heroicon-s-home')->badge('3')->isActiveWhen(fn () => true);
        $groups = [NavigationGroup::make(__('navigation.groups.dashboard'))->items([$original])];
        foreach ([$admin, $registrar, $viewer] as $user) {
            $items = app(SidebarNavigation::class)->apply($groups, $user)[0]->getItems();
            $this->assertCount(1, $items);
            $this->assertSame($icon, $items[0]->getIcon());
            $this->assertSame($icon, $items[0]->getActiveIcon());
            $this->assertSame('/dashboard', $items[0]->getUrl());
            $this->assertSame('3', $items[0]->getBadge());
            $this->assertTrue($items[0]->isActive());
        }
        $this->assertSame('heroicon-o-home', $original->getIcon());
        $this->actingAs($registrar);
        Livewire::test(EditSidebarMenu::class, ['record' => $menu->id])
            ->assertSet('data.navigation_icon', $icon)
            ->fillForm(['navigation_icon' => null])->call('save')->assertHasNoFormErrors();
        $this->assertNull($menu->fresh()->navigation_icon);
        $this->assertNull($copy->fresh()->navigation_icon);
        $restored = app(SidebarNavigation::class)->apply($groups, $viewer)[0]->getItems()[0];
        $this->assertSame('heroicon-o-home', $restored->getIcon());
        $this->assertSame('heroicon-s-home', $restored->getActiveIcon());
    }

    public function test_icon_selection_validates_icons_and_applies_only_to_menu_items(): void
    {
        $admin = $this->actor('admin');
        $role = $admin->roles()->first();
        $menu = $this->setting($role, 'item:'.Dashboard::class);
        foreach (['heroicon-o-not-real', '<img src=x onerror=alert(1)>'] as $invalid) {
            Livewire::test(EditSidebarMenu::class, ['record' => $menu->id])
                ->fillForm(['navigation_icon' => $invalid])->call('save')->assertHasFormErrors(['navigation_icon']);
            $this->assertNull($menu->fresh()->navigation_icon);
        }
        $heading = $this->setting($role, 'group:dashboard');
        Livewire::test(EditSidebarMenu::class, ['record' => $heading->id])
            ->assertFormFieldDoesNotExist('navigation_icon');
        app(SidebarNavigation::class)->updateAtPosition($heading, ['sort_order' => 1, 'navigation_icon' => 'heroicon-o-user']);
        $this->assertNull($heading->fresh()->navigation_icon);
        $this->expectException(ValidationException::class);
        app(SidebarNavigation::class)->updateAtPosition($menu, ['sort_order' => 1, 'navigation_icon' => 'not-real']);
    }

    public function test_sidebar_management_page_title_follows_saved_menu_name_in_both_languages_and_tabs(): void
    {
        $admin = $this->actor('admin');
        $record = $this->setting($admin->roles()->first(), 'item:'.SidebarMenuResource::class);
        Livewire::test(EditSidebarMenu::class, ['record' => $record->id])->fillForm([
            'label_en' => 'Custom sidebar name', 'label_km' => 'ឈ្មោះម៉ឺនុយថ្មី',
        ])->call('save')->assertHasNoFormErrors();
        $this->actor('sidebar_reader', ['ViewAny:SidebarMenu']);
        foreach (['en' => 'Custom sidebar name', 'km' => 'ឈ្មោះម៉ឺនុយថ្មី'] as $locale => $label) {
            app()->setLocale($locale);
            foreach (['menus', 'groups'] as $tab) {
                $component = Livewire::test(ListSidebarMenus::class)->set('activeTab', $tab);
                $this->assertSame($label, $component->instance()->getTitle());
                $this->assertSame(app(SidebarNavigation::class)->targetLabel($record->target_key), $component->instance()->getTitle());
                $component->assertSee($label);
            }
        }
        $this->actingAs($admin);
        Livewire::test(EditSidebarMenu::class, ['record' => $record->id])->fillForm([
            'label_en' => null, 'label_km' => null,
        ])->call('save')->assertHasNoFormErrors();
        foreach (['en', 'km'] as $locale) {
            app()->setLocale($locale);
            $this->assertSame(__('sidebar_menus.navigation'), Livewire::test(ListSidebarMenus::class)->instance()->getTitle());
        }
    }

    public function test_admin_and_authorized_registrar_renames_are_shared_with_every_role_in_both_languages(): void
    {
        $admin = $this->actor('admin');
        $cashierRole = Role::findOrCreate('cashier', 'web');
        $key = 'item:'.Dashboard::class;
        $adminSetting = $this->setting($admin->roles()->first(), $key);
        Livewire::test(EditSidebarMenu::class, ['record' => $adminSetting->id])->fillForm([
            'label_en' => 'Dashboard Candidate', 'label_km' => 'ផ្ទាំងបេក្ខជន', 'sort_order' => 8,
        ])->call('save')->assertHasNoFormErrors();
        $this->assertDatabaseMissing('sidebar_menus', ['role_id' => $cashierRole->id, 'target_key' => $key]);
        $cashier = $this->actor('cashier');
        $registrar = $this->actor('registrar', ['ViewAny:SidebarMenu', 'Update:SidebarMenu']);
        $users = [$admin, $cashier, $registrar];
        foreach (['en' => 'Dashboard Candidate', 'km' => 'ផ្ទាំងបេក្ខជន'] as $locale => $expected) {
            app()->setLocale($locale);
            foreach ($users as $user) {
                $this->assertSame($expected, $this->dashboardLabel($user));
            }
        }
        // The registrar has no role-specific Dashboard row but can update the shared name.
        Livewire::test(EditSidebarMenu::class, ['record' => $adminSetting->id])->fillForm([
            'label_en' => 'Dashboard Student', 'label_km' => 'ផ្ទាំងសិស្ស',
        ])->set('data.role_id', $registrar->roles()->first()->id)->call('save')->assertHasNoFormErrors();
        foreach (['en' => 'Dashboard Student', 'km' => 'ផ្ទាំងសិស្ស'] as $locale => $expected) {
            app()->setLocale($locale);
            foreach ($users as $user) {
                $this->assertSame($expected, $this->dashboardLabel($user));
            }
        }
        $this->assertSame(1, $adminSetting->fresh()->sort_order);
        $this->assertSame($admin->roles()->first()->id, $adminSetting->fresh()->role_id);
        $this->assertDatabaseHas('sidebar_menu_names', ['target_key' => $key, 'label_en' => 'Dashboard Student']);
        $this->assertFalse($cashier->can('Update:SidebarMenu'));
    }

    public function test_shared_reordering_keeps_names_and_updates_legacy_role_copies(): void
    {
        $admin = $this->actor('admin');
        $role = $admin->roles()->first();
        $key = 'item:'.Dashboard::class;
        $first = $this->setting($role, $key, ['label_en' => 'Original shared name', 'label_km' => 'ឈ្មោះរួម', 'sort_order' => 8]);
        $newRole = Role::findOrCreate('new_viewer', 'web');
        $second = $this->setting($newRole, $key);
        $this->assertSame('Original shared name', $second->label_en);
        $secondOrder = $second->sort_order;
        $first->update(['label_en' => 'Latest shared name']);
        $this->assertSame('Latest shared name', $second->fresh()->label_en);
        // A model loaded before the rename must not restore its old name during a sort edit.
        $second->update(['sort_order' => $secondOrder + 10]);
        $this->assertSame('Latest shared name', SidebarMenuName::query()->where('target_key', $key)->sole()->label_en);
        app(SidebarNavigation::class)->reorder([$first->id]);
        $this->assertSame(1, $first->fresh()->sort_order);
        $this->assertSame(1, $second->fresh()->sort_order);
        $this->assertSame('Latest shared name', $first->fresh()->label_en);
        $this->assertSame('ឈ្មោះរួម', $first->fresh()->label_km);
        $this->assertSame('ឈ្មោះរួម', $second->fresh()->label_km);
    }

    public function test_deleting_role_configuration_preserves_shared_name_and_blank_name_resets_it_for_everyone(): void
    {
        $user = $this->actor('admin');
        $key = 'item:'.Dashboard::class;
        $record = $this->setting($user->roles()->first(), $key, ['label_en' => 'Shared dashboard', 'label_km' => 'ផ្ទាំងរួម']);
        $record->delete();
        $this->assertSame('Shared dashboard', $this->dashboardLabel($user));
        $other = $this->actor('another_role');
        $record = $this->setting($other->roles()->first(), $key);
        $this->assertSame('Shared dashboard', $record->label_en);
        $record->update(['label_en' => null, 'label_km' => null]);
        foreach ([$user, $other] as $actor) {
            $this->assertSame('Default dashboard', $this->dashboardLabel($actor));
        }
    }

    public function test_shared_name_migration_keeps_latest_existing_names_and_all_role_orders(): void
    {
        $this->actor('admin');
        Schema::drop('sidebar_menu_names');
        $firstRole = Role::findOrCreate('first', 'web');
        $secondRole = Role::findOrCreate('second', 'web');
        $key = 'item:'.Dashboard::class;
        $first = $this->setting($firstRole, $key, ['label_en' => 'Old name', 'sort_order' => 7]);
        $second = $this->setting($secondRole, $key, ['label_en' => 'Latest name', 'label_km' => 'ឈ្មោះថ្មី', 'sort_order' => 23]);
        DB::table('sidebar_menus')->where('id', $first->id)->update(['updated_at' => '2026-11-20 00:00:00']);
        DB::table('sidebar_menus')->where('id', $second->id)->update(['updated_at' => '2026-11-25 00:00:00']);
        $migration = require database_path('migrations/2026_11_25_000003_create_sidebar_menu_names_table.php');
        $migration->up();
        $this->assertDatabaseHas('sidebar_menu_names', ['target_key' => $key, 'label_en' => 'Latest name', 'label_km' => 'ឈ្មោះថ្មី']);
        $this->assertSame('Latest name', $first->fresh()->label_en);
        $this->assertSame(7, $first->fresh()->sort_order);
        $this->assertSame(23, $second->fresh()->sort_order);
    }

    public function test_shared_layout_migration_preserves_latest_saved_layout_and_leaves_custom_forms_alone(): void
    {
        $admin = $this->actor('admin');
        $otherRole = Role::findOrCreate('registrar_manager', 'web');
        $key = 'item:'.PaymentResource::class;
        $first = $this->setting($admin->roles()->first(), $key, ['sort_order' => 10, 'navigation_group' => 'group:cashier']);
        $latest = $this->setting($otherRole, $key, ['sort_order' => 2, 'navigation_group' => 'group:dashboard']);
        DB::table('sidebar_menus')->where('id', $first->id)->update(['updated_at' => '2026-11-24 00:00:00']);
        DB::table('sidebar_menus')->where('id', $latest->id)->update(['updated_at' => '2026-11-25 00:00:00']);
        $formFirst = $this->setting($admin->roles()->first(), 'item:custom-form-entry-999', ['sort_order' => 11]);
        $formOther = $this->setting($otherRole, 'item:custom-form-entry-999', ['sort_order' => 99]);
        $migration = require database_path('migrations/2026_11_25_000006_share_sidebar_layout_across_roles.php');
        $migration->up();
        foreach ([$first, $latest] as $record) {
            $this->assertSame(2, $record->fresh()->sort_order);
            $this->assertSame('group:dashboard', $record->fresh()->navigation_group);
        }
        $this->assertSame($first->id, SidebarMenuResource::getEloquentQuery()->where('target_key', $key)->sole()->id);
        $this->assertSame(4, SidebarMenu::query()->count());
        $this->assertSame(11, $formFirst->fresh()->sort_order);
        $this->assertSame(99, $formOther->fresh()->sort_order);
    }

    public function test_registrar_drag_updates_admin_and_roles_without_settings_without_granting_resource_access(): void
    {
        $admin = $this->actor('admin');
        $role = $admin->roles()->first();
        $first = $this->setting($role, 'item:'.PaymentStatusListResource::class, ['sort_order' => 1]);
        $second = $this->setting($role, 'item:'.PaymentResource::class, ['sort_order' => 2]);
        $registrar = $this->actor('registrar_manager', ['ViewAny:SidebarMenu', 'Reorder:SidebarMenu']);
        $permissionsBefore = $registrar->getAllPermissions()->pluck('name')->sort()->values()->all();
        Livewire::test(ListSidebarMenus::class)->call('reorderTable', [$second->id, $first->id], $second->id);
        $newUser = $this->actor('new_role_without_sidebar_settings');
        $this->assertDatabaseMissing('sidebar_menus', ['role_id' => $newUser->roles()->first()->id]);
        $groups = [NavigationGroup::make(__('navigation.groups.cashier'))->items([
            NavigationItem::make('Status')->key(PaymentStatusListResource::class)->url('/status')->sort(1),
            NavigationItem::make('Payment')->key(PaymentResource::class)->url('/payment')->sort(2),
        ])];
        foreach ([$admin, $registrar, $newUser] as $user) {
            $items = app(SidebarNavigation::class)->apply($groups, $user)[0]->getItems();
            $this->assertSame(['/payment', '/status'], collect($items)->map->getUrl()->all());
        }
        // Filament still controls visibility: sorting never adds a resource absent from its navigation.
        $limited = [NavigationGroup::make(__('navigation.groups.cashier'))->items([$groups[0]->getItems()[0]])];
        $items = app(SidebarNavigation::class)->apply($limited, $registrar)[0]->getItems();
        $this->assertCount(1, $items);
        $this->assertSame('/status', $items[0]->getUrl());
        $this->assertSame($permissionsBefore, $registrar->fresh()->getAllPermissions()->pluck('name')->sort()->values()->all());
    }

    private function dashboardLabel(User $user): string
    {
        $groups = [NavigationGroup::make()->items([
            NavigationItem::make('Default dashboard')->key(Dashboard::class)->url('/dashboard'),
        ])];

        return app(SidebarNavigation::class)->apply($groups, $user)[0]->getItems()[0]->getLabel();
    }

    public function test_failed_role_configuration_save_cannot_partially_rename_the_shared_menu(): void
    {
        $user = $this->actor('admin');
        $role = $user->roles()->first();
        $key = 'item:'.Dashboard::class;
        $original = $this->setting($role, $key, ['label_en' => 'Saved name']);
        try {
            $this->setting($role, $key, ['label_en' => 'Must be rolled back']);
            $this->fail('Duplicate role configuration was accepted.');
        } catch (QueryException) {
            $this->assertSame('Saved name', $original->fresh()->label_en);
            $this->assertSame('Saved name', SidebarMenuName::query()->where('target_key', $key)->sole()->label_en);
        }
    }

    public function test_group_heading_names_and_order_are_shared_with_roles_without_configuration(): void
    {
        $admin = $this->actor('admin');
        $this->setting($admin->roles()->first(), 'group:settings', ['label_en' => 'Shared settings', 'label_km' => 'ការកំណត់រួម', 'sort_order' => 20]);
        $viewer = $this->actor('viewer');
        foreach (['en' => 'Shared settings', 'km' => 'ការកំណត់រួម'] as $locale => $name) {
            app()->setLocale($locale);
            $groups = [
                NavigationGroup::make(__('navigation.groups.settings'))->items([NavigationItem::make('A')->key('a')->url('/a')]),
                NavigationGroup::make(__('navigation.groups.candidates'))->items([NavigationItem::make('B')->key('b')->url('/b')]),
            ];
            $service = app(SidebarNavigation::class);
            $this->assertSame($name, $service->apply($groups, $viewer)[1]->getLabel());
            $this->assertSame($name, $service->apply($groups, $admin)[1]->getLabel());
        }
    }

    private function actor(string $roleName, array $permissions = []): User
    {
        $name = 'sidebar_'.bin2hex(random_bytes(5));
        $role = Role::findOrCreate($roleName, 'web');
        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $user = User::query()->forceCreate(['name' => $name, 'username' => $name, 'email' => $name.'@example.test', 'registration_type' => 'admin', 'date_of_birth' => '2000-01-01', 'password' => 'password', 'is_active' => true]);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function setting(Role $role, string $key, array $attributes = []): SidebarMenu
    {
        return SidebarMenu::query()->create(['role_id' => $role->id, 'target_key' => $key, 'sort_order' => 10, ...$attributes]);
    }
}
