<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\WorkflowNotifications\Pages\CreateWorkflowNotification;
use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\CreateWorkflowStageType;
use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\EditWorkflowStageType;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowButton;
use App\Models\WorkflowNotification;
use App\Models\WorkflowStageType;
use App\Support\WorkflowStageTypeSettings;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use DOMDocument;
use DOMXPath;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkflowStageTypeGlobalActionsTest extends TestCase
{
    use RefreshDatabase;

    private const ACTIONS = ['submitted', 'verify', 'accept', 'send_back', 'reject', 'paid', 'passed', 'notify'];

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        Notification::fake();
        $admin = User::query()->create(['registration_type' => 'admin', 'name' => 'Global actions admin', 'username' => 'global-actions-admin', 'email' => 'global-actions-admin@example.test', 'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true]);
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        Role::findOrCreate('registrar_officer', 'web')->givePermissionTo(Permission::findOrCreate('Accepted:CustomFormEntry', 'web'));
        $this->actingAs($admin);
    }

    public function test_blank_create_shows_only_the_localized_catalog_selector_without_fixed_action_checkboxes(): void
    {
        $buttons = array_map(fn (string $action): WorkflowButton => $this->button($action), self::ACTIONS);

        foreach (['en', 'km'] as $locale) {
            app()->setLocale($locale);
            $component = Livewire::test(CreateWorkflowStageType::class)
                ->assertFormFieldVisible('workflow_button_ids');
            $fields = $component->instance()->form->getFlatFields(withHidden: true);
            $this->assertInstanceOf(Hidden::class, $fields['behavior']);
            $this->assertInstanceOf(Hidden::class, $fields['enabled_actions']);
            $this->assertInstanceOf(Select::class, $fields['workflow_button_ids']);
            $this->assertArrayNotHasKey('base_stage_type_id', $fields);
            $this->assertArrayNotHasKey('review_actions.accept.status_message', $fields);
            $this->assertArrayNotHasKey('stage_settings.status_message', $fields);
            $options = $fields['workflow_button_ids']->getOptions();
            $this->assertCount(count(self::ACTIONS), $options);
            foreach ($buttons as $button) {
                $this->assertSame($button->label(), $options[$button->getKey()]);
            }
            $document = new DOMDocument;
            $this->assertTrue($document->loadHTML('<meta charset="UTF-8">'.$component->html(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING));
            $this->assertSame(0, (new DOMXPath($document))->query('//fieldset[@data-stage-type-button-group]')->length);
        }
    }

    public function test_catalog_button_groups_infer_their_existing_canonical_behavior_and_save_without_a_base_selection(): void
    {
        $groups = ['form_submission' => ['submitted'], 'verification' => ['verify'], 'review' => ['accept', 'reject'], 'payment' => ['paid'], 'awaiting_results' => ['passed'], 'completed' => ['notify']];
        foreach ($groups as $behavior => $actions) {
            $this->assertSame($behavior, WorkflowStageTypeSettings::behaviorForSelection($actions));
            $ids = array_map(fn (string $action): int => $this->button($action)->getKey(), $actions);
            Livewire::test(CreateWorkflowStageType::class)
                ->fillForm(['label_en' => 'Global '.$behavior, 'label_km' => 'ខ្មែរ '.$behavior, 'is_active' => true])
                ->set('data.workflow_button_ids', $ids)
                ->call('create')->assertHasNoFormErrors();
            $type = WorkflowStageType::query()->where('label_en', 'Global '.$behavior)->firstOrFail();
            $this->assertSame($behavior, $type->behavior);
            $this->assertSame($actions, $type->enabled_actions);
            $this->assertSame($ids, $type->workflow_button_ids);
            $this->assertEmpty($type->review_actions);
            $this->assertEmpty($type->stage_settings);
        }
    }

    public function test_changing_catalog_buttons_in_the_same_group_preserves_entered_labels_and_active_state(): void
    {
        $accept = $this->button('accept');
        $reject = $this->button('reject');
        Livewire::test(CreateWorkflowStageType::class)
            ->fillForm(['label_en' => 'My configured review', 'label_km' => 'ការពិនិត្យរបស់ខ្ញុំ', 'is_active' => false])
            ->set('data.workflow_button_ids', [$accept->getKey()])
            ->set('data.workflow_button_ids', [$accept->getKey(), $reject->getKey()])
            ->set('data.workflow_button_ids', [$reject->getKey()])
            ->set('data.workflow_button_ids', [$accept->getKey(), $reject->getKey()])
            ->assertSet('data.label_en', 'My configured review')->assertSet('data.label_km', 'ការពិនិត្យរបស់ខ្ញុំ')
            ->assertSet('data.is_active', false)
            ->call('create')->assertHasNoFormErrors();
        $type = WorkflowStageType::query()->where('label_en', 'My configured review')->firstOrFail();
        $this->assertSame(['accept', 'reject'], $type->enabled_actions);
        $this->assertSame([$accept->getKey(), $reject->getKey()], $type->workflow_button_ids);
        $this->assertFalse($type->is_active);
    }

    public function test_mixed_empty_unknown_and_duplicate_catalog_selections_fail_without_being_silently_truncated(): void
    {
        $accept = $this->button('accept');
        $otherAccept = $this->button('accept');
        $paid = $this->button('paid');
        $cases = [[$accept->getKey(), $paid->getKey()], [], [999999], [$accept->getKey(), $accept->getKey()], [$accept->getKey(), $otherAccept->getKey()]];
        foreach ($cases as $index => $ids) {
            $component = Livewire::test(CreateWorkflowStageType::class)
                ->fillForm(['label_en' => 'Invalid global '.$index, 'label_km' => 'មិនត្រឹមត្រូវ '.$index])
                ->set('data.workflow_button_ids', $ids)->call('create')->assertHasFormErrors();
            $this->assertNotEmpty(array_filter($component->errors()->keys(), fn (string $key): bool => str_starts_with($key, 'data.workflow_button_ids')));
            $this->assertDatabaseMissing('workflow_stage_types', ['label_en' => 'Invalid global '.$index]);
        }
    }

    public function test_edit_rejects_a_different_catalog_action_group_and_leaves_the_existing_definition_unchanged(): void
    {
        $accept = $this->button('accept');
        $paid = $this->button('paid');
        $type = WorkflowStageType::query()->create(['label_en' => 'Immutable review', 'label_km' => 'ការពិនិត្យមិនអាចប្តូរ', 'workflow_button_ids' => [$accept->getKey()], 'review_actions' => ['accept' => ['status_message' => 'Original checked wording']]])->fresh();
        $before = $type->getRawOriginal();
        Livewire::test(EditWorkflowStageType::class, ['record' => $type->getRouteKey()])
            ->assertFormFieldDisabled('behavior')
            ->set('data.workflow_button_ids', [$paid->getKey()])
            ->call('save')->assertHasFormErrors();
        $this->assertSame($before, $type->fresh()->getRawOriginal());
    }

    public function test_create_guard_derives_behavior_from_catalog_ids_and_new_names_appear_in_workflow_options(): void
    {
        $paid = $this->button('paid');
        $component = Livewire::test(CreateWorkflowStageType::class);
        $payload = (new ReflectionMethod(CreateWorkflowStageType::class, 'mutateFormDataBeforeCreate'))->invoke($component->instance(), ['workflow_button_ids' => [$paid->getKey()], 'behavior' => 'review', 'enabled_actions' => ['accept'], 'review_actions' => ['accept' => ['status_message' => 'Unused posted review settings']], 'stage_settings' => ['status_message' => 'Unused posted stage settings']]);
        $this->assertSame('payment', $payload['behavior']);
        $this->assertSame(['paid'], $payload['enabled_actions']);
        $this->assertArrayNotHasKey('review_actions', $payload);
        $this->assertArrayNotHasKey('stage_settings', $payload);
        $component->fillForm(['label_en' => 'New payment name', 'label_km' => 'ឈ្មោះការបង់ប្រាក់ថ្មី', 'is_active' => true])
            ->set('data.workflow_button_ids', [$paid->getKey()])->call('create')->assertHasNoFormErrors();
        $type = WorkflowStageType::query()->where('label_en', 'New payment name')->firstOrFail();
        $this->assertSame('payment', $type->behavior);
        foreach (['en' => 'New payment name', 'km' => 'ឈ្មោះការបង់ប្រាក់ថ្មី'] as $locale => $label) {
            app()->setLocale($locale);
            $workflow = Livewire::test(CreateWorkflowNotification::class);
            $key = array_key_first($workflow->get('data.stages'));
            $field = collect($workflow->instance()->form->getFlatFields())->first(fn ($field): bool => $field instanceof Select && str_ends_with($field->getStatePath(), '.stage_type_id'));
            $this->assertNotNull($field);
            $this->assertSame($label, $field->getOptions()[$type->getKey()]);
            $workflow->set('data.stages.'.$key.'.data.stage_type_id', $type->getKey())
                ->assertSet('data.stages.'.$key.'.data.stage_type', 'payment')->assertSet('data.stages.'.$key.'.data.enabled_actions', ['paid']);
        }
    }

    public function test_creating_a_definition_from_catalog_buttons_does_not_change_legacy_workflows_or_application_snapshots(): void
    {
        $workflow = WorkflowNotification::query()->create(['name' => 'Legacy unchanged workflow', 'stages' => [['type' => 'stage', 'data' => ['stage_name' => 'Legacy review', 'stage_type' => 'review', 'responsible_role' => ['registrar_officer']]]]]);
        $form = CustomForm::query()->create(['name' => 'Legacy global checklist application', 'slug' => 'legacy-global-checklist-application', 'is_active' => true]);
        $workflow->forms()->attach($form);
        $entry = CustomFormEntry::query()->forceCreate(['custom_form_id' => $form->getKey(), 'review_status' => 'pending', 'data' => ['registration_status' => 'pending']]);
        $workflowBefore = $workflow->getRawOriginal('stages');
        $entryBefore = $entry->fresh()->getRawOriginal();
        $paid = $this->button('paid');
        Livewire::test(CreateWorkflowStageType::class)
            ->fillForm(['label_en' => 'Independent paid action', 'label_km' => 'សកម្មភាពបង់ប្រាក់ឯករាជ្យ'])
            ->set('data.workflow_button_ids', [$paid->getKey()])->call('create')->assertHasNoFormErrors();
        $this->assertSame($workflowBefore, $workflow->fresh()->getRawOriginal('stages'));
        $this->assertSame($entryBefore, $entry->fresh()->getRawOriginal());
        $this->assertArrayNotHasKey('enabled_actions', $workflow->fresh()->stages[0]['data']);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    private function button(string $action): WorkflowButton
    {
        return WorkflowButton::query()->create([
            'label_en' => 'Catalog '.$action,
            'label_km' => 'ប៊ូតុង '.$action,
            'action_key' => $action,
            'icon' => WorkflowStageTypeSettings::buttonActions()[$action]['icon'],
        ]);
    }
}
