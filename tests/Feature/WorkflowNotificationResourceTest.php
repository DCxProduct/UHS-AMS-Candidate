<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\WorkflowNotifications\Pages\CreateWorkflowNotification;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\EditWorkflowNotification;
use App\Filament\Admin\Resources\WorkflowNotifications\WorkflowNotificationResource;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\Pages\EditCustomForm;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\Pages\ListCustomForms;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WorkflowNotificationResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('app'));

        Role::query()->create(['name' => 'admin', 'guard_name' => 'web']);
        Role::query()->create(['name' => 'registrar_officer', 'guard_name' => 'web']);

        $this->admin = $this->user('admin_user', ['admin']);
    }

    public function test_admin_can_open_every_page(): void
    {
        $template = $this->template();
        $this->actingAs($this->admin);

        $this->get(WorkflowNotificationResource::getUrl('index'))->assertOk()->assertSee('Enrollment');
        $this->get(WorkflowNotificationResource::getUrl('create'))->assertOk();
        $this->get(WorkflowNotificationResource::getUrl('view', ['record' => $template]))->assertOk();
        $this->get(WorkflowNotificationResource::getUrl('edit', ['record' => $template]))->assertOk();
    }

    public function test_access_follows_role_permissions(): void
    {
        $template = $this->template();

        $this->actingAs($this->user('no_access'));
        $this->get(WorkflowNotificationResource::getUrl('index'))->assertForbidden();

        // The four permissions Shield generates for a resource in this project.
        foreach (['ViewAny', 'Create', 'Update', 'Delete'] as $action) {
            Permission::findOrCreate("{$action}:WorkflowNotification", 'web');
        }
        Role::findByName('registrar_officer')->givePermissionTo('ViewAny:WorkflowNotification');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // AuthenticateSession ties the session to one user; start a new one.
        $this->flushSession();
        $this->actingAs($this->user('read_only', ['registrar_officer']));
        $this->get(WorkflowNotificationResource::getUrl('index'))->assertOk();
        $this->get(WorkflowNotificationResource::getUrl('view', ['record' => $template]))->assertOk();
        $this->get(WorkflowNotificationResource::getUrl('create'))->assertForbidden();
        $this->get(WorkflowNotificationResource::getUrl('edit', ['record' => $template]))->assertForbidden();
    }

    public function test_template_with_sequential_stages_can_be_created(): void
    {
        $this->actingAs($this->admin);

        // The create page starts with one blank stage; replace it.
        Livewire::test(CreateWorkflowNotification::class)
            ->set('data.stages', [])
            ->fillForm([
                'name' => 'Foreign Internship Registration',
                'stages' => [
                    'a' => ['type' => 'stage', 'data' => ['stage_name' => 'Submit application', 'stage_type' => 'form_submission']],
                    'b' => ['type' => 'stage', 'data' => [
                        'stage_name' => 'Check documents',
                        'stage_type' => 'review',
                        'responsible_role' => 'registrar_officer',
                        'status_message' => 'Under review',
                        'notification_message' => 'Your documents are being checked.',
                    ]],
                    'c' => ['type' => 'stage', 'data' => [
                        'stage_name' => 'Payment',
                        'stage_type' => 'payment',
                        'responsible_role' => 'registrar_officer',
                    ]],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $template = WorkflowNotification::query()->firstOrFail();

        $this->assertSame('Foreign Internship Registration', $template->name);
        $this->assertSame(3, $template->steps_count);
        $this->assertSame([], $template->forms()->pluck('custom_forms.id')->all());
    }

    public function test_staff_stage_requires_a_responsible_role(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateWorkflowNotification::class)
            ->set('data.stages', [])
            ->fillForm([
                'name' => 'Missing role',
                'stages' => ['a' => ['type' => 'stage', 'data' => ['stage_name' => 'Review', 'stage_type' => 'review']]],
            ])
            ->call('create')
            ->assertHasFormErrors();

        $this->assertSame(0, WorkflowNotification::query()->count());
    }

    public function test_template_needs_at_least_one_stage(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateWorkflowNotification::class)
            ->fillForm(['name' => 'Empty', 'stages' => []])
            ->call('create')
            ->assertHasFormErrors(['stages']);
    }

    public function test_add_stage_button_appends_a_stage(): void
    {
        $this->actingAs($this->admin);

        $component = Livewire::test(CreateWorkflowNotification::class);
        $initial = count($component->get('data.stages'));

        $component->callAction(TestAction::make('addStage')->schemaComponent('stages-section', schema: 'form'));

        $types = collect($component->get('data.stages'))->pluck('type')->values()->all();

        $this->assertCount($initial + 1, $types);
        $this->assertSame(['stage'], array_slice($types, -1));
    }

    public function test_a_form_cannot_belong_to_two_templates(): void
    {
        $form = $this->customForm();
        $this->template()->forms()->attach($form);

        $this->expectException(QueryException::class);
        $this->template('Second')->forms()->attach($form);
    }

    public function test_deleting_a_template_releases_its_forms(): void
    {
        $form = $this->customForm();
        $template = $this->template();
        $template->forms()->attach($form);
        $this->actingAs($this->admin);

        Livewire::test(EditWorkflowNotification::class, ['record' => $template->getRouteKey()])
            ->callAction('delete');

        $this->assertModelMissing($template);
        $this->assertDatabaseMissing('workflow_notification_forms', ['custom_form_id' => $form->id]);
        $this->assertModelExists($form);
    }

    public function test_custom_forms_list_shows_each_forms_workflow_notification(): void
    {
        $assigned = $this->customForm();
        $unassigned = $this->customForm();
        $template = $this->template();
        $template->forms()->attach($assigned);
        $this->actingAs($this->admin);

        Livewire::test(ListCustomForms::class)
            ->assertTableColumnStateSet('workflow_notification', 'Enrollment', $assigned)
            ->assertTableColumnStateSet('workflow_notification', null, $unassigned)
            ->sortTable('workflow_notification')
            ->assertCanSeeTableRecords([$unassigned, $assigned], inOrder: true)
            ->assertSee(__('workflow_notifications.not_assigned'))
            ->assertSee(WorkflowNotificationResource::getUrl('view', ['record' => $template]));
    }

    public function test_custom_form_page_can_pick_change_and_clear_its_workflow_notification(): void
    {
        $form = $this->customForm();
        $first = $this->template();
        $second = $this->template('Second');
        $this->actingAs($this->admin);

        Livewire::test(EditCustomForm::class, ['record' => $form->getRouteKey()])
            ->assertSchemaStateSet(['workflow_notification_id' => null])
            ->fillForm(['workflow_notification_id' => $first->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($first->id, WorkflowNotification::forForm($form->id)?->id);

        Livewire::test(EditCustomForm::class, ['record' => $form->getRouteKey()])
            ->assertSchemaStateSet(['workflow_notification_id' => $first->id])
            ->fillForm(['workflow_notification_id' => $second->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($second->id, WorkflowNotification::forForm($form->id)?->id);
        $this->assertDatabaseCount('workflow_notification_forms', 1);

        Livewire::test(EditCustomForm::class, ['record' => $form->getRouteKey()])
            ->fillForm(['workflow_notification_id' => null])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertNull(WorkflowNotification::forForm($form->id));
    }

    private function template(string $name = 'Enrollment'): WorkflowNotification
    {
        return WorkflowNotification::query()->create([
            'name' => $name,
            'stages' => [
                ['type' => 'stage', 'data' => ['stage_name' => 'Submit', 'stage_type' => 'form_submission']],
                ['type' => 'stage', 'data' => ['stage_name' => 'Review', 'stage_type' => 'review', 'responsible_role' => 'registrar_officer']],
            ],
        ]);
    }

    private function customForm(): CustomForm
    {
        return CustomForm::query()->create([
            'name' => 'Admission Form '.uniqid(),
            'slug' => 'admission-form-'.uniqid(),
            'is_active' => true,
            'requires_payment' => false,
        ]);
    }

    private function user(string $username, array $roles = []): User
    {
        $user = User::query()->forceCreate([
            // Staff accounts use registration_type admin, as in the existing test suite.
            'registration_type' => 'admin',
            'name' => $username,
            'username' => $username,
            'email' => $username.'@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        foreach ($roles as $role) {
            $user->assignRole($role);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }
}
