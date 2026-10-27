<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\CustomFormRoleAccess;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\CreateRole;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\EditRole;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RoleFormRestrictionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');

        Role::query()->create(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::query()->forceCreate([
            'registration_type' => 'admin',
            'name' => 'Role form administrator',
            'username' => 'role_form_admin',
            'email' => 'role_form_admin@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => 'password',
            'is_active' => true,
        ]);
        $admin->assignRole('admin');
        $this->actingAs($admin);
    }

    public function test_role_can_be_created_with_multiple_active_candidate_forms(): void
    {
        $forms = $this->forms();

        Livewire::test(CreateRole::class)
            ->assertSuccessful()
            ->fillForm([
                'name' => 'restricted_reviewer',
                'label_en' => 'Restricted reviewer',
                'role_type_key' => 'staff',
                'restrict_custom_form_access' => true,
                'allowed_custom_form_ids' => array_map('strval', $forms),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $role = Role::query()->where('name', 'restricted_reviewer')->sole();
        $this->assertTrue($role->restrict_custom_form_access);
        $this->assertSame($forms, $role->allowed_custom_form_ids);
    }

    public function test_editing_candidate_form_selection_preserves_existing_permissions(): void
    {
        $forms = $this->forms();
        $role = $this->role([$forms[0]]);
        $permission = Permission::findOrCreate('ViewAny:CustomFormEntry', 'web');
        $role->givePermissionTo($permission);

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->assertSuccessful()
            ->set('data.allowed_custom_form_ids', array_map('strval', $forms))
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($forms, $role->fresh()->allowed_custom_form_ids);
        $this->assertTrue($role->fresh()->hasPermissionTo($permission));
    }

    public function test_unknown_candidate_form_selection_is_rejected(): void
    {
        $forms = $this->forms();
        $role = $this->role($forms);

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->set('data.allowed_custom_form_ids', [(string) $forms[0], '999999'])
            ->call('save')
            ->assertHasFormErrors(['allowed_custom_form_ids.1' => 'in']);

        $this->assertSame($forms, $role->fresh()->allowed_custom_form_ids);
    }

    public function test_existing_unavailable_form_ids_do_not_block_editing_visible_form_selection(): void
    {
        $forms = $this->forms();
        $inactive = CustomForm::query()->forceCreate([
            'name' => 'Inactive application', 'slug' => 'inactive-application', 'is_active' => false,
        ]);
        $profile = CustomForm::query()->forceCreate([
            'name' => 'Profile', 'slug' => CustomForm::PROFILE_SLUG, 'is_active' => true,
        ]);
        $deleted = CustomForm::query()->forceCreate([
            'name' => 'Deleted application', 'slug' => 'deleted-application', 'is_active' => true,
        ]);
        $deletedId = $deleted->getKey();
        $deleted->delete();
        $role = $this->role([...$forms, $inactive->getKey(), $profile->getKey(), $deletedId, 999999]);

        $component = Livewire::test(EditRole::class, ['record' => $role->getRouteKey()]);
        $component->call('save')->assertHasNoFormErrors();
        $this->assertSame($forms, array_map('intval', $component->get('data.allowed_custom_form_ids')));

        $this->assertSame($forms, $role->fresh()->allowed_custom_form_ids);
        $this->assertTrue($role->fresh()->restrict_custom_form_access);
    }

    public function test_selecting_all_permissions_keeps_candidate_form_restrictions_unchanged(): void
    {
        $forms = $this->forms();
        $selected = [$forms[0]];
        $role = $this->role($selected);

        $component = Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->set('data.select_all', true);

        $this->assertSame($selected, array_map('intval', $component->get('data.allowed_custom_form_ids')));

        $component->call('save')->assertHasNoFormErrors();
        $this->assertSame($selected, $role->fresh()->allowed_custom_form_ids);

        $component->set('data.select_all', false);
        $this->assertSame($selected, array_map('intval', $component->get('data.allowed_custom_form_ids')));
        $component->call('save')->assertHasNoFormErrors();
        $this->assertSame($selected, $role->fresh()->allowed_custom_form_ids);
    }

    public function test_removing_all_unavailable_selections_keeps_the_role_restricted(): void
    {
        $forms = $this->forms();
        $role = $this->role([999999]);

        Livewire::test(EditRole::class, ['record' => $role->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $role->refresh();
        $this->assertTrue($role->restrict_custom_form_access);
        $this->assertSame([], $role->allowed_custom_form_ids);

        $staff = User::query()->forceCreate([
            'registration_type' => 'admin',
            'name' => 'Restricted reviewer',
            'username' => 'restricted_reviewer',
            'email' => 'restricted_reviewer@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => 'password',
            'is_active' => true,
        ]);
        $staff->assignRole($role);
        $this->assertFalse(CustomFormRoleAccess::canAccessForm($staff, $forms[0]));
    }

    private function forms(): array
    {
        return collect([101, 205, 306])->map(function (int $id): int {
            return CustomForm::query()->forceCreate([
                'id' => $id,
                'name' => json_encode(['en' => 'Candidate application '.$id, 'km' => 'ពាក្យស្នើសុំ '.$id], JSON_UNESCAPED_UNICODE),
                'slug' => 'candidate-application-'.$id,
                'is_active' => true,
            ])->getKey();
        })->all();
    }

    private function role(array $formIds): Role
    {
        return Role::query()->create([
            'name' => 'restricted_reviewer',
            'label_en' => 'Restricted reviewer',
            'guard_name' => 'web',
            'role_type_key' => 'staff',
            'restrict_custom_form_access' => true,
            'allowed_custom_form_ids' => $formIds,
        ]);
    }
}
