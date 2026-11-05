<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType as StageBehavior;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\CreateWorkflowNotification;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\EditWorkflowNotification;
use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\EditWorkflowStageType;
use App\Filament\Admin\Resources\WorkflowStageTypes\WorkflowStageTypeResource;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Models\WorkflowStageType;
use App\Support\WorkflowStageProgression;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkflowStageTypeReviewActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        Notification::fake();
        $admin = $this->user('review-defaults-admin');
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        Role::findOrCreate('registrar_officer', 'web')->givePermissionTo(Permission::findOrCreate('Accepted:CustomFormEntry', 'web'));
        Role::findOrCreate('candidate', 'web')->forceFill(['role_type_key' => 'candidate'])->save();
        $this->actingAs($admin);
    }

    public function test_programmatic_workflows_copy_defaults_only_when_review_actions_key_is_absent(): void
    {
        $type = $this->type('Programmatic defaults', $this->actions('Default'));
        $workflow = WorkflowNotification::query()->create(['name' => 'Defaults copied', 'stages' => [$this->stage($type)]]);
        $this->assertSame($type->actionDefaults(), data_get($workflow->stages, '0.data.review_actions'));

        foreach (['null' => null, 'empty' => [], 'custom' => $this->actions('Override')] as $name => $override) {
            $stage = $this->stage($type);
            $stage['data']['review_actions'] = $override;
            $workflow = WorkflowNotification::query()->create(['name' => 'Preserve '.$name, 'stages' => [$stage]]);
            $this->assertSame($override, data_get($workflow->stages, '0.data.review_actions'));
        }

        $previouslyUnconfigured = $this->type('Previously unconfigured catalog review', []);
        $existing = WorkflowNotification::query()->create(['name' => 'Existing catalog workflow', 'stages' => [$this->stage($previouslyUnconfigured)]]);
        $before = $existing->getRawOriginal('stages');
        $this->assertArrayNotHasKey('review_actions', $existing->stages[0]['data']);
        $previouslyUnconfigured->update(['review_actions' => $this->actions('Added later')]);
        $existing->update(['name' => 'Unrelated workflow title edit']);
        $this->assertArrayNotHasKey('review_actions', $existing->fresh()->stages[0]['data']);
        $this->assertSame($before, $existing->fresh()->getRawOriginal('stages'));
    }

    public function test_new_review_selection_copies_defaults_and_same_behavior_aliases_preserve_names_roles_and_custom_settings(): void
    {
        $first = $this->type('First review defaults', $this->actions('First'));
        $second = $this->type('Second review defaults', $this->actions('Second'));
        $component = Livewire::test(CreateWorkflowNotification::class);
        $key = array_key_first($component->get('data.stages'));
        $path = 'data.stages.'.$key.'.data';
        $component->set($path.'.stage_type_id', $first->getKey())
            ->assertSet($path.'.stage_type', 'review')
            ->assertSet($path.'.review_actions.accept.status_message', 'First checked')
            ->assertSet($path.'.review_actions.reject.staff_recipient_roles', ['registrar_officer'])
            ->set($path.'.stage_name', 'Workflow-specific name')
            ->set($path.'.responsible_role', ['registrar_officer'])
            ->set($path.'.review_actions.accept.status_message', 'Workflow-specific checked label')
            ->set($path.'.stage_type_id', $second->getKey())
            ->assertSet($path.'.stage_name', 'Workflow-specific name')
            ->assertSet($path.'.responsible_role', ['registrar_officer'])
            ->assertSet($path.'.review_actions.accept.status_message', 'Workflow-specific checked label')
            ->assertSet($path.'.review_actions.send_back.status_message', 'First sent back');
    }

    public function test_explicit_apply_copies_only_action_defaults_and_requires_workflow_save(): void
    {
        $type = $this->type('Apply current review settings', $this->actions('Catalog'));
        $stage = $this->stage($type);
        $stage['data']['review_actions'] = $this->actions('Workflow');
        $workflow = WorkflowNotification::query()->create(['name' => 'Explicit defaults application', 'stages' => [$stage]]);
        $before = $workflow->getRawOriginal('stages');
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $key = array_key_first($component->get('data.stages'));
        $path = 'data.stages.'.$key.'.data';
        $sectionKey = collect(array_keys($component->instance()->form->getFlatComponents()))
            ->first(fn ($key): bool => is_string($key) && ($key === 'review-actions' || str_ends_with($key, '.review-actions')));
        $this->assertNotNull($sectionKey);
        $component->callAction(TestAction::make('applyStageTypeReviewSettings')->schemaComponent($sectionKey, schema: 'form'))
            ->assertSet($path.'.review_actions.accept.status_message', 'Catalog checked')
            ->assertSet($path.'.review_actions.send_back.status_message', 'Catalog sent back')
            ->assertSet($path.'.stage_name', 'Keep custom name')
            ->assertSet($path.'.responsible_role', ['registrar_officer'])
            ->assertSet($path.'.stage_type', 'review');
        $this->assertSame($before, $workflow->fresh()->getRawOriginal('stages'));

        $component->call('save')->assertHasNoFormErrors();
        $this->assertSame($type->actionDefaults(), data_get($workflow->fresh()->stages, '0.data.review_actions'));
        $this->assertSame('Keep custom name', data_get($workflow->fresh()->stages, '0.data.stage_name'));
        $this->assertSame(['registrar_officer'], data_get($workflow->fresh()->stages, '0.data.responsible_role'));
    }

    public function test_catalog_default_edits_do_not_rewrite_existing_workflows_or_application_snapshots(): void
    {
        $type = $this->type('Frozen review configuration', $this->actions('Original'));
        $workflow = WorkflowNotification::query()->create(['name' => 'Frozen review defaults', 'stages' => [
            ['type' => 'stage', 'data' => ['stage_name' => 'Submit', 'stage_type' => 'form_submission']],
            $this->stage($type),
        ]]);
        $form = CustomForm::query()->create(['name' => 'Review defaults application', 'slug' => 'review-defaults-application', 'is_active' => true]);
        $workflow->forms()->attach($form);
        $entry = CustomFormEntry::query()->forceCreate(['custom_form_id' => $form->getKey(), 'review_status' => 'pending', 'data' => ['registration_status' => 'pending']]);
        $entryBefore = $entry->fresh()->getRawOriginal();
        $workflowBefore = $workflow->getRawOriginal('stages');
        $type->update(['review_actions' => $this->actions('Changed')]);

        $this->assertSame('Original checked', data_get($entry->fresh()->workflow_snapshot, 'stages.1.data.review_actions.accept.status_message'));
        $this->assertSame($workflowBefore, $workflow->fresh()->getRawOriginal('stages'));
        $this->assertSame($entryBefore, $entry->fresh()->getRawOriginal());
        $this->assertSame(StageBehavior::Review, WorkflowStageProgression::currentType($entry->fresh()));
        $newWorkflow = WorkflowNotification::query()->create(['name' => 'Future defaults', 'stages' => [$this->stage($type->fresh())]]);
        $this->assertSame('Changed checked', data_get($newWorkflow->stages, '0.data.review_actions.accept.status_message'));
    }

    public function test_review_configuration_uses_existing_catalog_update_permission_and_is_ignored_for_non_review_behaviors(): void
    {
        $review = $this->type('Permission review settings', $this->actions('Review'));
        $operator = $this->user('review-defaults-operator');
        $operator->assignRole(Role::findOrCreate('review_defaults_operator', 'web'));
        foreach (['ViewAny:WorkflowStageType', 'ViewAny:WorkflowNotification', 'Update:WorkflowNotification'] as $permission) {
            $operator->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $this->actingAs($operator);
        $this->get(WorkflowStageTypeResource::getUrl('edit', ['record' => $review]))->assertForbidden();
        $operator->givePermissionTo(Permission::findOrCreate('Update:WorkflowStageType', 'web'));
        $this->get(WorkflowStageTypeResource::getUrl('edit', ['record' => $review]))->assertOk();

        $payment = $this->type('Payment ignores review configuration', $this->actions('Ignored'), 'payment');
        $this->assertNull($payment->review_actions);
        $this->assertSame([], $payment->actionDefaults());
        Livewire::test(EditWorkflowStageType::class, ['record' => $payment->getRouteKey()])
            ->assertFormFieldDoesNotExist('review_actions.accept.status_message');
        $workflow = WorkflowNotification::query()->create(['name' => 'Payment without review defaults', 'stages' => [$this->stage($payment)]]);
        $this->assertArrayNotHasKey('review_actions', $workflow->stages[0]['data']);
    }

    public function test_review_default_validation_rejects_oversized_messages_and_non_staff_roles_and_treats_blank_settings_as_empty(): void
    {
        $actions = $this->actions('Invalid');
        $actions['accept']['status_message'] = str_repeat('s', 256);
        $actions['send_back']['notification_message'] = str_repeat('m', 1001);
        $actions['reject']['staff_recipient_roles'] = ['candidate'];
        try {
            $this->type('Invalid review defaults', $actions);
            $this->fail('Invalid programmatic review defaults must fail validation.');
        } catch (ValidationException $exception) {
            foreach (['review_actions.accept.status_message', 'review_actions.send_back.notification_message', 'review_actions.reject.staff_recipient_roles.0'] as $field) {
                $this->assertArrayHasKey($field, $exception->errors());
            }
        }
        try {
            $this->type('Unknown review action key', ['unknown' => ['status_message' => 'No new engine action']]);
            $this->fail('Unsupported review action keys must fail validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('review_actions', $exception->errors());
        }
        $this->assertFalse(WorkflowStageType::hasReviewActionSettings(null));
        $this->assertFalse(WorkflowStageType::hasReviewActionSettings([]));
        $this->assertFalse(WorkflowStageType::hasReviewActionSettings(['accept' => ['status_message' => null, 'staff_recipient_roles' => []]]));
        $this->assertTrue(WorkflowStageType::hasReviewActionSettings(['accept' => ['staff_recipient_roles' => ['registrar_officer']]]));
    }

    private function actions(string $prefix): array
    {
        $actions = [];
        foreach (['accept' => 'checked', 'send_back' => 'sent back', 'reject' => 'rejected'] as $action => $status) {
            $actions[$action] = [
                'status_message' => $prefix.' '.$status,
                'alert_message' => $prefix.' '.$status.' alert',
                'notification_message' => $prefix.' candidate '.$status,
                'staff_notification_message' => $prefix.' staff '.$status,
                'staff_recipient_roles' => ['registrar_officer'],
            ];
        }

        return $actions;
    }

    private function type(string $label, array $actions, string $behavior = 'review'): WorkflowStageType
    {
        return WorkflowStageType::query()->create(['label_en' => $label, 'label_km' => 'ខ្មែរ '.$label, 'behavior' => $behavior, 'is_active' => true, 'review_actions' => $actions]);
    }

    private function stage(WorkflowStageType $type): array
    {
        return ['type' => 'stage', 'data' => ['stage_name' => 'Keep custom name', 'stage_type' => $type->behavior, 'stage_type_id' => $type->getKey(), 'responsible_role' => ['registrar_officer']]];
    }

    private function user(string $username): User
    {
        return User::query()->create(['registration_type' => 'admin', 'name' => $username, 'username' => $username, 'email' => $username.'@example.test', 'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true]);
    }
}
