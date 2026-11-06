<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Models\Role;
use App\Models\SystemUser;
use App\Models\User;
use App\Providers\Filament\AppPanelProvider;
use App\Support\CandidateApplicationLevels;
use App\Support\DashboardMetrics;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CandidateApplicationLevelsTest extends TestCase
{
    use RefreshDatabase;

    private const BACHELOR = 'national_entrance_exam_application_bachelor';

    private const ASSOCIATE = 'national_entrance_exam_application_associate';

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
    }

    public function test_selected_level_is_visible_consistently_in_resource_sidebar_access_and_dashboard(): void
    {
        $user = $this->candidate('multi-level-visibility');
        $profile = CustomForm::query()->create(['name' => 'Profile', 'slug' => 'profile', 'is_active' => true]);
        CustomFormEntry::query()->forceCreate(['custom_form_id' => $profile->id, 'created_by' => $user->id, 'review_status' => 'pending', 'data' => ['registration_status' => 'pending']]);
        $bachelor = $this->form('Bachelor', self::BACHELOR);
        $associate = $this->form('Associate', self::ASSOCIATE);
        $provider = new AppPanelProvider(app());
        $access = new ReflectionMethod(AppPanelProvider::class, 'currentUserCanAccessDynamicForm');

        $this->assertTrue(CustomFormEntryResource::canCurrentUserAccessForm($bachelor));
        $this->assertFalse(CustomFormEntryResource::canCurrentUserAccessForm($associate));
        $this->assertFalse($access->invoke($provider, $associate->id));
        $this->assertNotContains($associate->id, array_column(DashboardMetrics::studentAvailableForms($user->id), 'id'));

        CandidateApplicationLevels::switchTo($user, self::ASSOCIATE);

        $this->assertFalse(CustomFormEntryResource::canCurrentUserAccessForm($bachelor));
        $this->assertTrue(CustomFormEntryResource::canCurrentUserAccessForm($associate));
        $this->assertFalse($access->invoke($provider, $bachelor->id));
        $this->assertTrue($access->invoke($provider, $associate->id));
        $this->assertNotContains($bachelor->id, array_column(DashboardMetrics::studentAvailableForms($user->id), 'id'));
        $this->assertContains($associate->id, array_column(DashboardMetrics::studentAvailableForms($user->id), 'id'));

        Role::findByName('candidate', 'web')->forceFill(['restrict_custom_form_access' => true, 'allowed_custom_form_ids' => [$bachelor->id]])->save();
        $this->assertFalse(CustomFormEntryResource::canCurrentUserAccessForm($associate));
        $this->assertFalse($access->invoke($provider, $associate->id));
        $this->assertNotContains($associate->id, array_column(DashboardMetrics::studentAvailableForms($user->id), 'id'));
    }

    public function test_uncategorized_legacy_applicant_keeps_only_actual_role_form_access(): void
    {
        $masterType = 'national_exit_exam_application_master_of_science';
        CandidateApplicationLevels::options();
        $user = User::query()->create([
            'registration_type' => 'student', 'name' => 'Legacy applicant', 'username' => 'uncategorized-legacy-applicant',
            'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true,
        ]);
        $candidateRole = Role::findOrCreate('candidate', 'web');
        $candidateRole->givePermissionTo(Permission::findOrCreate('ViewAny:CustomFormEntry', 'web'));
        $user->assignRole($candidateRole, Role::findOrCreate('submission_applicant', 'web'));
        $this->actingAs($user);
        $profile = CustomForm::query()->create(['name' => 'Profile', 'slug' => 'profile', 'is_active' => true]);
        CustomFormEntry::query()->forceCreate(['custom_form_id' => $profile->id, 'created_by' => $user->id, 'review_status' => 'pending', 'data' => ['registration_status' => 'pending']]);
        $legacy = $this->form('Legacy application', 'submission_applicant');
        $master = $this->form('Master application', $masterType);
        $provider = new AppPanelProvider(app());
        $access = new ReflectionMethod(AppPanelProvider::class, 'currentUserCanAccessDynamicForm');

        $this->assertNull($user->linkedSystemUser());
        $this->assertNull(CandidateApplicationLevels::currentType($user));
        $this->assertContains('submission_applicant', CandidateApplicationLevels::formRoleKeys($user));
        $this->assertNotContains($masterType, CandidateApplicationLevels::formRoleKeys($user));
        $this->assertTrue(CustomFormEntryResource::canCurrentUserAccessForm($legacy));
        $this->assertFalse(CustomFormEntryResource::canCurrentUserAccessForm($master));
        $this->assertTrue($access->invoke($provider, $legacy->id));
        $this->assertFalse($access->invoke($provider, $master->id));
        $this->assertSame([$legacy->id], array_column(DashboardMetrics::studentAvailableForms($user->id), 'id'));
    }

    public function test_staff_cannot_use_the_candidate_switch_action(): void
    {
        $staff = User::query()->create(['registration_type' => 'admin', 'name' => 'staff-level', 'username' => 'staff-level', 'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true]);
        $staff->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($staff);

        Livewire::test(Dashboard::class)->assertActionHidden('switch_application_level');
        $this->expectException(HttpException::class);
        CandidateApplicationLevels::switchTo($staff, self::ASSOCIATE);
    }

    private function candidate(string $name): User
    {
        $user = User::query()->create(['registration_type' => 'student', 'name' => $name, 'username' => $name, 'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true]);
        $role = Role::findOrCreate('candidate', 'web');
        $role->givePermissionTo(Permission::findOrCreate('ViewAny:CustomFormEntry', 'web'));
        $user->assignRole($role);
        SystemUser::query()->create(['name' => $name, 'username' => $name, 'password' => 'password', 'roles' => [self::BACHELOR], 'is_active' => true]);
        $this->actingAs($user);

        return $user;
    }

    private function form(string $name, string $type): CustomForm
    {
        return CustomForm::query()->create(['name' => $name, 'slug' => str_replace('_', '-', $type), 'allowed_roles' => [$type], 'is_active' => true]);
    }
}
