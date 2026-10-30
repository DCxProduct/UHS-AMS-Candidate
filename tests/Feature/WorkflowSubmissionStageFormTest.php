<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\WorkflowNotifications\Pages\CreateWorkflowNotification;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\EditWorkflowNotification;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkflowSubmissionStageFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        $admin = $this->user('submission_form_admin');
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        Role::findOrCreate('cashier_officer', 'web');
        $this->actingAs($admin);
    }

    public function test_submission_messages_can_be_created_without_a_responsible_role_or_staff_status(): void
    {
        $messages = $this->messages();

        $component = $this->createForm('Submission configuration', $messages)
            ->call('create')
            ->assertHasNoFormErrors();

        $workflow = WorkflowNotification::query()->sole();
        $stage = data_get($workflow->stages, '0.data');
        $this->assertEquals($messages, $stage['submission_messages']);
        $this->assertEmpty($stage['responsible_role'] ?? []);
        $this->assertArrayNotHasKey('staff_status_message', $stage['submission_messages']);
        $component->assertRedirect();
    }

    public function test_existing_submission_configuration_hydrates_and_edits_all_four_fields(): void
    {
        $messages = $this->messages();
        $workflow = WorkflowNotification::query()->create([
            'name' => 'Editable submission',
            'stages' => [$this->stage($messages)],
        ]);

        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $key = array_key_first($component->get('data.stages'));
        $path = "data.stages.{$key}.data.submission_messages";
        foreach ($messages as $field => $value) {
            $component->assertSet($path.'.'.$field, $value);
        }

        $component->set($path.'.candidate_status_message', 'Submitted and awaiting review')
            ->set($path.'.staff_notification_message', 'An application is ready for review.')
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = data_get($workflow->fresh()->stages, '0.data.submission_messages');
        $this->assertSame('Submitted and awaiting review', $saved['candidate_status_message']);
        $this->assertSame('An application is ready for review.', $saved['staff_notification_message']);
        $this->assertSame($messages['candidate_notification_message'], $saved['candidate_notification_message']);
        $this->assertEquals($messages['staff_recipient_roles'], $saved['staff_recipient_roles']);
    }

    public function test_new_submission_settings_are_optional_and_do_not_change_other_stage_configuration(): void
    {
        $payment = [
            'stage_name' => 'Payment',
            'stage_type' => 'payment',
            'responsible_role' => ['cashier_officer'],
            'status_message' => 'Waiting for payment',
            'notification_message' => 'Please complete payment.',
            'notification_channels' => ['system'],
        ];

        Livewire::test(CreateWorkflowNotification::class)
            ->set('data.stages', [])
            ->fillForm([
                'name' => 'Legacy-compatible submission',
                'stages' => [
                    'submit' => $this->stage(),
                    'payment' => ['type' => 'stage', 'data' => $payment],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $workflow = WorkflowNotification::query()->sole();
        $this->assertEmpty(data_get($workflow->stages, '0.data.responsible_role'));
        $this->assertEmpty(array_filter((array) data_get($workflow->stages, '0.data.submission_messages', [])));
        foreach ($payment as $field => $value) {
            $this->assertEquals($value, data_get($workflow->stages, '1.data.'.$field));
        }
    }

    public static function locales(): array
    {
        return [['en'], ['km']];
    }

    #[DataProvider('locales')]
    public function test_submission_stage_shows_candidate_and_system_user_groups_without_a_staff_status_field(string $locale): void
    {
        app()->setLocale($locale);
        $component = $this->createForm('Localized submission');
        $fields = $component->instance()->form->getFlatFields();
        $visibleSubmissionFields = collect($fields)
            ->map(fn ($field): string => $field->getStatePath())
            ->filter(fn (string $path): bool => str_contains($path, '.submission_messages.'))
            ->map(fn (string $path): string => substr($path, strrpos($path, '.') + 1))
            ->values()->all();

        $this->assertEqualsCanonicalizing([
            'candidate_status_message',
            'candidate_notification_message',
            'staff_notification_message',
            'staff_recipient_roles',
        ], $visibleSubmissionFields);
        $this->assertFalse(collect($fields)->contains(fn ($field): bool => str_ends_with($field->getStatePath(), '.responsible_role')));
        $component->assertSee($locale === 'km' ? 'សម្រាប់បេក្ខជន' : 'For Candidates')
            ->assertSee($locale === 'km' ? 'សម្រាប់អ្នកប្រើប្រាស់ប្រព័ន្ធ' : 'For System Users');

        $html = $component->html();
        $previousPosition = -1;
        foreach ([
            __('workflow_notifications.fields.stage_name'),
            __('workflow_notifications.fields.stage_type'),
            __('workflow_notifications.fields.shared_status_message'),
            __('workflow_notifications.sections.for_candidates'),
            __('workflow_notifications.sections.for_system_users'),
        ] as $label) {
            $position = strpos($html, $label, $previousPosition + 1);
            $this->assertNotFalse($position, 'Stage details and shared status must precede audience groups: '.$label);
            $this->assertGreaterThan($previousPosition, $position);
            $previousPosition = $position;
        }

        $statusFields = collect($fields)->filter(fn ($field): bool => str_ends_with($field->getStatePath(), '.candidate_status_message'));
        $this->assertCount(1, $statusFields);
        $this->assertSame(__('workflow_notifications.fields.shared_status_message'), $statusFields->first()->getLabel());

        foreach ($fields as $field) {
            if (str_contains($field->getStatePath(), '.submission_messages.') && method_exists($field, 'getPlaceholder')) {
                $this->assertStringNotContainsString(':form', (string) $field->getPlaceholder());
                $this->assertStringNotContainsString(':candidate', (string) $field->getPlaceholder());
            }
        }
    }

    public function test_staff_recipient_picker_lists_roles_instead_of_personal_accounts(): void
    {
        $staff = $this->user('personal_submission_staff');
        $staff->assignRole('cashier_officer');
        Role::findOrCreate('candidate', 'web')->forceFill(['role_type_key' => 'candidate'])->save();
        Role::findOrCreate('api_role', 'api');
        $component = $this->createForm('Recipient roles');
        $picker = collect($component->instance()->form->getFlatFields())
            ->first(fn ($field): bool => str_ends_with($field->getStatePath(), '.submission_messages.staff_recipient_roles'));
        $this->assertNotNull($picker);
        $this->assertTrue($picker->isMultiple());
        $options = $picker->getOptions();
        $this->assertArrayHasKey('admin', $options);
        $this->assertArrayHasKey('cashier_officer', $options);
        $this->assertArrayNotHasKey('candidate', $options);
        $this->assertArrayNotHasKey('api_role', $options);
        $this->assertArrayNotHasKey($staff->getKey(), $options);
        $this->assertNotContains($staff->name, $options);
    }

    #[DataProvider('locales')]
    public function test_submission_notification_channels_are_visible_and_saved(string $locale): void
    {
        app()->setLocale($locale);
        $workflow = WorkflowNotification::query()->create([
            'name' => 'Submission channels',
            'stages' => [$this->stage()],
        ]);
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $key = array_key_first($component->get('data.stages'));
        $path = "data.stages.{$key}.data";
        $channels = collect($component->instance()->form->getFlatFields())
            ->first(fn ($field): bool => str_ends_with($field->getStatePath(), '.notification_channels'));

        $this->assertNotNull($channels);
        $this->assertSame(['system', 'sms', 'email'], array_keys($channels->getOptions()));
        $component->assertSee(__('workflow_notifications.fields.notification_channels'))
            ->assertSet($path.'.notification_channels', ['system'])
            ->set($path.'.notification_channels', ['system', 'sms', 'email']);

        $fields = collect($component->instance()->form->getFlatFields());
        foreach (['email_template_key', 'sms_template_key'] as $field) {
            $this->assertTrue($fields->contains(fn ($component): bool => str_ends_with($component->getStatePath(), '.'.$field)));
        }

        $component->call('save')->assertHasNoFormErrors();
        $this->assertSame(['system', 'sms', 'email'], data_get($workflow->fresh()->stages, '0.data.notification_channels'));
    }

    private function createForm(string $name, ?array $messages = null)
    {
        return Livewire::test(CreateWorkflowNotification::class)
            ->set('data.stages', [])
            ->fillForm(['name' => $name, 'stages' => ['submit' => $this->stage($messages)]]);
    }

    private function stage(?array $messages = null): array
    {
        $data = ['stage_name' => 'Submit application', 'stage_type' => 'form_submission'];
        if ($messages !== null) {
            $data['submission_messages'] = $messages;
        }

        return ['type' => 'stage', 'data' => $data];
    }

    private function messages(): array
    {
        return [
            'candidate_status_message' => 'Application submitted',
            'candidate_notification_message' => 'Your application was submitted successfully.',
            'staff_notification_message' => 'A new application needs review.',
            'staff_recipient_roles' => ['cashier_officer'],
        ];
    }

    private function user(string $username, string $type = 'admin', bool $active = true): User
    {
        return User::query()->forceCreate([
            'registration_type' => $type,
            'name' => $username,
            'username' => $username,
            'email' => $username.'@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => 'password',
            'is_active' => $active,
        ]);
    }
}
