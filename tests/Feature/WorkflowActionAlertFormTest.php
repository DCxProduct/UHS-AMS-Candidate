<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\WorkflowNotifications\Pages\EditWorkflowNotification;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkflowActionAlertFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        $admin = User::query()->forceCreate([
            'name' => 'Action alert administrator', 'username' => 'action_alert_admin',
            'email' => 'action_alert_admin@example.test', 'password' => 'password',
            'registration_type' => 'admin', 'is_active' => true, 'date_of_birth' => '2000-01-01',
        ]);
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
    }

    public static function locales(): array
    {
        return [['en'], ['km']];
    }

    #[DataProvider('locales')]
    public function test_builder_exposes_independent_optional_localized_alert_fields_at_each_correct_stage_path(string $locale): void
    {
        app()->setLocale($locale);
        $workflow = $this->workflow();
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $fields = collect($component->instance()->form->getFlatFields())
            ->keyBy(fn ($field): string => $field->getStatePath());
        $alertPaths = [];

        foreach ($component->get('data.stages') as $key => $stage) {
            $prefix = "data.stages.{$key}.data";
            $type = $stage['data']['stage_type'];
            $paths = $type === 'review'
                ? ['review_actions.accept.alert_message', 'review_actions.send_back.alert_message', 'review_actions.reject.alert_message']
                : ['alert_message'];
            if ($type === 'awaiting_results') {
                $paths[] = 'result_statuses.pending_alert_message';
            }
            foreach ($paths as $path) {
                $field = $fields->get($prefix.'.'.$path);
                $this->assertNotNull($field, $type.' '.$path);
                $this->assertFalse($field->isRequired());
                $this->assertSame(255, $field->getMaxLength());
                $translation = str_ends_with($path, 'pending_alert_message') ? 'pending_alert_message' : 'alert_message';
                $this->assertSame(__('workflow_notifications.fields.'.$translation), $field->getLabel());
                $this->assertSame(__('workflow_notifications.placeholders.'.$translation), (string) $field->getPlaceholder());
                $this->assertNotSame('workflow_notifications.fields.'.$translation, $field->getLabel());
                $alertPaths[] = $prefix.'.'.$path;
            }
            $this->assertSame($type !== 'review', $fields->has($prefix.'.alert_message'));
            if ($type !== 'awaiting_results') {
                $this->assertFalse($fields->has($prefix.'.result_statuses.pending_alert_message'));
            }
        }

        $this->assertCount(8, $alertPaths);
        $this->assertCount(8, $fields->filter(fn ($field): bool => str_ends_with($field->getStatePath(), 'alert_message')));
    }

    public function test_editing_and_reopening_alerts_preserves_the_separate_status_notification_and_stage_configuration(): void
    {
        app()->setLocale('en');
        $workflow = $this->workflow();
        $original = $workflow->stages;
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);

        foreach ($component->get('data.stages') as $key => $stage) {
            $prefix = "data.stages.{$key}.data";
            if ($stage['data']['stage_type'] === 'review') {
                foreach (['accept', 'send_back', 'reject'] as $action) {
                    $component->set($prefix.'.review_actions.'.$action.'.alert_message', 'Edited '.$action.' action alert');
                }
            } else {
                $component->set($prefix.'.alert_message', 'Edited '.$stage['data']['stage_type'].' action alert');
            }
            if ($stage['data']['stage_type'] === 'awaiting_results') {
                $component->set($prefix.'.result_statuses.pending_alert_message', 'Edited pending action alert');
            }
        }
        $component->call('save')->assertHasNoFormErrors();
        $saved = $workflow->fresh()->stages;
        $reopened = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);

        foreach (array_values($reopened->get('data.stages')) as $index => $stage) {
            $type = $stage['data']['stage_type'];
            $this->assertSame($original[$index]['data']['stage_type'], $type);
            $this->assertSame($original[$index]['data']['stage_name'], $saved[$index]['data']['stage_name']);
            $this->assertSame(['system'], $saved[$index]['data']['notification_channels']);
            if ($type === 'review') {
                foreach (['accept', 'send_back', 'reject'] as $action) {
                    $this->assertSame('Edited '.$action.' action alert', data_get($stage, 'data.review_actions.'.$action.'.alert_message'));
                    $this->assertSame(data_get($original[$index], 'data.review_actions.'.$action.'.status_message'), data_get($saved[$index], 'data.review_actions.'.$action.'.status_message'));
                    $this->assertSame(data_get($original[$index], 'data.review_actions.'.$action.'.notification_message'), data_get($saved[$index], 'data.review_actions.'.$action.'.notification_message'));
                }
            } else {
                $this->assertSame('Edited '.$type.' action alert', data_get($stage, 'data.alert_message'));
                $statusPath = $type === 'form_submission' ? 'submission_messages.candidate_status_message' : 'status_message';
                $messagePath = $type === 'form_submission' ? 'submission_messages.candidate_notification_message' : 'notification_message';
                $this->assertSame(data_get($original[$index]['data'], $statusPath), data_get($saved[$index]['data'], $statusPath));
                $this->assertSame(data_get($original[$index]['data'], $messagePath), data_get($saved[$index]['data'], $messagePath));
            }
            if ($type === 'payment') {
                $this->assertSame('system', $saved[$index]['data']['payment_source']);
                $this->assertSame('Unpaid status', data_get($saved[$index], 'data.payment_statuses.unpaid_status_message'));
            }
            if ($type === 'awaiting_results') {
                $this->assertSame('Edited pending action alert', data_get($stage, 'data.result_statuses.pending_alert_message'));
                $this->assertSame('Waiting status', data_get($saved[$index], 'data.result_statuses.pending_status_message'));
            }
        }
    }

    private function workflow(): WorkflowNotification
    {
        return WorkflowNotification::query()->create([
            'name' => 'Independent action alerts',
            'stages' => array_map(static function (string $type): array {
                $data = [
                    'stage_type' => $type, 'stage_name' => 'Configured stage '.$type,
                    'notification_channels' => ['system'], 'responsible_role' => ['admin'],
                ];
                if ($type === 'review') {
                    foreach (['accept', 'send_back', 'reject'] as $action) {
                        $data['review_actions'][$action] = [
                            'status_message' => ucfirst($action).' status', 'alert_message' => ucfirst($action).' action alert',
                            'notification_message' => ucfirst($action).' candidate notification',
                        ];
                    }
                } else {
                    $data['alert_message'] = ucfirst($type).' action alert';
                    if ($type === 'form_submission') {
                        $data['submission_messages'] = ['candidate_status_message' => 'Submitted status', 'candidate_notification_message' => 'Submission candidate notification'];
                    } else {
                        $data['status_message'] = ucfirst($type).' status';
                        $data['notification_message'] = ucfirst($type).' candidate notification';
                    }
                }
                if ($type === 'payment') {
                    $data['payment_source'] = 'system';
                    $data['payment_statuses']['unpaid_status_message'] = 'Unpaid status';
                }
                if ($type === 'awaiting_results') {
                    $data['result_statuses'] = ['pending_status_message' => 'Waiting status', 'pending_alert_message' => 'Waiting action alert'];
                }

                return ['type' => 'stage', 'data' => $data];
            }, ['form_submission', 'review', 'payment', 'awaiting_results', 'completed']),
        ]);
    }
}
