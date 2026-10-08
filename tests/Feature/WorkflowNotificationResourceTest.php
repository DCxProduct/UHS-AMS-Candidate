<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\CreateWorkflowNotification;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\EditWorkflowNotification;
use App\Filament\Admin\Resources\WorkflowNotifications\Schemas\WorkflowNotificationForm;
use App\Filament\Admin\Resources\WorkflowNotifications\WorkflowNotificationResource;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Support\WorkflowNotificationStageSummary;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\Pages\EditCustomForm;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\Pages\ListCustomForms;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;
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
        Role::query()->create(['name' => 'registrar_officer', 'guard_name' => 'web'])
            ->givePermissionTo(Permission::findOrCreate('Accepted:CustomFormEntry', 'web'));
        Role::query()->create(['name' => 'cashier_officer', 'guard_name' => 'web'])
            ->givePermissionTo(Permission::findOrCreate('Update:Payment', 'web'));

        $this->admin = $this->user('admin_user', ['admin']);
    }

    public function test_admin_can_open_every_page(): void
    {
        $template = $this->template();
        $this->actingAs($this->admin);

        $this->get(WorkflowNotificationResource::getUrl('index'))
            ->assertOk()
            ->assertSee('Enrollment')
            ->assertSee(WorkflowNotificationResource::getUrl('edit', ['record' => $template]), false);
        $this->get(WorkflowNotificationResource::getUrl('create'))->assertOk();
        $this->get(WorkflowNotificationResource::getUrl('view', ['record' => $template]))->assertOk();
        $this->get(WorkflowNotificationResource::getUrl('edit', ['record' => $template]))->assertOk();
    }

    public function test_view_page_shows_numbered_stage_summary(): void
    {
        $template = WorkflowNotification::query()->create([
            'name' => 'Foreign Internship Registration',
            'stages' => [
                ['type' => 'stage', 'data' => [
                    'stage_name' => 'Submit application',
                    'stage_type' => 'form_submission',
                ]],
                ['type' => 'stage', 'data' => [
                    'stage_name' => 'Check documents',
                    'stage_type' => 'review',
                    'responsible_role' => 'registrar_officer',
                    'status_message' => 'Under review',
                    'notification_message' => 'Your documents are being checked.',
                ]],
                ['type' => 'stage', 'data' => [
                    'stage_name' => 'Payment',
                    'stage_type' => 'payment',
                    'responsible_role' => 'registrar_officer',
                ]],
                ['type' => 'stage', 'data' => [
                    'stage_name' => 'Completed',
                    'stage_type' => 'completed',
                ]],
            ],
        ]);

        $this->actingAs($this->admin);
        $originalLocale = app()->getLocale();

        try {
            app()->setLocale('en');

            $this->get(WorkflowNotificationResource::getUrl('view', ['record' => $template]))
                ->assertOk()
                ->assertSee('4 stages')
                ->assertSee('Submit application')
                ->assertSee('Form Submission')
                ->assertSee('Responsible: Candidate')
                ->assertSee('Check documents')
                ->assertSee('Responsible: Registrar Officer')
                ->assertSee('Under review')
                ->assertSee('Your documents are being checked.')
                ->assertSee('Payment')
                ->assertSee('Completed')
                ->assertSee('Responsible: Automatic');
        } finally {
            app()->setLocale($originalLocale);
        }
    }

    public function test_view_page_uses_the_reference_stage_card_styles(): void
    {
        $template = WorkflowNotification::query()->create([
            'name' => 'Styled workflow',
            'stages' => [[
                'type' => 'stage',
                'data' => [
                    'stage_name' => 'Review application',
                    'stage_type' => 'review',
                    'responsible_role' => 'registrar_officer',
                ],
            ]],
        ]);

        $this->actingAs($this->admin);

        $this->get(WorkflowNotificationResource::getUrl('view', ['record' => $template]))
            ->assertOk()
            ->assertSee('uhs-workflow-summary', false)
            ->assertSee('uhs-workflow-stage-card', false)
            ->assertSee('uhs-workflow-stage-card__number', false)
            ->assertSee('uhs-workflow-stage-card__badge--responsible', false)
            ->assertDontSee('shadow-sm', false);
    }

    public function test_view_page_localizes_stage_summary_labels(): void
    {
        $template = WorkflowNotification::query()->create([
            'name' => 'Khmer workflow',
            'stages' => [
                ['type' => 'stage', 'data' => [
                    'stage_name' => 'ការដាក់ពាក្យ',
                    'stage_type' => 'form_submission',
                ]],
            ],
        ]);

        $this->actingAs($this->admin);
        $originalLocale = app()->getLocale();

        try {
            app()->setLocale('km');

            $this->get(WorkflowNotificationResource::getUrl('view', ['record' => $template]))
                ->assertOk()
                ->assertSee('1 ដំណាក់កាល')
                ->assertSee('ការដាក់ស្នើទម្រង់')
                ->assertSee('អ្នកទទួលខុសត្រូវ៖ បេក្ខជន');
        } finally {
            app()->setLocale($originalLocale);
        }
    }

    public function test_view_page_localizes_known_default_stage_names_but_preserves_custom_names(): void
    {
        $template = WorkflowNotification::query()->create([
            'name' => 'Localized workflow',
            'stages' => [
                ['type' => 'stage', 'data' => [
                    'stage_name' => 'Review',
                    'stage_type' => 'review',
                    'responsible_role' => 'registrar_officer',
                ]],
                ['type' => 'stage', 'data' => [
                    'stage_name' => 'Custom checkpoint',
                    'stage_type' => 'review',
                    'responsible_role' => 'registrar_officer',
                ]],
            ],
        ]);

        $this->actingAs($this->admin);
        $originalLocale = app()->getLocale();

        try {
            app()->setLocale('km');

            $this->get(WorkflowNotificationResource::getUrl('view', ['record' => $template]))
                ->assertOk()
                ->assertSee('ការពិនិត្យ')
                ->assertSee('Custom checkpoint');
        } finally {
            app()->setLocale($originalLocale);
        }
    }

    public function test_stage_name_display_follows_locale_without_changing_custom_values(): void
    {
        $originalLocale = app()->getLocale();
        $customName = 'Custom checkpoint';

        try {
            app()->setLocale('en');
            $this->assertSame(
                'Review',
                WorkflowNotificationStageSummary::localizedName('Review', WorkflowStageType::Review),
            );
            $this->assertSame(
                $customName,
                WorkflowNotificationStageSummary::localizedName($customName, WorkflowStageType::Review),
            );

            app()->setLocale('km');
            $this->assertSame(
                'ការពិនិត្យ',
                WorkflowNotificationStageSummary::localizedName('Review', WorkflowStageType::Review),
            );
            $this->assertSame(
                $customName,
                WorkflowNotificationStageSummary::localizedName($customName, WorkflowStageType::Review),
            );
        } finally {
            app()->setLocale($originalLocale);
        }
    }

    public function test_view_table_action_uses_a_modal_instead_of_the_view_url(): void
    {
        $template = $this->template();
        $this->actingAs($this->admin);

        $this->get(WorkflowNotificationResource::getUrl('index'))
            ->assertOk()
            ->assertSee("wire:click=\"mountAction('view'", false)
            ->assertDontSee('href="'.WorkflowNotificationResource::getUrl('view', ['record' => $template]).'"', false);
    }

    public function test_workflow_notification_updated_at_shows_date_without_time(): void
    {
        $template = $this->template();
        $template->forceFill([
            'updated_at' => Carbon::create(2026, 10, 6, 14, 30, 0),
        ])->saveQuietly();

        $this->actingAs($this->admin);
        $originalLocale = app()->getLocale();

        try {
            app()->setLocale('en');

            $this->get(WorkflowNotificationResource::getUrl('index'))
                ->assertOk()
                ->assertSee('06-Oct-2026')
                ->assertDontSee('14:30:00');
        } finally {
            app()->setLocale($originalLocale);
        }
    }

    public function test_workflow_notification_updated_at_uses_khmer_date_format(): void
    {
        $template = $this->template();
        $template->forceFill([
            'updated_at' => Carbon::create(2026, 10, 6, 14, 30, 0),
        ])->saveQuietly();

        $this->actingAs($this->admin);
        $originalLocale = app()->getLocale();

        try {
            app()->setLocale('km');

            $this->get(WorkflowNotificationResource::getUrl('index'))
                ->assertOk()
                ->assertSee('០៦-តុលា-២០២៦')
                ->assertDontSee('14:30:00');
        } finally {
            app()->setLocale($originalLocale);
        }
    }

    public function test_workflow_notification_candidate_terminology_is_bilingual(): void
    {
        $this->assertSame('Candidate', __('workflow_notifications.view.candidate', [], 'en'));
        $this->assertSame('បេក្ខជន', __('workflow_notifications.view.candidate', [], 'km'));
        $this->assertSame('Candidate Status Message (Optional)', __('workflow_notifications.fields.status_message', [], 'en'));
        $this->assertSame('សារស្ថានភាពសម្រាប់បេក្ខជន (ជាជម្រើស)', __('workflow_notifications.fields.status_message', [], 'km'));
        $this->assertSame('Candidate Notification Message (Optional)', __('workflow_notifications.fields.notification_message', [], 'en'));
        $this->assertSame('សារជូនដំណឹងសម្រាប់បេក្ខជន (ជាជម្រើស)', __('workflow_notifications.fields.notification_message', [], 'km'));
        $this->assertSame('Example: Candidate Admission', __('workflow_notifications.placeholders.name', [], 'en'));
        $this->assertSame('ឧទាហរណ៍៖ ការចុះឈ្មោះបេក្ខជន', __('workflow_notifications.placeholders.name', [], 'km'));
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
                        'responsible_role' => 'cashier_officer',
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

    public function test_stage_type_options_have_the_requested_stages(): void
    {
        $originalLocale = app()->getLocale();

        try {
            app()->setLocale('en');

            $this->assertSame([
                'form_submission',
                'review',
                'payment',
                'awaiting_results',
                'completed',
            ], array_keys(WorkflowStageType::options()));
            $this->assertSame('Awaiting Results', WorkflowStageType::AwaitingResults->label());
            $this->assertSame('Completed', WorkflowStageType::Completed->label());

            app()->setLocale('km');

            $this->assertSame('ការដាក់ស្នើទម្រង់', WorkflowStageType::FormSubmission->label());
            $this->assertSame('រង់ចាំលទ្ធផល', WorkflowStageType::AwaitingResults->label());
            $this->assertSame('បញ្ចប់', WorkflowStageType::Completed->label());
        } finally {
            app()->setLocale($originalLocale);
        }

        $this->assertSame(WorkflowStageType::Approval, WorkflowStageType::tryFrom('approval'));
        $this->assertFalse(array_key_exists('approval', WorkflowStageType::options()));
        $this->assertFalse(WorkflowStageType::FormSubmission->requiresRole());
        $this->assertTrue(WorkflowStageType::Completed->requiresRole());
        $this->assertTrue(WorkflowStageType::Review->requiresRole());
        $this->assertTrue(WorkflowStageType::Payment->requiresRole());
        $this->assertTrue(WorkflowStageType::AwaitingResults->requiresRole());
    }

    public function test_first_stage_type_selection_sets_an_editable_localized_stage_name_default(): void
    {
        $this->actingAs($this->admin);
        $originalLocale = app()->getLocale();

        try {
            foreach (['en', 'km'] as $locale) {
                app()->setLocale($locale);

                foreach (array_keys(WorkflowStageType::options()) as $stageType) {
                    $component = Livewire::test(CreateWorkflowNotification::class);
                    $stageKey = array_key_first($component->get('data.stages'));
                    $stagePath = "data.stages.{$stageKey}.data";
                    $expectedStageName = WorkflowStageType::from($stageType)->label();

                    $component
                        ->set("{$stagePath}.stage_type", $stageType)
                        ->assertSet("{$stagePath}.stage_name", $expectedStageName);
                }
            }
        } finally {
            app()->setLocale($originalLocale);
        }
    }

    public function test_changing_stage_type_does_not_replace_the_stage_name(): void
    {
        $this->actingAs($this->admin);

        $component = Livewire::test(CreateWorkflowNotification::class);
        $stageKey = array_key_first($component->get('data.stages'));
        $stagePath = "data.stages.{$stageKey}.data";

        $component
            ->set("{$stagePath}.stage_type", WorkflowStageType::Review->value)
            ->assertSet("{$stagePath}.stage_name", WorkflowStageType::Review->label())
            ->set("{$stagePath}.stage_name", 'Check documents')
            ->set("{$stagePath}.stage_type", WorkflowStageType::Payment->value)
            ->assertSet("{$stagePath}.stage_name", 'Check documents');
    }

    public function test_completed_stage_uses_an_automatic_responsible_role(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateWorkflowNotification::class)
            ->set('data.stages', [])
            ->fillForm([
                'name' => 'Completed workflow',
                'stages' => [
                    'a' => [
                        'type' => 'stage',
                        'data' => [
                            'stage_name' => 'Completed',
                            'stage_type' => 'completed',
                        ],
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $template = WorkflowNotification::query()->firstOrFail();

        $this->assertNull(data_get($template->stages, '0.data.responsible_role'));
        $this->assertSame('Automatic', __('workflow_notifications.placeholders.automatic_role', [], 'en'));
        $this->assertSame('ស្វ័យប្រវត្តិ', __('workflow_notifications.placeholders.automatic_role', [], 'km'));
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

    public function test_responsible_role_lists_every_role_except_the_candidate_role_type(): void
    {
        Role::query()->create(['name' => 'candidate', 'guard_name' => 'web', 'role_type_key' => 'candidate']);
        $staffRoles = ['admin', 'registrar_officer', 'cashier_officer'];

        foreach (['review', 'payment', 'awaiting_results', 'approval'] as $stageType) {
            $this->assertSame($staffRoles, array_keys(WorkflowNotificationForm::responsibleRoleOptions($stageType)));
        }

        $this->assertSame([], WorkflowNotificationForm::responsibleRoleOptions('form_submission'));
        $this->assertSame([], WorkflowNotificationForm::responsibleRoleOptions('completed'));
    }

    public function test_the_candidate_role_cannot_be_responsible_for_a_stage(): void
    {
        Role::query()->create(['name' => 'candidate', 'guard_name' => 'web', 'role_type_key' => 'candidate']);
        $this->actingAs($this->admin);

        Livewire::test(CreateWorkflowNotification::class)
            ->set('data.stages', [])
            ->fillForm([
                'name' => 'Wrong role',
                'stages' => ['a' => ['type' => 'stage', 'data' => [
                    'stage_name' => 'Payment',
                    'stage_type' => 'payment',
                    'responsible_role' => 'candidate',
                ]]],
            ])
            ->call('create')
            ->assertHasFormErrors();

        $this->assertSame(0, WorkflowNotification::query()->count());
    }

    public function test_changing_stage_type_clears_the_responsible_role(): void
    {
        $this->actingAs($this->admin);

        $component = Livewire::test(CreateWorkflowNotification::class);
        $stagePath = 'data.stages.'.array_key_first($component->get('data.stages')).'.data';

        $component
            ->set("{$stagePath}.stage_type", 'review')
            ->set("{$stagePath}.responsible_role", 'registrar_officer')
            ->set("{$stagePath}.stage_type", 'payment')
            ->assertSet("{$stagePath}.responsible_role", null);
    }

    public function test_template_names_must_be_unique(): void
    {
        $this->template('Enrollment');
        $this->actingAs($this->admin);

        Livewire::test(CreateWorkflowNotification::class)
            ->set('data.stages', [])
            ->fillForm([
                'name' => 'Enrollment',
                'stages' => ['a' => ['type' => 'stage', 'data' => ['stage_name' => 'Submit', 'stage_type' => 'form_submission']]],
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'unique'])
            ->assertNotified(__('workflow_notifications.validation.could_not_save'));
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
