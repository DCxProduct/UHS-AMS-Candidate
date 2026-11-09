<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\WorkflowNotifications\Pages\CreateWorkflowNotification;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\EditWorkflowNotification;
use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\EditWorkflowStageType;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowButton;
use App\Models\WorkflowNotification;
use App\Models\WorkflowStageType;
use App\Support\WorkflowStageTypeSettings;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkflowStageTypeSelectedActionsTest extends TestCase
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
        $admin = User::query()->create(['registration_type' => 'admin', 'name' => 'Selected actions admin', 'username' => 'selected-actions-admin', 'email' => 'selected-actions-admin@example.test', 'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true]);
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        Role::findOrCreate('registrar_officer', 'web')->givePermissionTo(Permission::findOrCreate('Accepted:CustomFormEntry', 'web'));
        $this->actingAs($admin);
    }

    public function test_catalog_null_keeps_all_actions_and_explicit_subsets_are_nonempty_valid_unique_and_canonically_ordered(): void
    {
        foreach (['form_submission', 'review', 'payment', 'awaiting_results', 'completed'] as $behavior) {
            $legacy = $this->type('Legacy '.$behavior, $behavior, null);
            $this->assertNull($legacy->enabled_actions);
            $this->assertSame(array_keys(WorkflowStageTypeSettings::actions($behavior)), $legacy->enabledActionKeys());
            $this->assertArrayNotHasKey('enabled_actions', $legacy->stageDefaults());
        }
        $subset = $this->type('Ordered subset', 'review', ['reject', 'accept']);
        $this->assertSame(['accept', 'reject'], $subset->enabledActionKeys());
        $this->assertSame(['accept', 'reject'], $subset->fresh()->enabled_actions);
        foreach ([[], ['accept', 'accept'], ['paid'], ['unknown'], 'accept'] as $index => $invalid) {
            try {
                $this->type('Invalid selection '.$index, 'review', $invalid);
                $this->fail('Invalid catalog action selections must fail validation.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty(array_filter(array_keys($exception->errors()), fn (string $key): bool => str_starts_with($key, 'enabled_actions')));
            }
        }
    }

    public function test_catalog_button_edits_preserve_saved_configuration_and_legacy_status_selections(): void
    {
        $actions = ['accept' => ['status_message' => 'Checked wording'], 'send_back' => ['status_message' => 'Retained send back wording'], 'reject' => ['status_message' => 'Retained rejection wording']];
        $buttons = collect(array_keys($actions))->mapWithKeys(fn (string $action): array => [
            $action => WorkflowButton::query()->create(['label_en' => 'Selected '.$action, 'action_key' => $action]),
        ]);
        $type = $this->type('Selected catalog review', 'review', ['accept', 'send_back', 'reject'], ['review_actions' => $actions]);
        $this->assertSame(['accept', 'send_back', 'reject'], $type->enabled_actions);
        $before = $type->getRawOriginal('review_actions');
        Livewire::test(EditWorkflowStageType::class, ['record' => $type->getRouteKey()])
            ->set('data.label_en', 'Renamed catalog review')
            ->set('data.workflow_button_ids', [$buttons['accept']->getKey()])
            ->set('data.enabled_actions', ['reject'])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame($before, $type->fresh()->getRawOriginal('review_actions'));
        $this->assertSame(['accept'], array_keys($type->fresh()->actionDefaults()));
        Livewire::test(EditWorkflowStageType::class, ['record' => $type->getRouteKey()])
            ->set('data.workflow_button_ids', $buttons->pluck('id')->all())
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame(['accept', 'send_back', 'reject'], $type->fresh()->enabled_actions);
        $this->assertSame($before, $type->fresh()->getRawOriginal('review_actions'));

        $cases = [
            ['payment', null, 'unpaid', 'paid'],
            ['payment', ['unpaid', 'paid'], 'unpaid', 'paid'],
            ['payment', ['unpaid'], 'unpaid', 'paid'],
            ['awaiting_results', null, 'pending', 'passed'],
            ['awaiting_results', ['pending', 'passed'], 'pending', 'passed'],
            ['awaiting_results', ['pending'], 'pending', 'passed'],
        ];
        foreach ($cases as $index => [$behavior, $selection, $status, $button]) {
            $settings = $behavior === 'payment'
                ? ['payment_statuses' => ['unpaid_status_message' => 'Retained unpaid wording'], 'status_message' => 'Retained paid wording']
                : ['result_statuses' => ['pending_status_message' => 'Retained pending wording'], 'status_message' => 'Retained passed wording'];
            $legacy = $this->type('Legacy button selection '.$index, $behavior, $selection, ['stage_settings' => $settings]);
            $selectionBefore = $legacy->getRawOriginal('enabled_actions');
            $settingsBefore = $legacy->getRawOriginal('stage_settings');
            $component = Livewire::test(EditWorkflowStageType::class, ['record' => $legacy->getRouteKey()]);
            $this->assertInstanceOf(Hidden::class, $component->instance()->form->getFlatFields(withHidden: true)['enabled_actions']);
            $component->assertSet('data.enabled_actions', $selection)
                ->set('data.label_en', 'Renamed legacy selection '.$index)
                ->call('save')->assertHasNoFormErrors();
            $this->assertSame($selectionBefore, $legacy->fresh()->getRawOriginal('enabled_actions'));
            $this->assertSame($settingsBefore, $legacy->fresh()->getRawOriginal('stage_settings'));

            // Posted hidden values must never replace legacy nulls, status keys, or behavior.
            foreach ([[$status], [$button], []] as $forgedSelection) {
                Livewire::test(EditWorkflowStageType::class, ['record' => $legacy->getRouteKey()])
                    ->set('data.enabled_actions', $forgedSelection)
                    ->set('data.behavior', 'review')
                    ->call('save')->assertHasNoFormErrors();
                $this->assertSame($selectionBefore, $legacy->fresh()->getRawOriginal('enabled_actions'));
                $this->assertSame($settingsBefore, $legacy->fresh()->getRawOriginal('stage_settings'));
                $this->assertSame($behavior, $legacy->fresh()->behavior);
                $this->assertNull($legacy->fresh()->workflow_button_ids);
            }
        }
    }

    public function test_catalog_selection_copies_into_new_workflows_and_same_behavior_alias_preserves_workflow_selection(): void
    {
        $first = $this->type('Selected payment', 'payment', ['paid']);
        $second = $this->type('Other payment alias', 'payment', ['unpaid']);
        $workflow = WorkflowNotification::query()->create(['name' => 'Programmatic selected payment', 'stages' => [$this->stage($first)]]);
        $this->assertSame(['paid'], data_get($workflow->stages, '0.data.enabled_actions'));
        $component = Livewire::test(CreateWorkflowNotification::class);
        $key = array_key_first($component->get('data.stages'));
        $path = 'data.stages.'.$key.'.data';
        $component->set($path.'.stage_type_id', $first->getKey())
            ->assertSet($path.'.enabled_actions', ['paid'])
            ->set($path.'.stage_name', 'Custom workflow payment name')
            ->set($path.'.responsible_role', ['registrar_officer'])
            ->set($path.'.stage_type_id', $second->getKey())
            ->assertSet($path.'.enabled_actions', ['paid'])
            ->assertSet($path.'.stage_name', 'Custom workflow payment name')
            ->assertSet($path.'.responsible_role', ['registrar_officer'])
            ->set('data.name', 'Saved selection from form')->call('create')->assertHasNoFormErrors();
        $saved = WorkflowNotification::query()->where('name', 'Saved selection from form')->firstOrFail();
        $this->assertSame(['paid'], data_get($saved->stages, '0.data.enabled_actions'));
    }

    public function test_catalog_changes_do_not_import_selection_into_existing_workflows_or_replace_frozen_snapshots(): void
    {
        $type = $this->type('Frozen review selection', 'review', ['accept', 'reject']);
        $workflow = WorkflowNotification::query()->create(['name' => 'Frozen action workflow', 'stages' => [$this->stage($type)]]);
        $form = CustomForm::query()->create(['name' => 'Frozen action application', 'slug' => 'frozen-action-application', 'is_active' => true]);
        $workflow->forms()->attach($form);
        $entry = CustomFormEntry::query()->forceCreate(['custom_form_id' => $form->getKey(), 'review_status' => 'pending', 'data' => ['registration_status' => 'pending']]);
        $entryBefore = $entry->fresh()->getRawOriginal();
        $before = $workflow->getRawOriginal('stages');
        $type->update(['enabled_actions' => ['send_back'], 'is_active' => false]);
        $workflow->update(['name' => 'Unrelated title edit']);
        $this->assertSame($before, $workflow->fresh()->getRawOriginal('stages'));
        $this->assertSame($entryBefore, $entry->fresh()->getRawOriginal());
        $this->assertSame(['accept', 'reject'], data_get($entry->fresh()->workflow_snapshot, 'stages.0.data.enabled_actions'));
        $future = WorkflowNotification::query()->create(['name' => 'Future selection', 'stages' => [$this->stage($type->fresh())]]);
        $this->assertSame(['send_back'], data_get($future->stages, '0.data.enabled_actions'));

        $legacyType = $this->type('Legacy workflow selection', 'payment', null);
        $legacy = WorkflowNotification::query()->create(['name' => 'Legacy selection absent', 'stages' => [$this->stage($legacyType)]]);
        $before = $legacy->getRawOriginal('stages');
        $legacyType->update(['enabled_actions' => ['paid']]);
        $legacy->update(['name' => 'Legacy title edit']);
        $this->assertSame($before, $legacy->fresh()->getRawOriginal('stages'));
        Livewire::test(EditWorkflowNotification::class, ['record' => $legacy->getRouteKey()])->call('save')->assertHasNoFormErrors();
        $this->assertArrayNotHasKey('enabled_actions', $legacy->fresh()->stages[0]['data']);
    }

    public function test_non_review_edits_keep_their_selection_and_separate_review_apply_still_requires_save(): void
    {
        foreach (['payment' => ['paid', 'unpaid'], 'review' => ['accept', 'reject']] as $behavior => [$catalogAction, $workflowAction]) {
            $type = $this->type('Existing selection '.$behavior, $behavior, [$catalogAction]);
            $stage = $this->stage($type);
            $stage['data']['enabled_actions'] = [$workflowAction];
            $stage['data']['status_message'] = 'Keep workflow wording';
            $workflow = WorkflowNotification::query()->create(['name' => 'Keep selection '.$behavior, 'stages' => [$stage]]);
            $before = $workflow->getRawOriginal('stages');
            $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
            $key = array_key_first($component->get('data.stages'));
            $path = 'data.stages.'.$key.'.data';
            $expectedSelection = [$workflowAction];
            if ($behavior === 'review') {
                $containerKey = collect(array_keys($component->instance()->form->getFlatComponents()))->first(fn ($key): bool => is_string($key) && str_ends_with($key, 'review-actions'));
                $this->assertNotNull($containerKey);
                $component->callAction(TestAction::make('applyStageTypeReviewSettings')->schemaComponent($containerKey, schema: 'form'));
                $expectedSelection = [$catalogAction];
            } else {
                $action = collect($component->instance()->form->getFlatComponents())->first(fn ($component): bool => $component instanceof Action && $component->getName() === 'applyStageTypeSettings');
                $this->assertNull($action);
            }
            $component->assertSet($path.'.enabled_actions', $expectedSelection)
                ->assertSet($path.'.stage_name', 'Keep workflow stage name')
                ->assertSet($path.'.responsible_role', ['registrar_officer'])
                ->assertSet($path.'.status_message', 'Keep workflow wording');
            $this->assertSame($before, $workflow->fresh()->getRawOriginal('stages'));
            $component->call('save')->assertHasNoFormErrors();
            $this->assertSame($expectedSelection, data_get($workflow->fresh()->stages, '0.data.enabled_actions'));
            if ($behavior !== 'review') {
                $this->assertSame('Keep workflow wording', data_get($workflow->fresh()->stages, '0.data.status_message'));
            }
        }
    }

    public function test_workflow_selection_validation_preserves_explicit_null_or_valid_override_without_importing_catalog_selection(): void
    {
        $type = $this->type('Workflow validation review', 'review', ['accept']);
        foreach ([null, ['reject']] as $index => $selection) {
            $stage = $this->stage($type);
            $stage['data']['enabled_actions'] = $selection;
            $workflow = WorkflowNotification::query()->create(['name' => 'Explicit workflow selection '.$index, 'stages' => [$stage]]);
            $this->assertSame($selection, $workflow->stages[0]['data']['enabled_actions']);
        }
        foreach ([[], ['paid'], ['accept', 'accept']] as $selection) {
            $stage = $this->stage($type);
            $stage['data']['enabled_actions'] = $selection;
            try {
                WorkflowNotification::query()->create(['name' => 'Invalid workflow selection', 'stages' => [$stage]]);
                $this->fail('Invalid explicit workflow selections must fail validation.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
    }

    private function type(string $label, string $behavior, mixed $enabledActions, array $attributes = []): WorkflowStageType
    {
        return WorkflowStageType::query()->create(['label_en' => $label, 'label_km' => 'ខ្មែរ '.$label, 'behavior' => $behavior, 'enabled_actions' => $enabledActions, ...$attributes]);
    }

    private function stage(WorkflowStageType $type): array
    {
        return ['type' => 'stage', 'data' => ['stage_name' => 'Keep workflow stage name', 'stage_type' => $type->behavior, 'stage_type_id' => $type->getKey(), 'responsible_role' => ['registrar_officer']]];
    }
}
