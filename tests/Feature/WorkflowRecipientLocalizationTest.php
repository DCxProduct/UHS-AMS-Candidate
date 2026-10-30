<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\WorkflowNotifications\Pages\EditWorkflowNotification;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Support\UserTypeOptions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkflowRecipientLocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        $admin = $this->user('recipient_localization_admin');
        $role = Role::findOrCreate('admin', 'web');
        $role->forceFill([
            'label_en' => 'Administrator',
            'name_kh' => 'អ្នកគ្រប់គ្រងប្រព័ន្ធ',
        ])->save();
        $admin->assignRole($role);
        $this->actingAs($admin);
    }

    public static function locales(): array
    {
        return [['en'], ['km']];
    }

    #[DataProvider('locales')]
    public function test_all_seven_role_pickers_localize_labels_without_changing_role_names(string $locale): void
    {
        $labels = $this->roles();
        $roles = array_keys($labels['en']);
        $workflow = $this->workflow($roles);
        app()->setLocale($locale);
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $pickers = $this->pickers($component);
        $this->assertCount(7, $pickers);
        $component->assertSee(__('workflow_notifications.helpers.review_staff_recipients'));

        foreach ($pickers as $picker) {
            $this->assertSame($labels[$locale], $picker->getOptions());
            $this->assertEquals($roles, $picker->getState());
            $this->assertSame($labels[$locale], $picker->getOptionLabels());
            $this->assertTrue($picker->isMultiple());
            $this->assertSame(__('workflow_notifications.fields.staff_recipient_roles'), $picker->getLabel());
            $this->assertSame(__('workflow_notifications.placeholders.staff_recipient_roles'), $picker->getPlaceholder());
        }
    }

    public function test_saving_and_reopening_in_another_locale_preserves_selected_roles(): void
    {
        $labels = $this->roles();
        $selected = ['cashier_manager', 'registrar_officer'];
        $workflow = $this->workflow(['admin']);
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        foreach ($this->pickers($component) as $picker) {
            $component->set($picker->getStatePath(), $selected);
        }
        $component->call('save')->assertHasNoFormErrors();
        $this->assertSelections($workflow->fresh(), $selected);

        app()->setLocale('km');
        $reopened = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $expectedLabels = array_intersect_key($labels['km'], array_flip($selected));
        foreach ($this->pickers($reopened) as $picker) {
            $this->assertEquals($selected, $picker->getState());
            $this->assertSame($expectedLabels, $picker->getOptionLabels());
        }
        $reopened->call('save')->assertHasNoFormErrors();
        $this->assertSelections($workflow->fresh(), $selected);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_editing_legacy_recipient_ids_saves_roles_and_preserves_existing_messages(): void
    {
        $this->roles();
        $staff = $this->user('legacy_selected_staff');
        $workflow = $this->workflow([]);
        $stages = $workflow->stages;
        $messagePaths = [
            '0.data.submission_messages',
            '1.data.review_actions.accept',
            '1.data.review_actions.send_back',
            '1.data.review_actions.reject',
            '2.data.audience_messages',
            '3.data.audience_messages',
            '4.data.audience_messages',
        ];
        foreach ($messagePaths as $path) {
            data_set($stages, $path, [
                'staff_recipient_ids' => [$staff->getKey()],
                'staff_notification_message' => 'Existing staff message',
            ]);
        }
        data_set($stages, '0.data.submission_messages.candidate_status_message', 'Submitted');
        data_set($stages, '0.data.submission_messages.candidate_notification_message', 'Existing candidate submission message');
        foreach (['accept', 'send_back', 'reject'] as $action) {
            data_set($stages, '1.data.review_actions.'.$action.'.status_message', 'Existing '.$action.' status');
            data_set($stages, '1.data.review_actions.'.$action.'.notification_message', 'Existing '.$action.' candidate message');
        }
        $workflow->update(['stages' => $stages]);
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        foreach ($this->pickers($component) as $picker) {
            $component->set($picker->getStatePath(), ['admin']);
        }
        $component->call('save')->assertHasNoFormErrors();
        $saved = $workflow->fresh()->stages;
        $this->assertSelections($workflow->fresh(), ['admin']);
        foreach ($messagePaths as $path) {
            $messages = data_get($saved, $path);
            $this->assertArrayNotHasKey('staff_recipient_ids', $messages);
            $this->assertSame('Existing staff message', $messages['staff_notification_message']);
        }
        $this->assertSame('Submitted', data_get($saved, '0.data.submission_messages.candidate_status_message'));
        $this->assertSame('Existing candidate submission message', data_get($saved, '0.data.submission_messages.candidate_notification_message'));
        foreach (['accept', 'send_back', 'reject'] as $action) {
            $this->assertSame('Existing '.$action.' status', data_get($saved, '1.data.review_actions.'.$action.'.status_message'));
            $this->assertSame('Existing '.$action.' candidate message', data_get($saved, '1.data.review_actions.'.$action.'.notification_message'));
        }
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_role_pickers_never_list_personal_names_or_numeric_account_ids(): void
    {
        $this->roles();
        $staff = $this->user('personal_cashier', ['name' => 'Sok Dara']);
        $staff->assignRole('cashier_manager');
        $workflow = $this->workflow([]);

        foreach (['en', 'km'] as $locale) {
            app()->setLocale($locale);
            $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
            foreach ($this->pickers($component) as $picker) {
                $options = $picker->getOptions();
                $this->assertArrayNotHasKey($staff->getKey(), $options);
                $this->assertNotContains('Sok Dara', $options);
                foreach (array_keys($options) as $name) {
                    $this->assertIsString($name);
                    $this->assertFalse(ctype_digit($name));
                }
            }
        }
    }

    public function test_all_seven_role_pickers_reject_numeric_user_ids(): void
    {
        $this->roles();
        $staff = $this->user('invalid_recipient_id');
        $workflow = $this->workflow([]);
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $errors = [];
        foreach ($this->pickers($component) as $picker) {
            $component->set($picker->getStatePath(), [$staff->getKey()]);
            $errors[] = $picker->getStatePath();
        }
        $component->call('save')->assertHasErrors();
        $errorPaths = array_keys($component->errors()->toArray());
        foreach ($errors as $path) {
            $this->assertTrue(collect($errorPaths)->contains(fn (string $error): bool => $error === $path || str_starts_with($error, $path.'.')), 'Missing role validation error for '.$path);
        }
        $this->assertSelections($workflow->fresh(), []);
    }

    public function test_all_seven_role_pickers_reject_candidate_type_role_keys(): void
    {
        $this->roles();
        $workflow = $this->workflow([]);
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $paths = [];
        foreach ($this->pickers($component) as $picker) {
            $component->set($picker->getStatePath(), ['national_exit_exam_application_master_of_science', 'retired_custom_candidate']);
            $paths[] = $picker->getStatePath();
        }
        $component->call('save')->assertHasErrors();
        $errorPaths = array_keys($component->errors()->toArray());
        foreach ($paths as $path) {
            $this->assertTrue(collect($errorPaths)->contains(fn (string $error): bool => $error === $path || str_starts_with($error, $path.'.')), 'Missing candidate-role validation error for '.$path);
        }
        $this->assertSelections($workflow->fresh(), []);
    }

    private function pickers($component)
    {
        return collect($component->instance()->form->getFlatFields())
            ->filter(fn ($field): bool => str_ends_with($field->getStatePath(), '.staff_recipient_roles'))
            ->values();
    }

    private function assertSelections(WorkflowNotification $workflow, array $roles): void
    {
        $this->assertEquals($roles, data_get($workflow->stages, '0.data.submission_messages.staff_recipient_roles'));
        foreach (['accept', 'send_back', 'reject'] as $action) {
            $this->assertEquals($roles, data_get($workflow->stages, '1.data.review_actions.'.$action.'.staff_recipient_roles'));
        }
        foreach ([2, 3, 4] as $index) {
            $this->assertEquals($roles, data_get($workflow->stages, $index.'.data.audience_messages.staff_recipient_roles'));
        }
    }

    private function workflow(array $roles): WorkflowNotification
    {
        $stages = [
            ['type' => 'stage', 'data' => [
                'stage_name' => 'Submit',
                'stage_type' => 'form_submission',
                'submission_messages' => ['staff_recipient_roles' => $roles],
            ]],
            ['type' => 'stage', 'data' => [
                'stage_name' => 'Review',
                'stage_type' => 'review',
                'responsible_role' => ['admin'],
                'review_actions' => array_fill_keys(['accept', 'send_back', 'reject'], ['staff_recipient_roles' => $roles]),
            ]],
        ];
        foreach (['payment', 'awaiting_results', 'completed'] as $type) {
            $stages[] = ['type' => 'stage', 'data' => [
                'stage_name' => $type,
                'stage_type' => $type,
                'responsible_role' => ['admin'],
                'audience_messages' => ['staff_recipient_roles' => $roles],
            ]];
        }

        return WorkflowNotification::query()->create(['name' => 'Localized workflow recipients', 'stages' => $stages]);
    }

    private function roles(): array
    {
        Role::findOrCreate('cashier_manager', 'web')->forceFill([
            'label_en' => 'Cashier Manager',
            'name_kh' => 'អ្នកគ្រប់គ្រងបេឡា',
        ])->save();
        Role::findOrCreate('registrar_officer', 'web')->forceFill([
            'label_en' => 'Registrar Officer',
            'name_kh' => 'មន្ត្រីការិយាល័យចុះឈ្មោះ',
        ])->save();
        Role::findOrCreate('custom_finance_team', 'web')->forceFill([
            'label_en' => 'Custom Finance Team',
            'name_kh' => 'ក្រុមហិរញ្ញវត្ថុផ្ទាល់ខ្លួន',
        ])->save();
        // UserType::saved creates these roles without role_type_key metadata.
        foreach (array_column(UserTypeOptions::defaultRecords(), 'key') as $key) {
            Role::findOrCreate($key, 'web');
        }
        Role::findOrCreate('student', 'web');
        Role::findOrCreate('retired_custom_candidate', 'web');
        DB::table('user_types')->insert([
            'key' => 'retired_custom_candidate',
            'label_en' => 'Retired candidate type',
            'label_kh' => 'ប្រភេទបេក្ខជនដែលឈប់ប្រើ',
            'is_active' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Role::findOrCreate('candidate', 'web')->forceFill(['role_type_key' => 'candidate'])->save();
        Role::findOrCreate('api_role', 'api');

        return [
            'en' => [
                'admin' => 'Administrator',
                'cashier_manager' => 'Cashier Manager',
                'registrar_officer' => 'Registrar Officer',
                'custom_finance_team' => 'Custom Finance Team',
            ],
            'km' => [
                'admin' => 'អ្នកគ្រប់គ្រងប្រព័ន្ធ',
                'cashier_manager' => 'អ្នកគ្រប់គ្រងបេឡា',
                'registrar_officer' => 'មន្ត្រីការិយាល័យចុះឈ្មោះ',
                'custom_finance_team' => 'ក្រុមហិរញ្ញវត្ថុផ្ទាល់ខ្លួន',
            ],
        ];
    }

    private function user(string $username, array $attributes = []): User
    {
        return User::query()->forceCreate(array_replace([
            'registration_type' => 'admin',
            'name' => $username,
            'username' => $username,
            'email' => $username.'@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => 'password',
            'is_active' => true,
        ], $attributes));
    }
}
