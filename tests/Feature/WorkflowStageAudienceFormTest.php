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

class WorkflowStageAudienceFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        $admin = $this->user('stage_audience_admin');
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        Role::findOrCreate('cashier_officer', 'web');
        $this->actingAs($admin);
    }

    public static function stageTypes(): array
    {
        return [['payment'], ['awaiting_results'], ['completed']];
    }

    public static function localizedStageTypes(): array
    {
        $cases = [];
        foreach (['en', 'km'] as $locale) {
            foreach (['payment', 'awaiting_results', 'completed'] as $type) {
                $cases[$locale.'-'.$type] = [$locale, $type];
            }
        }

        return $cases;
    }

    #[DataProvider('localizedStageTypes')]
    public function test_stage_has_localized_status_controls_and_separate_candidate_and_staff_notifications(string $locale, string $type): void
    {
        app()->setLocale($locale);
        $workflow = $this->workflow([$type]);
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $key = array_key_first($component->get('data.stages'));
        $path = "data.stages.{$key}.data";
        $fields = collect($component->instance()->form->getFlatFields())
            ->keyBy(fn ($field): string => $field->getStatePath());

        $status = $fields->get($path.'.status_message');
        $candidateNotification = $fields->get($path.'.notification_message');
        $staffNotification = $fields->get($path.'.audience_messages.staff_notification_message');
        $staffRecipients = $fields->get($path.'.audience_messages.staff_recipient_roles');
        $this->assertNotNull($status);
        $this->assertNotNull($candidateNotification);
        $this->assertNotNull($staffNotification);
        $this->assertNotNull($staffRecipients);
        $this->assertSame(__(match ($type) {
            'payment' => 'workflow_notifications.fields.paid_status_message',
            'awaiting_results' => 'workflow_notifications.fields.passed_result_status_message',
            default => 'workflow_notifications.fields.shared_status_message',
        }), $status->getLabel());
        $this->assertSame(__('workflow_notifications.fields.notification_message'), $candidateNotification->getLabel());
        $this->assertSame(__('workflow_notifications.fields.staff_notification_message'), $staffNotification->getLabel());
        $this->assertSame(__('workflow_notifications.fields.staff_recipient_roles'), $staffRecipients->getLabel());
        $this->assertEmpty($staffRecipients->getState());
        $pendingStatus = $fields->get($path.'.result_statuses.pending_status_message');
        $this->assertSame($type === 'awaiting_results', $pendingStatus !== null);
        if ($pendingStatus !== null) {
            $this->assertSame(__('workflow_notifications.fields.waiting_result_status_message'), $pendingStatus->getLabel());
            $this->assertSame(__('workflow_notifications.placeholders.waiting_result_status_message'), (string) $pendingStatus->getPlaceholder());
            $this->assertFalse($pendingStatus->isRequired());
            $this->assertEmpty($pendingStatus->getState());
            $component->assertSee(__('workflow_notifications.helpers.waiting_result_status_message'));
        }

        foreach (['status_message' => $status, 'notification_message' => $candidateNotification, 'staff_notification_message' => $staffNotification] as $name => $field) {
            $placeholder = (string) $field->getPlaceholder();
            $this->assertSame(__('workflow_notifications.placeholders.stage_audiences.'.$type.'.'.$name), $placeholder);
            $this->assertStringNotContainsString(':form', $placeholder);
            $this->assertStringNotContainsString(':candidate', $placeholder);
        }

        $component->assertSee(__('workflow_notifications.sections.for_candidates'))
            ->assertSee(__('workflow_notifications.sections.for_system_users'));
        if ($type === 'awaiting_results') {
            $component->assertSee(__('workflow_notifications.helpers.passed_result_status_message'));
        }
        $this->assertTrue($fields->has($path.'.responsible_role'));
        $this->assertFalse($fields->has($path.'.responsible_role_automatic'));
        $this->assertTrue($fields->get($path.'.responsible_role')->isMultiple());
        $this->assertSame($type !== 'completed', $fields->get($path.'.responsible_role')->isRequired());
    }

    #[DataProvider('stageTypes')]
    public function test_existing_stage_settings_hydrate_and_new_staff_messages_save_independently(string $type): void
    {
        $workflow = $this->workflow([$type]);
        $original = $workflow->stages[0]['data'];
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $key = array_key_first($component->get('data.stages'));
        $path = "data.stages.{$key}.data";

        $component->assertSet($path.'.status_message', $original['status_message'])
            ->assertSet($path.'.notification_message', $original['notification_message'])
            ->set($path.'.audience_messages.staff_notification_message', 'Staff alert for '.$type)
            ->set($path.'.audience_messages.staff_recipient_roles', ['cashier_officer'])
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = $workflow->fresh()->stages[0]['data'];
        foreach (['stage_name', 'stage_type', 'status_message', 'notification_message', 'responsible_role', 'notification_channels'] as $field) {
            $this->assertEquals($original[$field], $saved[$field] ?? null);
        }
        $this->assertSame('Staff alert for '.$type, data_get($saved, 'audience_messages.staff_notification_message'));
        $this->assertEquals(['cashier_officer'], data_get($saved, 'audience_messages.staff_recipient_roles'));

        $reopened = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $reopenedKey = array_key_first($reopened->get('data.stages'));
        $reopenedPath = "data.stages.{$reopenedKey}.data";
        $reopened->assertSet($reopenedPath.'.status_message', $original['status_message'])
            ->assertSet($reopenedPath.'.notification_message', $original['notification_message'])
            ->assertSet($reopenedPath.'.audience_messages.staff_notification_message', 'Staff alert for '.$type)
            ->assertSet($reopenedPath.'.audience_messages.staff_recipient_roles', ['cashier_officer']);
    }

    public function test_new_staff_settings_are_optional_on_all_three_stage_types(): void
    {
        $stages = [];
        foreach (['payment', 'awaiting_results', 'completed'] as $type) {
            $stages[$type] = ['type' => 'stage', 'data' => $this->stageData($type)];
        }

        Livewire::test(CreateWorkflowNotification::class)
            ->set('data.stages', [])
            ->fillForm(['name' => 'Optional staff audiences', 'stages' => $stages])
            ->call('create')
            ->assertHasNoFormErrors();

        $workflow = WorkflowNotification::query()->sole();
        foreach ($workflow->stages as $stage) {
            $data = $stage['data'];
            $this->assertEmpty(data_get($data, 'audience_messages.staff_notification_message'));
            $this->assertEmpty(data_get($data, 'audience_messages.staff_recipient_roles'));
            $this->assertSame('Existing status for '.$data['stage_type'], $data['status_message']);
            $this->assertSame('Existing candidate message for '.$data['stage_type'], $data['notification_message']);
        }
    }

    public function test_waiting_and_passed_exam_result_labels_save_independently_without_changing_audience_settings(): void
    {
        $workflow = $this->workflow(['awaiting_results']);
        $stages = $workflow->stages;
        $stages[0]['data']['status_message'] = 'បានជាប់';
        $stages[0]['data']['result_statuses']['pending_status_message'] = 'កំពុងរង់ចាំ';
        $stages[0]['data']['audience_messages'] = [
            'staff_notification_message' => 'A candidate is waiting for exam results.',
            'staff_recipient_roles' => ['cashier_officer'],
        ];
        $workflow->update(['stages' => $stages]);
        $original = $workflow->stages[0]['data'];
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $key = array_key_first($component->get('data.stages'));
        $path = "data.stages.{$key}.data";
        $component->assertSet($path.'.status_message', 'បានជាប់')
            ->assertSet($path.'.result_statuses.pending_status_message', 'កំពុងរង់ចាំ')
            ->set($path.'.result_statuses.pending_status_message', 'រង់ចាំលទ្ធផលប្រឡង')
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = $workflow->fresh()->stages[0]['data'];
        $this->assertSame('បានជាប់', $saved['status_message']);
        $this->assertSame('រង់ចាំលទ្ធផលប្រឡង', data_get($saved, 'result_statuses.pending_status_message'));
        foreach ($original as $field => $value) {
            if ($field !== 'result_statuses') {
                $this->assertEquals($value, $saved[$field] ?? null);
            }
        }

        $component->set($path.'.status_message', 'ប្រឡងជាប់')
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('ប្រឡងជាប់', data_get($workflow->fresh()->stages, '0.data.status_message'));
        $this->assertSame('រង់ចាំលទ្ធផលប្រឡង', data_get($workflow->fresh()->stages, '0.data.result_statuses.pending_status_message'));

        foreach (['en', 'km'] as $locale) {
            app()->setLocale($locale);
            $reopened = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
            $reopenedKey = array_key_first($reopened->get('data.stages'));
            $reopenedPath = "data.stages.{$reopenedKey}.data";
            $reopened->assertSet($reopenedPath.'.status_message', 'ប្រឡងជាប់')
                ->assertSet($reopenedPath.'.result_statuses.pending_status_message', 'រង់ចាំលទ្ធផលប្រឡង')
                ->assertSet($reopenedPath.'.notification_message', $original['notification_message'])
                ->assertSet($reopenedPath.'.audience_messages.staff_notification_message', $original['audience_messages']['staff_notification_message'])
                ->assertSet($reopenedPath.'.audience_messages.staff_recipient_roles', ['cashier_officer']);
            $statusFields = collect($reopened->instance()->form->getFlatFields())
                ->filter(fn ($field): bool => str_ends_with($field->getStatePath(), 'status_message'));
            $this->assertCount(2, $statusFields);
            $this->assertEqualsCanonicalizing([
                $reopenedPath.'.status_message',
                $reopenedPath.'.result_statuses.pending_status_message',
            ], $statusFields->map(fn ($field): string => $field->getStatePath())->values()->all());
            $passedField = $statusFields->first(fn ($field): bool => $field->getStatePath() === $reopenedPath.'.status_message');
            $this->assertSame(__('workflow_notifications.fields.passed_result_status_message'), $passedField->getLabel());
        }
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_waiting_result_status_field_is_visible_only_for_awaiting_results(): void
    {
        $workflow = $this->workflow(['form_submission', 'review', 'payment', 'awaiting_results', 'completed']);
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $fields = collect($component->instance()->form->getFlatFields())
            ->keyBy(fn ($field): string => $field->getStatePath());
        $pendingFields = $fields->filter(fn ($field): bool => str_ends_with($field->getStatePath(), '.result_statuses.pending_status_message'));
        $this->assertCount(1, $pendingFields);
        foreach ($component->get('data.stages') as $key => $stage) {
            $this->assertSame($stage['data']['stage_type'] === 'awaiting_results', $fields->has("data.stages.{$key}.data.result_statuses.pending_status_message"));
        }
    }

    public function test_pending_result_label_is_optional_for_old_workflows_and_accepts_at_most_255_characters(): void
    {
        $workflow = $this->workflow(['awaiting_results']);
        $original = $workflow->stages[0]['data'];
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $key = array_key_first($component->get('data.stages'));
        $path = "stages.{$key}.data.result_statuses.pending_status_message";
        $component->call('save')->assertHasNoFormErrors();
        $this->assertEmpty(data_get($workflow->fresh()->stages, '0.data.result_statuses.pending_status_message'));

        $component->set('data.'.$path, str_repeat('a', 256))
            ->call('save')
            ->assertHasFormErrors([$path => 'max']);
        $this->assertEmpty(data_get($workflow->fresh()->stages, '0.data.result_statuses.pending_status_message'));
        $component->set('data.'.$path, str_repeat('a', 255))
            ->call('save')
            ->assertHasNoFormErrors();
        $saved = $workflow->fresh()->stages[0]['data'];
        $this->assertSame(str_repeat('a', 255), data_get($saved, 'result_statuses.pending_status_message'));
        foreach ($original as $field => $value) {
            $this->assertEquals($value, $saved[$field] ?? null);
        }
    }

    public function test_staff_picker_on_each_stage_lists_system_roles_instead_of_accounts(): void
    {
        $staff = $this->user('personal_audience_staff');
        $staff->assignRole('cashier_officer');
        Role::findOrCreate('candidate', 'web')->forceFill(['role_type_key' => 'candidate'])->save();
        $workflow = $this->workflow(['payment', 'awaiting_results', 'completed']);
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $pickers = collect($component->instance()->form->getFlatFields())
            ->filter(fn ($field): bool => str_ends_with($field->getStatePath(), '.audience_messages.staff_recipient_roles'));
        $this->assertCount(3, $pickers);

        foreach ($pickers as $picker) {
            $this->assertTrue($picker->isMultiple());
            $this->assertEmpty($picker->getState());
            $options = $picker->getOptions();
            $this->assertArrayHasKey('admin', $options);
            $this->assertArrayHasKey('cashier_officer', $options);
            $this->assertArrayNotHasKey('candidate', $options);
            $this->assertArrayNotHasKey($staff->getKey(), $options);
            $this->assertNotContains($staff->name, $options);
        }
    }

    private function workflow(array $types): WorkflowNotification
    {
        return WorkflowNotification::query()->create([
            'name' => 'Stage audiences',
            'stages' => array_map(fn (string $type): array => ['type' => 'stage', 'data' => $this->stageData($type)], $types),
        ]);
    }

    private function stageData(string $type): array
    {
        return [
            'stage_name' => ucfirst(str_replace('_', ' ', $type)),
            'stage_type' => $type,
            'status_message' => 'Existing status for '.$type,
            'notification_message' => 'Existing candidate message for '.$type,
            'responsible_role' => in_array($type, ['form_submission', 'completed'], true) ? null : ['cashier_officer'],
            'notification_channels' => ['system'],
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
