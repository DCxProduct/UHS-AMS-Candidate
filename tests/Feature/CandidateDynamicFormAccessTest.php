<?php

namespace Tests\Feature;

use App\Filament\Pages\StudentDynamicFormPage;
use App\Models\Role;
use App\Models\SystemUser;
use App\Models\User;
use App\Support\CandidateApplicationLevels;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormAuthorization;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CandidateDynamicFormAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        Http::fake();
        Mail::fake();
        Notification::fake();
        $user = User::query()->create(['registration_type' => 'student', 'name' => 'dynamic-level-owner', 'username' => 'dynamic-level-owner', 'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true]);
        $role = Role::findOrCreate('candidate', 'web');
        $role->givePermissionTo([
            Permission::findOrCreate('ViewAny:CustomFormEntry', 'web'),
            Permission::findOrCreate('Create:CustomFormEntry', 'web'),
        ]);
        $user->assignRole($role);
        SystemUser::query()->create(['name' => $user->name, 'username' => $user->username, 'roles' => ['national_entrance_exam_application_bachelor'], 'password' => 'password', 'is_active' => true]);
        $this->actingAs($user);
    }

    public function test_dynamic_route_requires_the_current_type_and_keeps_explicit_form_restrictions(): void
    {
        $form = $this->form('national_entrance_exam_application_associate');
        Livewire::test(StudentDynamicFormPage::class, ['slug' => $form->slug])->assertForbidden();

        CandidateApplicationLevels::switchTo(auth()->user(), 'national_entrance_exam_application_associate');
        Livewire::test(StudentDynamicFormPage::class, ['slug' => $form->slug])->assertOk();

        Role::findByName('candidate', 'web')->forceFill(['restrict_custom_form_access' => true, 'allowed_custom_form_ids' => []])->save();
        Livewire::test(StudentDynamicFormPage::class, ['slug' => $form->slug])->assertForbidden();
    }

    public function test_dynamic_save_rechecks_target_create_permission_before_raw_database_insert(): void
    {
        $form = $this->form('national_entrance_exam_application_bachelor');
        $page = Livewire::test(StudentDynamicFormPage::class, ['slug' => $form->slug])
            ->set('data.full_name', 'Own application');

        CustomFormAuthorization::query()->create(['custom_form_id' => $form->id, 'panel' => 'student', 'permissions' => ['view_any'], 'isolate_user_data' => true]);
        $page->call('save')->assertForbidden();

        $this->assertDatabaseCount('custom_form_entries', 0);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    private function form(string $type): CustomForm
    {
        $form = CustomForm::query()->create(['name' => 'Dynamic application', 'slug' => str_replace('_', '-', $type), 'allowed_roles' => [$type], 'is_active' => true]);
        $form->fields()->create(['name' => 'full_name', 'label' => 'Name', 'type' => 'text', 'options' => []]);

        return $form;
    }
}
