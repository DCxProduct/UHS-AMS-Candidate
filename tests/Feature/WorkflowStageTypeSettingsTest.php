<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\WorkflowNotifications\Pages\CreateWorkflowNotification;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\EditWorkflowNotification;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Models\WorkflowStageType;
use App\Support\WorkflowStageTypeSettings;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkflowStageTypeSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::preventStrayRequests();
        Http::fake([]);
        Mail::fake();
        Notification::fake();
        $admin = User::query()->create(['registration_type' => 'admin', 'name' => 'Settings admin', 'username' => 'settings-admin', 'email' => 'settings-admin@example.test', 'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true]);
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        Role::findOrCreate('registrar_officer', 'web')->givePermissionTo(Permission::findOrCreate('Accepted:CustomFormEntry', 'web'));
        Role::findOrCreate('candidate', 'web')->forceFill(['role_type_key' => 'candidate'])->save();
        $this->actingAs($admin);
    }

    public function test_configuration_actions_map_only_to_existing_engine_fields_for_each_behavior(): void
    {
        $expected = [
            'form_submission' => ['submitted'],
            'review' => ['accept', 'send_back', 'reject'],
            'payment' => ['unpaid', 'paid'],
            'awaiting_results' => ['pending', 'passed'],
            'completed' => ['notify'],
        ];
        foreach ($expected as $behavior => $actions) {
            $this->assertSame($actions, array_keys(WorkflowStageTypeSettings::actions($behavior)));
            foreach ($actions as $action) {
                $this->assertNotEmpty(WorkflowStageTypeSettings::actions($behavior)[$action]['label']);
                $this->assertNotEmpty(WorkflowStageTypeSettings::fields($behavior, $action));
            }
        }
        $this->assertSame(['payment_statuses.unpaid_status_message'], array_column(WorkflowStageTypeSettings::fields('payment', 'unpaid'), 'path'));
        $this->assertSame(['result_statuses.pending_status_message', 'result_statuses.pending_alert_message'], array_column(WorkflowStageTypeSettings::fields('awaiting_results', 'pending'), 'path'));
        $this->assertSame('review_actions.reject.status_message', WorkflowStageTypeSettings::fields('review', 'reject')[0]['path']);
        $this->assertSame([], WorkflowStageTypeSettings::actions('unknown'));
        $this->assertSame([], WorkflowStageTypeSettings::fields('payment', 'reject'));
        $this->assertSame([
            'status_message' => null,
            'audience_messages' => ['staff_recipient_roles' => []],
        ], WorkflowStageTypeSettings::onlyAllowed('payment', [
            'status_message' => null,
            'audience_messages' => ['staff_recipient_roles' => [], 'staff_recipient_ids' => [123]],
            'stage_type' => 'completed',
            'responsible_role' => ['admin'],
        ]));
    }

    public function test_new_workflows_copy_valid_catalog_defaults_for_all_five_behaviors_without_new_engine_actions(): void
    {
        foreach (['form_submission', 'review', 'payment', 'awaiting_results', 'completed'] as $behavior) {
            $type = $this->type($behavior, 'Catalog '.$behavior);
            $expected = $this->settings($behavior, 'Catalog '.$behavior);
            $this->assertSame($expected, $type->stageDefaults());
            $stage = $this->stage($type);
            $stage['data']['stage_type'] = 'spoofed-action';
            $workflow = WorkflowNotification::query()->create(['name' => 'Copy '.$behavior, 'stages' => [$stage]]);
            $saved = $workflow->fresh()->stages[0]['data'];
            $this->assertSame($behavior, $saved['stage_type']);
            $this->assertSame('Keep workflow name', $saved['stage_name']);
            $this->assertSame(['registrar_officer'], $saved['responsible_role']);
            foreach ($expected as $key => $value) {
                $this->assertSame($value, $saved[$key], $behavior.'.'.$key);
            }
            $this->assertArrayNotHasKey('stage_settings', $saved);
        }
    }

    public function test_catalog_settings_reject_unknown_paths_oversized_messages_and_non_staff_recipient_roles(): void
    {
        $invalid = [
            ['payment', ['payment_statuses' => ['paid_status_message' => 'Wrong path']], 'stage_settings.payment_statuses'],
            ['payment', ['responsible_role' => ['admin']], 'stage_settings'],
            ['payment', ['status_message' => str_repeat('s', 256)], 'stage_settings.status_message'],
            ['completed', ['notification_message' => str_repeat('m', 1001)], 'stage_settings.notification_message'],
            ['form_submission', ['submission_messages' => ['staff_recipient_roles' => ['candidate']]], 'stage_settings.submission_messages.staff_recipient_roles.0'],
            ['completed', ['audience_messages' => ['staff_recipient_roles' => ['missing-role']]], 'stage_settings.audience_messages.staff_recipient_roles.0'],
            ['awaiting_results', ['result_statuses' => ['pending_alert_message' => str_repeat('a', 256)]], 'stage_settings.result_statuses.pending_alert_message'],
        ];
        foreach ($invalid as $index => [$behavior, $settings, $error]) {
            try {
                WorkflowStageType::query()->create(['label_en' => 'Invalid '.$index, 'label_km' => 'ខ្មែរ '.$index, 'behavior' => $behavior, 'stage_settings' => $settings]);
                $this->fail('Invalid defaults must not be stored: '.$error);
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($error, $exception->errors());
            }
        }
        $payment = $this->type('payment', 'Allowed staff');
        $this->assertSame(['registrar_officer'], data_get($payment->stageDefaults(), 'audience_messages.staff_recipient_roles'));
    }

    public function test_explicit_workflow_values_null_and_empty_arrays_override_catalog_defaults_at_each_top_level_key(): void
    {
        foreach (['form_submission', 'review', 'payment', 'awaiting_results', 'completed'] as $behavior) {
            $type = $this->type($behavior, 'Default '.$behavior);
            foreach (['null' => null, 'empty' => [], 'custom' => 'Workflow-specific'] as $name => $override) {
                $stage = $this->stage($type);
                foreach ($type->stageDefaults() as $key => $value) {
                    $stage['data'][$key] = match ($name) {
                        'custom' => $this->settings($behavior, 'Workflow override')[$key],
                        'empty' => is_array($value) ? [] : null,
                        default => $override,
                    };
                }
                $workflow = WorkflowNotification::query()->create(['name' => $behavior.' '.$name, 'stages' => [$stage]]);
                $saved = $workflow->fresh()->stages[0]['data'];
                foreach (array_keys($type->stageDefaults()) as $key) {
                    $this->assertSame($stage['data'][$key], $saved[$key], $behavior.'.'.$key.' '.$name);
                }
            }
        }
        $payment = $this->type('payment', 'Partial payment');
        $stage = $this->stage($payment);
        $stage['data']['payment_statuses'] = ['unpaid_status_message' => 'Keep unpaid wording'];
        $workflow = WorkflowNotification::query()->create(['name' => 'Partial explicit settings', 'stages' => [$stage]]);
        $this->assertSame(['unpaid_status_message' => 'Keep unpaid wording'], $workflow->stages[0]['data']['payment_statuses']);
        $this->assertSame('Partial payment status', $workflow->stages[0]['data']['status_message']);
    }

    public function test_later_defaults_do_not_enter_existing_workflows_and_application_snapshots_remain_frozen(): void
    {
        $type = $this->type('payment', 'Original payment');
        $workflow = WorkflowNotification::query()->create(['name' => 'Frozen payment', 'stages' => [
            ['type' => 'stage', 'data' => ['stage_name' => 'Submit', 'stage_type' => 'form_submission']],
            $this->stage($type),
        ]]);
        $form = CustomForm::query()->create(['name' => 'Payment settings application', 'slug' => 'payment-settings-application', 'is_active' => true]);
        $workflow->forms()->attach($form);
        $entry = CustomFormEntry::query()->forceCreate(['custom_form_id' => $form->getKey(), 'review_status' => 'pending', 'data' => ['registration_status' => 'pending']]);
        $entryBefore = $entry->fresh()->getRawOriginal();
        $stagesBefore = $workflow->getRawOriginal('stages');
        $type->update(['stage_settings' => $this->settings('payment', 'Changed payment'), 'is_active' => false]);
        $workflow->update(['name' => 'Title-only workflow edit']);
        $this->assertSame($stagesBefore, $workflow->fresh()->getRawOriginal('stages'));
        $this->assertSame($entryBefore, $entry->fresh()->getRawOriginal());
        $this->assertSame('Original payment status', data_get($entry->fresh()->workflow_snapshot, 'stages.1.data.status_message'));

        $blank = WorkflowStageType::query()->create(['label_en' => 'Initially unconfigured payment', 'label_km' => 'ការបង់ប្រាក់ទទេ', 'behavior' => 'payment']);
        $existing = WorkflowNotification::query()->create(['name' => 'Existing absent settings', 'stages' => [$this->stage($blank)]]);
        $before = $existing->getRawOriginal('stages');
        $blank->update(['stage_settings' => $this->settings('payment', 'Added later')]);
        $existing->update(['name' => 'Unrelated existing title edit']);
        $this->assertSame($before, $existing->fresh()->getRawOriginal('stages'));
        $this->assertArrayNotHasKey('payment_statuses', $existing->fresh()->stages[0]['data']);
        $future = WorkflowNotification::query()->create(['name' => 'Future payment defaults', 'stages' => [$this->stage($blank->fresh())]]);
        $this->assertSame('Added later unpaid', data_get($future->stages, '0.data.payment_statuses.unpaid_status_message'));
    }

    public function test_new_form_selections_copy_each_non_review_behavior_and_same_behavior_aliases_preserve_custom_settings(): void
    {
        foreach (['form_submission', 'payment', 'awaiting_results', 'completed'] as $behavior) {
            $type = $this->type($behavior, 'Selected '.$behavior);
            $component = Livewire::test(CreateWorkflowNotification::class);
            $key = array_key_first($component->get('data.stages'));
            $path = 'data.stages.'.$key.'.data';
            $component->set($path.'.stage_type_id', $type->getKey())->assertSet($path.'.stage_type', $behavior);
            foreach ($type->stageDefaults() as $field => $value) {
                $component->assertSet($path.'.'.$field, $value);
            }
        }
        $first = $this->type('payment', 'First payment label');
        $second = $this->type('payment', 'Second payment label');
        $component = Livewire::test(CreateWorkflowNotification::class);
        $key = array_key_first($component->get('data.stages'));
        $path = 'data.stages.'.$key.'.data';
        $component->set($path.'.stage_type_id', $first->getKey())
            ->set($path.'.stage_name', 'Custom payment name')
            ->set($path.'.responsible_role', ['registrar_officer'])
            ->set($path.'.status_message', 'Keep custom paid wording')
            ->set($path.'.stage_type_id', $second->getKey())
            ->assertSet($path.'.stage_name', 'Custom payment name')
            ->assertSet($path.'.responsible_role', ['registrar_officer'])
            ->assertSet($path.'.status_message', 'Keep custom paid wording')
            ->assertSet($path.'.payment_statuses.unpaid_status_message', 'First payment label unpaid');
    }

    public function test_non_review_apply_button_is_absent_and_manual_settings_survive_opening_and_saving(): void
    {
        $type = $this->type('payment', 'Catalog payment');
        $stage = $this->stage($type);
        $stage['data'] = [...$stage['data'], ...$this->settings('payment', 'Workflow payment'), 'payment_source' => 'system'];
        $workflow = WorkflowNotification::query()->create(['name' => 'Keep manual payment settings', 'stages' => [$stage]]);
        $before = $workflow->getRawOriginal('stages');
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $key = array_key_first($component->get('data.stages'));
        $path = 'data.stages.'.$key.'.data';
        $action = collect($component->instance()->form->getFlatComponents())->first(fn ($component): bool => $component instanceof Action && $component->getName() === 'applyStageTypeSettings');
        $this->assertNull($action);
        $component->assertSet($path.'.status_message', 'Workflow payment status')
            ->assertSet($path.'.payment_statuses.unpaid_status_message', 'Workflow payment unpaid')
            ->assertSet($path.'.stage_name', 'Keep workflow name')
            ->assertSet($path.'.responsible_role', ['registrar_officer'])
            ->assertSet($path.'.payment_source', 'system');
        $this->assertSame($before, $workflow->fresh()->getRawOriginal('stages'));
        $component->call('save')->assertHasNoFormErrors();
        $saved = $workflow->fresh()->stages[0]['data'];
        foreach ($this->settings('payment', 'Workflow payment') as $field => $value) {
            $this->assertSame($value, $saved[$field]);
        }
        $this->assertSame('Keep workflow name', $saved['stage_name']);
        $this->assertSame(['registrar_officer'], $saved['responsible_role']);
        $this->assertSame('system', $saved['payment_source']);
    }

    public function test_ams_payment_defaults_never_import_local_recipients_and_edits_preserve_ams_roles_and_messages(): void
    {
        $type = $this->type('payment', 'AMS catalog payment');
        $defaults = $type->stageDefaults('ams');
        $this->assertArrayNotHasKey('staff_recipient_roles', $defaults['audience_messages']);
        $stage = $this->stage($type);
        $stage['data']['payment_source'] = 'ams';
        $stage['data']['responsible_role'] = ['ams_cashier'];
        $withoutExplicitRoles = WorkflowNotification::query()->create(['name' => 'AMS without local roles', 'stages' => [$stage]]);
        $this->assertArrayNotHasKey('staff_recipient_roles', $withoutExplicitRoles->stages[0]['data']['audience_messages']);

        $stage['data']['audience_messages'] = ['staff_recipient_roles' => ['ams_cashier'], 'staff_notification_message' => 'Keep workflow AMS text'];
        $workflow = WorkflowNotification::query()->create(['name' => 'Keep AMS settings', 'stages' => [$stage]]);
        $before = $workflow->getRawOriginal('stages');
        $this->assertSame(['ams_cashier'], data_get($workflow->stages, '0.data.audience_messages.staff_recipient_roles'));
        Http::assertNothingSent();

        config()->set(['services.ams.enabled' => true, 'services.ams.base_url' => 'https://ams.example.test', 'services.ams.token' => 'fake-test-token']);
        Http::fake(['https://ams.example.test/api/external/candidate/roles*' => Http::response(['data' => [['name' => 'ams_cashier', 'label' => 'AMS cashier']]])]);
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $key = array_key_first($component->get('data.stages'));
        $path = 'data.stages.'.$key.'.data';
        $action = collect($component->instance()->form->getFlatComponents())->first(fn ($component): bool => $component instanceof Action && $component->getName() === 'applyStageTypeSettings');
        $this->assertNull($action);
        $component->assertSet($path.'.audience_messages.staff_notification_message', 'Keep workflow AMS text')
            ->assertSet($path.'.audience_messages.staff_recipient_roles', ['ams_cashier'])
            ->assertSet($path.'.responsible_role', ['ams_cashier'])
            ->assertSet($path.'.payment_source', 'ams');
        $this->assertSame($before, $workflow->fresh()->getRawOriginal('stages'));
        $component->call('save')->assertHasNoFormErrors();
        $this->assertSame(['ams_cashier'], data_get($workflow->fresh()->stages, '0.data.audience_messages.staff_recipient_roles'));
        $this->assertSame(['ams_cashier'], data_get($workflow->fresh()->stages, '0.data.responsible_role'));
        $this->assertSame('Keep workflow AMS text', data_get($workflow->fresh()->stages, '0.data.audience_messages.staff_notification_message'));
    }

    private function type(string $behavior, string $label): WorkflowStageType
    {
        $settings = $this->settings($behavior, $label);

        return WorkflowStageType::query()->create([
            'label_en' => $label, 'label_km' => 'ខ្មែរ '.$label, 'behavior' => $behavior, 'is_active' => true,
            ...($behavior === 'review' ? $settings : ['stage_settings' => $settings]),
        ]);
    }

    private function settings(string $behavior, string $prefix): array
    {
        if ($behavior === 'review') {
            return ['review_actions' => ['accept' => ['status_message' => $prefix.' checked', 'staff_recipient_roles' => ['registrar_officer']], 'send_back' => ['status_message' => $prefix.' returned'], 'reject' => ['status_message' => $prefix.' rejected']]];
        }
        if ($behavior === 'form_submission') {
            return ['submission_messages' => ['candidate_status_message' => $prefix.' submitted', 'candidate_notification_message' => $prefix.' candidate notification', 'staff_notification_message' => $prefix.' staff notification', 'staff_recipient_roles' => ['registrar_officer']], 'alert_message' => $prefix.' alert'];
        }

        return [
            ...match ($behavior) {
                'payment' => ['payment_statuses' => ['unpaid_status_message' => $prefix.' unpaid']],
                'awaiting_results' => ['result_statuses' => ['pending_status_message' => $prefix.' waiting', 'pending_alert_message' => $prefix.' pending alert']],
                default => [],
            },
            'status_message' => $prefix.' status', 'alert_message' => $prefix.' alert', 'notification_message' => $prefix.' candidate notification',
            'audience_messages' => ['staff_notification_message' => $prefix.' staff notification', 'staff_recipient_roles' => ['registrar_officer']],
        ];
    }

    private function stage(WorkflowStageType $type): array
    {
        return ['type' => 'stage', 'data' => ['stage_name' => 'Keep workflow name', 'stage_type' => $type->behavior, 'stage_type_id' => $type->getKey(), 'responsible_role' => ['registrar_officer']]];
    }
}
