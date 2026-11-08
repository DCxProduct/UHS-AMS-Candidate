<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\WorkflowNotifications\Pages\CreateWorkflowNotification;
use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\CreateWorkflowStageType;
use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\EditWorkflowStageType;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Models\WorkflowStageType;
use App\Support\WorkflowStageTypeSettings;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use DOMDocument;
use DOMXPath;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
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

    public function test_blank_create_shows_eight_localized_buttons_without_statuses_or_extra_settings(): void
    {
        foreach (['en', 'km'] as $locale) {
            app()->setLocale($locale);
            $component = Livewire::test(CreateWorkflowStageType::class)->assertFormFieldHidden('behavior')->assertFormFieldVisible('enabled_actions');
            $fields = $component->instance()->form->getFlatFields(withHidden: true);
            $this->assertArrayNotHasKey('base_stage_type_id', $fields);
            $this->assertArrayNotHasKey('review_actions.accept.status_message', $fields);
            $this->assertArrayNotHasKey('stage_settings.status_message', $fields);
            $this->assertInstanceOf(CheckboxList::class, $fields['enabled_actions']);
            $this->assertSame(self::ACTIONS, array_keys($fields['enabled_actions']->getOptions()));
            foreach ($fields['enabled_actions']->getOptions() as $value => $label) {
                $this->assertFalse($fields['enabled_actions']->isOptionDisabled($value, $label), $value);
            }
            $this->assertSame([], $component->get('data.enabled_actions'));
            $this->assertSame([], $fields['enabled_actions']->getDescriptions());
            foreach (WorkflowStageTypeSettings::buttonActions() as $value => $action) {
                $component->assertSee($action['label']);
                $this->assertSame($action['label'], $fields['enabled_actions']->getOptions()[$value]);
            }
            $xpath = $this->renderedButtons($component);
            $this->assertSame(2, $xpath->query('//fieldset[@data-stage-type-button-group]')->length);
            foreach (['candidate' => ['submitted'], 'system' => ['verify', 'accept', 'send_back', 'reject', 'paid', 'passed', 'notify']] as $group => $expected) {
                $legend = $xpath->query('//fieldset[@data-stage-type-button-group="'.$group.'"]/legend')->item(0);
                $this->assertNotNull($legend);
                $this->assertStringContainsString(__('workflow_stage_types.picker_groups.'.$group), $legend->textContent);
                $inputs = $xpath->query('//fieldset[@data-stage-type-button-group="'.$group.'"]//input[@type="checkbox"]');
                $this->assertSame($expected, array_map(fn ($input): string => $input->getAttribute('value'), iterator_to_array($inputs)));
                foreach ($inputs as $input) {
                    $this->assertSame('data.enabled_actions', $input->getAttribute('wire:model.live'));
                    $this->assertFalse($input->hasAttribute('disabled'));
                }
            }
            $component->set('data.enabled_actions', ['accept']);
            $afterSelection = $this->actionField($component);
            foreach ($afterSelection->getOptions() as $value => $label) {
                $this->assertFalse($afterSelection->isOptionDisabled($value, $label), $value);
            }
            $checked = $this->renderedButtons($component)->query('//fieldset[@data-stage-type-button-group]//input[@checked]');
            $this->assertSame(['accept'], array_map(fn ($input): string => $input->getAttribute('value'), iterator_to_array($checked)));
        }
    }

    public function test_each_action_group_infers_its_existing_canonical_behavior_and_saves_without_a_base_selection(): void
    {
        $groups = ['form_submission' => ['submitted'], 'review' => ['accept', 'reject'], 'payment' => ['paid'], 'awaiting_results' => ['passed'], 'completed' => ['notify']];
        foreach ($groups as $behavior => $actions) {
            $this->assertSame($behavior, WorkflowStageTypeSettings::behaviorForSelection($actions));
            Livewire::test(CreateWorkflowStageType::class)
                ->fillForm(['label_en' => 'Global '.$behavior, 'label_km' => 'ខ្មែរ '.$behavior, 'is_active' => true])
                ->set('data.enabled_actions', $actions)->assertSet('data.behavior', $behavior)->assertSet('data.enabled_actions', $actions)
                ->call('create')->assertHasNoFormErrors();
            $type = WorkflowStageType::query()->where('label_en', 'Global '.$behavior)->firstOrFail();
            $this->assertSame($behavior, $type->behavior);
            $this->assertSame($actions, $type->enabled_actions);
            $this->assertEmpty($type->review_actions);
            $this->assertEmpty($type->stage_settings);
        }
    }

    public function test_changing_actions_in_the_same_group_preserves_entered_labels_and_active_state(): void
    {
        $component = Livewire::test(CreateWorkflowStageType::class)
            ->fillForm(['label_en' => 'My configured review', 'label_km' => 'ការពិនិត្យរបស់ខ្ញុំ', 'is_active' => false])
            ->set('data.enabled_actions', ['accept'])
            ->set('data.enabled_actions', ['accept', 'reject'])
            ->set('data.enabled_actions', ['reject'])->set('data.enabled_actions', ['accept', 'reject'])
            ->assertSet('data.behavior', 'review')
            ->assertSet('data.label_en', 'My configured review')->assertSet('data.label_km', 'ការពិនិត្យរបស់ខ្ញុំ')
            ->assertSet('data.is_active', false)
            ->call('create')->assertHasNoFormErrors();
        $type = WorkflowStageType::query()->where('label_en', 'My configured review')->firstOrFail();
        $this->assertSame(['accept', 'reject'], $type->enabled_actions);
        $this->assertFalse($type->is_active);
        $this->assertSame(self::ACTIONS, array_keys($this->actionField($component)->getOptions()));
    }

    public function test_mixed_empty_unknown_and_status_selections_fail_without_being_silently_truncated(): void
    {
        $cases = [[['accept', 'paid'], null], [[], null], [['unknown'], null], [self::ACTIONS, null], [['unpaid'], 'payment'], [['pending'], 'awaiting_results']];
        foreach ($cases as $index => [$actions, $canonicalBehavior]) {
            $this->assertSame($canonicalBehavior, WorkflowStageTypeSettings::behaviorForSelection($actions));
            $component = Livewire::test(CreateWorkflowStageType::class)
                ->fillForm(['label_en' => 'Invalid global '.$index, 'label_km' => 'មិនត្រឹមត្រូវ '.$index])
                ->set('data.enabled_actions', $actions)->call('create')->assertHasFormErrors();
            $component->assertSet('data.enabled_actions', $actions);
            $this->assertNotEmpty(array_filter($component->errors()->keys(), fn (string $key): bool => str_starts_with($key, 'data.enabled_actions')));
            $this->assertSame(self::ACTIONS, array_keys($this->actionField($component)->getOptions()));
            $this->assertDatabaseMissing('workflow_stage_types', ['label_en' => 'Invalid global '.$index]);
        }
    }

    public function test_edit_rejects_a_different_action_group_and_leaves_the_existing_definition_unchanged(): void
    {
        $type = WorkflowStageType::query()->create(['label_en' => 'Immutable review', 'label_km' => 'ការពិនិត្យមិនអាចប្តូរ', 'behavior' => 'review', 'enabled_actions' => ['accept'], 'review_actions' => ['accept' => ['status_message' => 'Original checked wording']]])->fresh();
        $before = $type->getRawOriginal();
        $component = Livewire::test(EditWorkflowStageType::class, ['record' => $type->getRouteKey()])
            ->assertFormFieldHidden('behavior')->assertFormFieldDisabled('behavior');
        $field = $this->actionField($component);
        foreach ($field->getOptions() as $value => $label) {
            $this->assertSame(! in_array($value, ['accept', 'send_back', 'reject'], true), $field->isOptionDisabled($value, $label), $value);
        }
        $inputs = $this->renderedButtons($component)->query('//fieldset[@data-stage-type-button-group]//input[@type="checkbox"]');
        $this->assertSame(8, $inputs->length);
        foreach ($inputs as $input) {
            $this->assertSame(! in_array($input->getAttribute('value'), ['accept', 'send_back', 'reject'], true), $input->hasAttribute('disabled'), $input->getAttribute('value'));
            $this->assertSame($input->getAttribute('value') === 'accept', $input->hasAttribute('checked'), $input->getAttribute('value'));
        }
        $component->set('data.enabled_actions', ['paid'])->assertSet('data.behavior', 'review')->call('save')->assertHasFormErrors();
        $component->assertSet('data.enabled_actions', ['paid']);
        $this->assertSame($before, $type->fresh()->getRawOriginal());
        $this->assertSame(self::ACTIONS, array_keys($this->actionField($component)->getOptions()));
    }

    public function test_create_guard_derives_behavior_from_actions_and_new_names_appear_in_workflow_options(): void
    {
        $component = Livewire::test(CreateWorkflowStageType::class);
        $payload = (new ReflectionMethod(CreateWorkflowStageType::class, 'mutateFormDataBeforeCreate'))->invoke($component->instance(), ['behavior' => 'review', 'enabled_actions' => ['paid'], 'review_actions' => ['accept' => ['status_message' => 'Unused posted review settings']], 'stage_settings' => ['status_message' => 'Unused posted stage settings']]);
        $this->assertSame('payment', $payload['behavior']);
        $this->assertArrayNotHasKey('review_actions', $payload);
        $this->assertArrayNotHasKey('stage_settings', $payload);
        $component->fillForm(['label_en' => 'New payment name', 'label_km' => 'ឈ្មោះការបង់ប្រាក់ថ្មី', 'is_active' => true])
            ->set('data.enabled_actions', ['paid'])->call('create')->assertHasNoFormErrors();
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

    public function test_creating_a_definition_from_global_actions_does_not_change_legacy_workflows_or_application_snapshots(): void
    {
        $workflow = WorkflowNotification::query()->create(['name' => 'Legacy unchanged workflow', 'stages' => [['type' => 'stage', 'data' => ['stage_name' => 'Legacy review', 'stage_type' => 'review', 'responsible_role' => ['registrar_officer']]]]]);
        $form = CustomForm::query()->create(['name' => 'Legacy global checklist application', 'slug' => 'legacy-global-checklist-application', 'is_active' => true]);
        $workflow->forms()->attach($form);
        $entry = CustomFormEntry::query()->forceCreate(['custom_form_id' => $form->getKey(), 'review_status' => 'pending', 'data' => ['registration_status' => 'pending']]);
        $workflowBefore = $workflow->getRawOriginal('stages');
        $entryBefore = $entry->fresh()->getRawOriginal();
        Livewire::test(CreateWorkflowStageType::class)
            ->fillForm(['label_en' => 'Independent paid action', 'label_km' => 'សកម្មភាពបង់ប្រាក់ឯករាជ្យ'])
            ->set('data.enabled_actions', ['paid'])->call('create')->assertHasNoFormErrors();
        $this->assertSame($workflowBefore, $workflow->fresh()->getRawOriginal('stages'));
        $this->assertSame($entryBefore, $entry->fresh()->getRawOriginal());
        $this->assertArrayNotHasKey('enabled_actions', $workflow->fresh()->stages[0]['data']);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    private function renderedButtons($component): DOMXPath
    {
        $document = new DOMDocument;
        $this->assertTrue($document->loadHTML('<meta charset="UTF-8">'.$component->html(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING));

        return new DOMXPath($document);
    }

    private function actionField($component): CheckboxList
    {
        $field = $component->instance()->form->getFlatFields(withHidden: true)['enabled_actions'];
        $this->assertInstanceOf(CheckboxList::class, $field);

        return $field;
    }
}
