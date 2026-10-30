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

class WorkflowReviewStageFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        $admin = User::query()->forceCreate([
            'registration_type' => 'admin',
            'name' => 'Review administrator',
            'username' => 'review_form_admin',
            'email' => 'review_form_admin@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => 'password',
            'is_active' => true,
        ]);
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        Role::findOrCreate('registrar_officer', 'web');
        Role::findOrCreate('cashier_officer', 'web');
        $this->actingAs($admin);
    }

    public static function locales(): array
    {
        return [['en'], ['km']];
    }

    #[DataProvider('locales')]
    public function test_review_actions_clearly_distinguish_shared_status_and_each_notification_audience(string $locale): void
    {
        app()->setLocale($locale);
        $workflow = $this->workflow();
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $fields = collect($component->instance()->form->getFlatFields());
        $actionFields = $fields->filter(fn ($field): bool => str_contains($field->getStatePath(), '.review_actions.'));
        $this->assertCount(12, $actionFields);
        $component->assertSee(__('workflow_notifications.helpers.shared_review_status'))
            ->assertSee(__('workflow_notifications.helpers.review_candidate_notification'))
            ->assertSee(__('workflow_notifications.helpers.review_staff_notification'))
            ->assertSee(__('workflow_notifications.helpers.review_staff_recipients'))
            ->assertSee(__('workflow_notifications.sections.for_candidates'))
            ->assertSee(__('workflow_notifications.sections.for_system_users'));

        foreach (['accept', 'send_back', 'reject'] as $action) {
            $component->assertSee(__('workflow_notifications.review_actions.'.$action.'.title'))
                ->assertSee(__('workflow_notifications.review_actions.'.$action.'.description'));

            $status = $actionFields->first(fn ($field): bool => str_ends_with($field->getStatePath(), '.review_actions.'.$action.'.status_message'));
            $notification = $actionFields->first(fn ($field): bool => str_ends_with($field->getStatePath(), '.review_actions.'.$action.'.notification_message'));
            $staffNotification = $actionFields->first(fn ($field): bool => str_ends_with($field->getStatePath(), '.review_actions.'.$action.'.staff_notification_message'));
            $staffRecipients = $actionFields->first(fn ($field): bool => str_ends_with($field->getStatePath(), '.review_actions.'.$action.'.staff_recipient_roles'));

            $this->assertNotNull($status);
            $this->assertNotNull($notification);
            $this->assertNotNull($staffNotification);
            $this->assertNotNull($staffRecipients);
            $this->assertSame(__('workflow_notifications.fields.shared_status_message'), $status->getLabel());
            $this->assertSame(__('workflow_notifications.fields.notification_message'), $notification->getLabel());
            $this->assertSame(__('workflow_notifications.fields.staff_notification_message'), $staffNotification->getLabel());
            $this->assertSame(__('workflow_notifications.fields.staff_recipient_roles'), $staffRecipients->getLabel());
            $this->assertEmpty($staffRecipients->getState());

            foreach (['status_message' => $status, 'notification_message' => $notification, 'staff_notification_message' => $staffNotification] as $name => $field) {
                $placeholder = (string) $field->getPlaceholder();
                $this->assertSame(__('workflow_notifications.placeholders.review_actions.'.$action.'.'.$name), $placeholder);
                $this->assertStringNotContainsString(':form', $placeholder);
                $this->assertStringNotContainsString(':candidate', $placeholder);
            }
        }

        $this->assertCount(3, $actionFields->filter(fn ($field): bool => str_ends_with($field->getStatePath(), '.status_message')));
    }

    public function test_saved_review_action_status_and_notification_edit_independently_and_preserve_other_stages(): void
    {
        $workflow = $this->workflow();
        $original = $workflow->stages;
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $key = array_key_first($component->get('data.stages'));
        $path = "data.stages.{$key}.data";

        foreach ($original[0]['data']['review_actions'] as $action => $messages) {
            foreach ($messages as $field => $value) {
                $component->assertSet($path.'.review_actions.'.$action.'.'.$field, $value);
            }
        }

        $component->set($path.'.review_actions.accept.status_message', 'Checked by staff')
            ->set($path.'.review_actions.send_back.notification_message', 'Please correct the attached document.')
            ->set($path.'.review_actions.accept.staff_notification_message', 'An application was checked by the reviewer.')
            ->set($path.'.review_actions.accept.staff_recipient_roles', ['registrar_officer'])
            ->call('save')
            ->assertHasNoFormErrors();

        $saved = $workflow->fresh()->stages;
        $expected = $original[0]['data']['review_actions'];
        $expected['accept']['status_message'] = 'Checked by staff';
        $expected['send_back']['notification_message'] = 'Please correct the attached document.';
        foreach ($expected as $action => $messages) {
            foreach ($messages as $field => $value) {
                $this->assertSame($value, $saved[0]['data']['review_actions'][$action][$field]);
            }
        }
        $this->assertSame('An application was checked by the reviewer.', data_get($saved, '0.data.review_actions.accept.staff_notification_message'));
        $this->assertEquals(['registrar_officer'], data_get($saved, '0.data.review_actions.accept.staff_recipient_roles'));
        foreach (['send_back', 'reject'] as $action) {
            $this->assertEmpty(data_get($saved, '0.data.review_actions.'.$action.'.staff_notification_message'));
            $this->assertEmpty(data_get($saved, '0.data.review_actions.'.$action.'.staff_recipient_roles'));
        }
        $this->assertSame($original[0]['data']['responsible_role'], $saved[0]['data']['responsible_role']);
        $this->assertSame($original[0]['data']['stage_type'], $saved[0]['data']['stage_type']);
        $payment = $saved[1]['data'];
        foreach (['audience_messages', 'payment_statuses'] as $namespace) {
            $this->assertEmpty(array_filter((array) ($payment[$namespace] ?? [])));
            unset($payment[$namespace]);
        }
        $this->assertEquals($original[1]['data'], $payment);
    }

    public function test_review_messages_remain_optional(): void
    {
        Livewire::test(CreateWorkflowNotification::class)
            ->set('data.stages', [])
            ->fillForm([
                'name' => 'Review without custom messages',
                'stages' => ['review' => ['type' => 'stage', 'data' => [
                    'stage_name' => 'Review',
                    'stage_type' => 'review',
                    'responsible_role' => ['registrar_officer'],
                ]]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $workflow = WorkflowNotification::query()->sole();
        $this->assertSame(['registrar_officer'], data_get($workflow->stages, '0.data.responsible_role'));
        foreach (['accept', 'send_back', 'reject'] as $action) {
            $this->assertEmpty(array_filter((array) data_get($workflow->stages, '0.data.review_actions.'.$action, [])));
        }
    }

    public function test_each_review_action_selects_system_roles_instead_of_accounts(): void
    {
        $staff = $this->user('personal_review_staff');
        $staff->assignRole('registrar_officer');
        Role::findOrCreate('candidate', 'web')->forceFill(['role_type_key' => 'candidate'])->save();
        $workflow = $this->workflow();
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $fields = collect($component->instance()->form->getFlatFields());

        foreach (['accept', 'send_back', 'reject'] as $action) {
            $picker = $fields->first(fn ($field): bool => str_ends_with($field->getStatePath(), '.review_actions.'.$action.'.staff_recipient_roles'));
            $this->assertNotNull($picker);
            $this->assertTrue($picker->isMultiple());
            $this->assertEmpty($picker->getState());
            $options = $picker->getOptions();
            $this->assertArrayHasKey('admin', $options);
            $this->assertArrayHasKey('registrar_officer', $options);
            $this->assertArrayNotHasKey('candidate', $options);
            $this->assertArrayNotHasKey($staff->getKey(), $options);
            $this->assertNotContains($staff->name, $options);
        }
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

    private function workflow(): WorkflowNotification
    {
        return WorkflowNotification::query()->create([
            'name' => 'Clear review configuration',
            'stages' => [
                ['type' => 'stage', 'data' => [
                    'stage_name' => 'Review',
                    'stage_type' => 'review',
                    'responsible_role' => ['registrar_officer'],
                    'notification_channels' => ['system'],
                    'review_actions' => [
                        'accept' => ['status_message' => 'Checked', 'notification_message' => 'Your application was checked.'],
                        'send_back' => ['status_message' => 'Returned for correction', 'notification_message' => 'Please update your application.'],
                        'reject' => ['status_message' => 'Rejected', 'notification_message' => 'Your application was rejected.'],
                    ],
                ]],
                ['type' => 'stage', 'data' => [
                    'stage_name' => 'Payment',
                    'stage_type' => 'payment',
                    'payment_source' => 'system',
                    'responsible_role' => ['cashier_officer'],
                    'notification_channels' => ['system'],
                    'status_message' => 'Waiting for payment',
                    'notification_message' => 'Please complete your payment.',
                ]],
            ],
        ]);
    }
}
