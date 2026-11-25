<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Payments\PaymentResource;
use App\Filament\Admin\Resources\SidebarMenus\SidebarMenuResource;
use App\Models\Role;
use App\Models\SidebarMenuName;
use App\Models\User;
use App\Support\FilamentActionPermissions;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\EditRole;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RoleSidebarLabelsTest extends TestCase
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
        $user = User::query()->forceCreate(['name' => 'Role administrator', 'username' => 'role_labels_admin', 'email' => 'role-labels@example.test', 'registration_type' => 'admin', 'date_of_birth' => '2000-01-01', 'password' => 'password', 'is_active' => true]);
        $user->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($user);
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public function test_permission_headings_follow_bilingual_menu_names_without_changing_keys_or_grants(): void
    {
        $before = RoleResource::getResourceEntitiesSchema();
        $pageKeys = array_keys(RoleResource::getPageOptions());
        $entity = FilamentShield::getResources()[SidebarMenuResource::class];
        $options = RoleResource::getResourcePermissionOptions($entity);
        $permissions = Permission::orderBy('id')->get(['id', 'name', 'guard_name'])->toArray();
        $grants = DB::table('role_has_permissions')->orderBy('role_id')->orderBy('permission_id')->get()->toArray();
        SidebarMenuName::create(['target_key' => 'item:'.SidebarMenuResource::class, 'label_en' => 'Navigation Settings', 'label_km' => 'កំណត់ម៉ឺនុយថ្មី']);
        SidebarMenuName::create(['target_key' => 'item:'.PaymentResource::class, 'label_en' => 'Cash Receipts', 'label_km' => 'បង្កាន់ដៃប្រាក់']);

        foreach (['en' => ['Navigation Settings', 'Cash Receipts'], 'km' => ['កំណត់ម៉ឺនុយថ្មី', 'បង្កាន់ដៃប្រាក់']] as $locale => $labels) {
            app()->setLocale($locale);
            $sections = RoleResource::getResourceEntitiesSchema();
            $this->assertSame($labels[0], $sections[SidebarMenuResource::class]->getHeading());
            $this->assertSame($labels[1], $sections[PaymentResource::class]->getHeading());
            $this->assertSame(array_keys($before), array_keys($sections));
            $this->assertSame(array_keys($options), array_keys(RoleResource::getResourcePermissionOptions($entity)));
            $this->assertSame($pageKeys, array_keys(RoleResource::getPageOptions()));
        }
        $this->assertSame($permissions, Permission::orderBy('id')->get(['id', 'name', 'guard_name'])->toArray());
        $this->assertEquals($grants, DB::table('role_has_permissions')->orderBy('role_id')->orderBy('permission_id')->get()->toArray());
    }

    public function test_blank_translations_keep_original_heading_and_later_renames_are_not_stale(): void
    {
        $original = RoleResource::getResourceEntitiesSchema()[SidebarMenuResource::class]->getHeading();
        $name = SidebarMenuName::create(['target_key' => 'item:'.SidebarMenuResource::class, 'label_en' => 'First name']);
        $this->assertSame('First name', RoleResource::getResourceEntitiesSchema()[SidebarMenuResource::class]->getHeading());
        $name->update(['label_en' => 'Second name']);
        $this->assertSame('Second name', RoleResource::getResourceEntitiesSchema()[SidebarMenuResource::class]->getHeading());
        $name->update(['label_en' => null]);
        $this->assertSame($original, RoleResource::getResourceEntitiesSchema()[SidebarMenuResource::class]->getHeading());
    }

    public function test_role_form_keeps_existing_permission_selection_and_saves_the_same_grants(): void
    {
        $role = Role::create(['name' => 'navigation_reader', 'label_en' => 'Navigation Reader', 'role_type_key' => 'staff', 'guard_name' => 'web']);
        foreach (['ViewAny:SidebarMenu', 'Update:SidebarMenu', 'ViewAny:Role'] as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $before = $role->permissions()->pluck('name')->sort()->values()->all();
        SidebarMenuName::create(['target_key' => 'item:'.SidebarMenuResource::class, 'label_en' => 'Navigation Settings']);
        Livewire::test(EditRole::class, ['record' => $role->id])->assertSee('Navigation Settings')
            ->assertSet('data.'.SidebarMenuResource::class, ['ViewAny:SidebarMenu', 'Update:SidebarMenu'])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame($before, $role->fresh()->permissions()->pluck('name')->sort()->values()->all());
    }
}
