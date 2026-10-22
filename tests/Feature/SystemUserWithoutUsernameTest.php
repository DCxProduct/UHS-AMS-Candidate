<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\SystemUsers\Pages\CreateSystemUser;
use App\Filament\Admin\Resources\SystemUsers\Pages\EditSystemUser;
use App\Filament\Admin\Resources\SystemUsers\Pages\ListSystemUsers;
use App\Models\Role;
use App\Models\SystemUser;
use App\Models\User;
use App\Support\SystemUsername;
use App\Support\UserTypeOptions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class SystemUserWithoutUsernameTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));
        Role::query()->create(['name' => 'admin', 'guard_name' => 'web']);
        Role::query()->create(['name' => 'registrar_officer', 'guard_name' => 'web']);

        $admin = User::query()->forceCreate([
            'registration_type' => 'admin',
            'name' => 'admin_user',
            'username' => 'admin_user',
            'email' => 'admin_user@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $admin->assignRole('admin');
        $this->actingAs($admin);
    }

    public function test_username_is_made_from_the_email_or_the_phone(): void
    {
        $this->assertSame('regofficer', SystemUsername::generate('RegOfficer@gmail.com', '010999002'));
        $this->assertSame('staff_010999002', SystemUsername::generate(null, '010999002'));

        SystemUser::query()->forceCreate(['name' => 'regofficer', 'username' => 'regofficer', 'password' => 'x', 'is_active' => true]);
        $this->assertSame('regofficer_1', SystemUsername::generate('regofficer@other.test', null));
    }

    public function test_the_form_and_list_have_no_username(): void
    {
        Livewire::test(CreateSystemUser::class)
            ->assertFormFieldDoesNotExist('username')
            ->assertFormFieldExists('phone')
            ->assertFormFieldExists('first_name_latin')
            ->assertFormFieldExists('last_name_latin');
        Livewire::test(ListSystemUsers::class)
            ->assertTableColumnDoesNotExist('username')
            ->assertTableColumnExists('phone')
            ->assertTableColumnExists('full_name_latin')
            ->assertTableColumnDoesNotExist('first_name_latin')
            ->assertTableColumnDoesNotExist('last_name_latin');
    }

    public function test_creating_a_staff_account_makes_the_username_and_its_login_works(): void
    {
        Livewire::test(CreateSystemUser::class)
            ->fillForm([
                'email' => 'newstaff@example.test',
                'first_name_latin' => 'sithan',
                'last_name_latin' => 'viratana',
                'phone' => '010999100',
                'candidate_type' => array_key_first(UserTypeOptions::systemOptions()),
                'password' => 'Password123!',
                'password_confirmation' => 'Password123!',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $staff = SystemUser::query()->where('email', 'newstaff@example.test')->sole();
        $this->assertSame('newstaff', $staff->username);
        $this->assertSame('newstaff', $staff->name);
        $this->assertSame('SITHAN', $staff->first_name_latin);
        $this->assertSame('VIRATANA', $staff->last_name_latin);
        $this->assertTrue(User::query()->where('username', 'newstaff')->where('email', 'newstaff@example.test')->exists());
        $this->assertSame('SITHAN VIRATANA', User::query()->where('username', 'newstaff')->sole()->name_latin);
        $this->assertSame('SITHAN VIRATANA', User::query()->where('username', 'newstaff')->sole()->getFilamentName());

        Livewire::test(ListSystemUsers::class)
            ->assertTableColumnStateSet('full_name_latin', 'SITHAN VIRATANA', $staff);
    }

    public function test_editing_keeps_the_saved_username_and_name(): void
    {
        $staff = SystemUser::query()->forceCreate([
            'name' => 'regofficer',
            'username' => 'regofficer',
            'email' => 'regofficer@example.test',
            'phone' => '010999002',
            'password' => Hash::make('password'),
            'roles' => ['registrar_officer'],
            'is_active' => true,
        ]);

        Livewire::test(EditSystemUser::class, ['record' => $staff->getRouteKey()])
            ->fillForm(['email' => 'reg.officer@example.test'])
            ->call('save')
            ->assertHasNoFormErrors();

        $staff->refresh();
        $this->assertSame('010999002', $staff->phone);
        $this->assertSame('regofficer', $staff->username);
        $this->assertSame('regofficer', $staff->name);
        $this->assertSame('reg.officer@example.test', $staff->email);
    }

    public function test_edit_form_loads_existing_login_latin_name_and_saves_changes(): void
    {
        $staff = SystemUser::query()->forceCreate([
            'name' => 'registrar',
            'username' => 'registrar',
            'email' => 'registrar@example.test',
            'phone' => '010999003',
            'password' => Hash::make('password'),
            'roles' => ['registrar_officer'],
            'is_active' => true,
        ]);
        $staff->syncLoginUser();
        $staff->findLinkedLoginUser()->forceFill(['name_latin' => 'DARA SOK'])->save();

        Livewire::test(EditSystemUser::class, ['record' => $staff->getRouteKey()])
            ->assertFormFieldExists('first_name_latin')
            ->assertFormFieldExists('last_name_latin')
            ->assertFormSet([
                'first_name_latin' => 'DARA',
                'last_name_latin' => 'SOK',
            ])
            ->fillForm([
                'first_name_latin' => 'SOK',
                'last_name_latin' => 'DARA',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('SOK DARA', $staff->findLinkedLoginUser()->fresh()->name_latin);
    }
}
