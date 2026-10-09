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
            ->assertFormFieldExists('phone');
        Livewire::test(ListSystemUsers::class)
            ->assertTableColumnDoesNotExist('username')
            ->assertTableColumnExists('phone');
    }

    public function test_creating_a_staff_account_makes_the_username_and_its_login_works(): void
    {
        Livewire::test(CreateSystemUser::class)
            ->fillForm([
                'email' => 'newstaff@example.test',
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
        $this->assertTrue(User::query()->where('username', 'newstaff')->where('email', 'newstaff@example.test')->exists());
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
}
