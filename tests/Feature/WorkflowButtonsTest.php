<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType as StageBehavior;
use App\Filament\Admin\Resources\WorkflowButtons\Pages\CreateWorkflowButton;
use App\Filament\Admin\Resources\WorkflowButtons\Pages\EditWorkflowButton;
use App\Filament\Admin\Resources\WorkflowButtons\Pages\ListWorkflowButtons;
use App\Filament\Admin\Resources\WorkflowButtons\WorkflowButtonResource;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\CreateWorkflowNotification;
use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\CreateWorkflowStageType;
use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\EditWorkflowStageType;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowButton;
use App\Models\WorkflowNotification;
use App\Models\WorkflowStageType;
use App\Support\FilamentActionPermissions;
use App\Support\WorkflowButtons;
use App\Support\WorkflowStageProgression;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\ListCustomFormEntries;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class WorkflowButtonsTest extends TestCase
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
        $admin = $this->user('workflow-buttons-admin');
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        Role::findOrCreate('registrar_officer', 'web');
        $this->actingAs($admin);
    }

    public function test_admin_can_create_and_edit_a_localized_button_entirely_through_the_form(): void
    {
        Livewire::test(CreateWorkflowButton::class)
            ->fillForm([
                'label_en' => 'Request edits',
                'label_km' => 'ស្នើសុំកែប្រែ',
                'action_key' => 'send_back',
                'icon' => 'heroicon-o-pencil-square',
                'color' => 'warning',
                'is_active' => true,
            ])
            ->call('create')->assertHasNoFormErrors();

        $button = WorkflowButton::query()->where('label_en', 'Request edits')->sole();
        $this->assertSame('send_back', $button->action_key);
        $this->assertSame('Request edits', $button->label());
        app()->setLocale('km');
        $this->assertSame('ស្នើសុំកែប្រែ', $button->label());
        app()->setLocale('en');

        Livewire::test(EditWorkflowButton::class, ['record' => $button->getRouteKey()])
            ->fillForm(['label_en' => 'Edit application', 'color' => 'info', 'is_active' => false])
            ->call('save')->assertHasNoFormErrors();

        $this->assertSame('Edit application', $button->fresh()->label_en);
        $this->assertSame('info', $button->fresh()->color);
        $this->assertFalse($button->fresh()->is_active);
        $this->assertArrayNotHasKey($button->getKey(), WorkflowButton::selectOptions());
        $this->assertArrayHasKey($button->getKey(), WorkflowButton::selectOptions([$button->getKey()]));
        Notification::assertNothingSent();
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_khmer_label_is_optional_and_english_is_used_as_its_fallback(): void
    {
        Livewire::test(CreateWorkflowButton::class)
            ->fillForm([
                'label_en' => 'Check application',
                'label_km' => null,
                'action_key' => 'accept',
                'icon' => 'heroicon-o-check-circle',
                'color' => 'success',
                'is_active' => true,
            ])
            ->call('create')->assertHasNoFormErrors();
        app()->setLocale('km');
        $this->assertSame('Check application', WorkflowButton::query()->where('label_en', 'Check application')->sole()->label());
    }

    public function test_staff_without_button_permissions_cannot_access_the_menu_or_direct_crud_urls(): void
    {
        $button = $this->button('Protected button');
        $staff = $this->user('workflow-buttons-staff');
        $staff->assignRole(Role::findOrCreate('button_viewer_without_permission', 'web'));
        $this->actingAs($staff);

        $this->assertFalse(WorkflowButtonResource::shouldRegisterNavigation());
        $this->get(WorkflowButtonResource::getUrl('index'))->assertForbidden();
        $this->get(WorkflowButtonResource::getUrl('create'))->assertForbidden();
        $this->get(WorkflowButtonResource::getUrl('edit', ['record' => $button]))->assertForbidden();
    }

    public function test_a_ui_created_stage_uses_custom_buttons_and_copies_them_into_a_new_workflow(): void
    {
        $edit = $this->button('Edit application', 'send_back');
        $check = $this->button('Check application', 'accept');
        Livewire::test(CreateWorkflowStageType::class)
            ->fillForm(['label_en' => 'Edit and check', 'label_km' => 'កែប្រែ និងពិនិត្យ', 'is_active' => true])
            ->set('data.workflow_button_ids', [$check->getKey(), $edit->getKey()])
            ->assertSet('data.behavior', 'review')
            ->call('create')->assertHasNoFormErrors();

        $type = WorkflowStageType::query()->where('label_en', 'Edit and check')->sole();
        $this->assertSame('review', $type->behavior);
        $this->assertEqualsCanonicalizing(['accept', 'send_back'], $type->enabledActionKeys());

        $component = Livewire::test(CreateWorkflowNotification::class);
        $key = array_key_first($component->get('data.stages'));
        $path = 'data.stages.'.$key.'.data';
        $component->set($path.'.stage_type_id', $type->getKey())
            ->set($path.'.responsible_role', ['registrar_officer'])
            ->set('data.name', 'UI custom button workflow')
            ->call('create')->assertHasNoFormErrors();

        $workflow = WorkflowNotification::query()->where('name', 'UI custom button workflow')->sole();
        $buttons = collect(data_get($workflow->stages, '0.data.buttons'))->keyBy('action_key');
        $this->assertSame('Edit application', $buttons['send_back']['label_en']);
        $this->assertSame($edit->getKey(), $buttons['send_back']['id']);
        $this->assertSame('Check application', $buttons['accept']['label_en']);
    }

    public function test_unknown_duplicate_and_mixed_behavior_button_selections_cannot_create_a_stage(): void
    {
        $check = $this->button('Primary check', 'accept');
        $otherCheck = $this->button('Other check', 'accept');
        $paid = $this->button('Record payment', 'paid');
        $cases = [
            [999999],
            [$check->getKey(), $check->getKey()],
            [$check->getKey(), $otherCheck->getKey()],
            [$check->getKey(), $paid->getKey()],
        ];

        foreach ($cases as $index => $selection) {
            Livewire::test(CreateWorkflowStageType::class)
                ->fillForm(['label_en' => 'Invalid button stage '.$index, 'label_km' => 'មិនត្រឹមត្រូវ '.$index, 'is_active' => true])
                ->set('data.workflow_button_ids', $selection)
                ->call('create')->assertHasFormErrors();
            $this->assertDatabaseMissing('workflow_stage_types', ['label_en' => 'Invalid button stage '.$index]);
        }
    }

    public function test_selecting_a_custom_button_stage_copies_its_configured_notification_defaults(): void
    {
        $button = $this->button('Check with defaults');
        $type = WorkflowStageType::query()->create([
            'label_en' => 'Custom default message stage', 'label_km' => 'ដំណាក់កាលសារដើម',
            'behavior' => 'review', 'workflow_button_ids' => [$button->getKey()],
            'review_actions' => ['accept' => ['status_message' => 'Checked by registrar', 'notification_message' => 'Your application has been checked.']],
        ]);
        $component = Livewire::test(CreateWorkflowNotification::class);
        $key = array_key_first($component->get('data.stages'));
        $path = 'data.stages.'.$key.'.data';

        $component->set($path.'.stage_type_id', $type->getKey())
            ->assertSet($path.'.review_actions.accept.status_message', 'Checked by registrar')
            ->assertSet($path.'.review_actions.accept.notification_message', 'Your application has been checked.');
    }

    public function test_button_changes_do_not_rewrite_saved_workflows_or_application_snapshots(): void
    {
        $button = $this->button('Original check', 'accept');
        $type = $this->type('Original check stage', [$button->getKey()]);
        $workflow = $this->workflow('Frozen button workflow', $type);
        $form = CustomForm::query()->create(['name' => 'Frozen button form', 'slug' => 'frozen-button-form', 'is_active' => true]);
        $workflow->forms()->attach($form);
        $entry = CustomFormEntry::query()->forceCreate(['custom_form_id' => $form->getKey(), 'review_status' => 'pending', 'data' => ['registration_status' => 'pending']]);
        $beforeStages = $workflow->getRawOriginal('stages');
        $beforeSnapshot = $entry->fresh()->workflow_snapshot;

        $button->update(['label_en' => 'Changed check', 'color' => 'danger', 'is_active' => false]);
        $workflow->update(['name' => 'Renamed frozen workflow']);

        $this->assertSame($beforeStages, $workflow->fresh()->getRawOriginal('stages'));
        $this->assertSame($beforeSnapshot, $entry->fresh()->workflow_snapshot);
        $this->assertSame('Original check', data_get($entry->fresh()->workflow_snapshot, 'stages.0.data.buttons.0.label_en'));
        Notification::assertNothingSent();
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_runtime_uses_frozen_labels_icons_and_colors_with_localized_fallback(): void
    {
        $button = $this->button('Original runtime check', attributes: ['icon' => 'heroicon-o-pencil-square', 'color' => 'info']);
        $workflow = $this->workflow('Runtime presentation workflow', $this->type('Runtime stage', [$button->getKey()]));
        $entry = $this->entry($workflow, 'runtime-presentation');
        $button->update(['label_en' => 'Changed runtime check', 'icon' => 'heroicon-o-banknotes', 'color' => 'danger']);

        $this->assertSame('Original runtime check', WorkflowButtons::label($entry, 'accepted', 'Fallback check'));
        $this->assertSame('heroicon-o-pencil-square', WorkflowButtons::icon($entry, 'accepted', 'heroicon-o-check-circle'));
        $this->assertSame('info', WorkflowButtons::color($entry, 'accepted', 'success'));
        app()->setLocale('km');
        $this->assertSame('Original runtime check', WorkflowButtons::label($entry, 'accepted', 'Fallback check'));

        app()->setLocale('en');
        $this->assertSame(StageBehavior::Review, WorkflowStageProgression::currentType($entry));
        $this->assertTrue(FilamentActionPermissions::canForWorkflowStage(CustomFormEntryResource::class, 'accepted', $entry, StageBehavior::Review));
        $component = Livewire::test(ListCustomFormEntries::class)->assertTableActionVisible('accepted', $entry);
        $action = $component->instance()->getTable()->getAction('accepted')->record($entry);
        $this->assertSame('Original runtime check', $action->getLabel());
        $this->assertSame('heroicon-o-pencil-square', $action->getIcon());
        $this->assertSame('info', $action->getColor());
    }

    public function test_disabled_buttons_are_hidden_and_their_callbacks_cannot_bypass_server_authorization(): void
    {
        $button = $this->button('Only allow check');
        $workflow = $this->workflow('Restricted runtime workflow', $this->type('Restricted runtime stage', [$button->getKey()]));
        $entry = $this->entry($workflow, 'restricted-runtime');
        $before = $entry->getRawOriginal();
        $this->assertTrue(WorkflowButtons::allows($entry, 'accepted', StageBehavior::Review));
        $this->assertFalse(WorkflowButtons::allows($entry, 'rejected', StageBehavior::Review));
        $this->assertFalse(WorkflowButtons::allows($entry, 'reject', StageBehavior::Review));
        $this->assertFalse(FilamentActionPermissions::canForWorkflowStage(CustomFormEntryResource::class, 'reject', $entry, StageBehavior::Review));

        $component = Livewire::test(ListCustomFormEntries::class)
            ->assertTableActionVisible('accepted', $entry)
            ->assertTableActionHidden('rejected', $entry)
            ->assertTableActionHidden('reject', $entry);
        $callback = $component->instance()->getTable()->getAction('reject')->getActionFunction();
        try {
            $callback($entry, ['review_note' => 'Forged hidden button']);
            $this->fail('A hidden workflow button must not be executable through its callback.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertSame($before, $entry->fresh()->getRawOriginal());
        Notification::assertNothingSent();
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_button_settings_are_validated_for_direct_model_writes_too(): void
    {
        foreach ([['action_key' => 'run_arbitrary_code'], ['color' => 'unsupported'], ['icon' => '<script>alert(1)</script>']] as $index => $invalid) {
            try {
                $this->button('Invalid button '.$index, attributes: $invalid);
                $this->fail('Invalid button configuration must not be saved.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
    }

    public function test_a_referenced_button_cannot_be_deleted_or_change_its_execution_behavior(): void
    {
        $button = $this->button('Referenced check');
        $type = $this->type('Referenced check stage', [$button->getKey()]);

        Livewire::test(ListWorkflowButtons::class)
            ->assertTableActionHidden('delete', $button)
            ->mountTableAction('delete', $button)->callMountedTableAction();
        $this->assertModelExists($button);
        Livewire::test(EditWorkflowButton::class, ['record' => $button->getRouteKey()])
            ->assertFormFieldDisabled('action_key');

        try {
            $button->update(['action_key' => 'paid']);
            $this->fail('An in-use button must keep its execution behavior.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }

        $this->assertSame('accept', $button->fresh()->action_key);
        $this->assertSame('review', $type->fresh()->behavior);
    }

    public function test_an_inactive_button_cannot_be_added_to_a_new_stage_but_existing_stage_can_be_reopened(): void
    {
        $button = $this->button('Disabled check');
        $existing = $this->type('Existing disabled check stage', [$button->getKey()]);
        $button->update(['is_active' => false]);

        Livewire::test(EditWorkflowStageType::class, ['record' => $existing->getRouteKey()])
            ->call('save')->assertHasNoFormErrors();
        $this->assertEquals([$button->getKey()], $existing->fresh()->workflow_button_ids);

        Livewire::test(CreateWorkflowStageType::class)
            ->fillForm(['label_en' => 'New inactive selection', 'label_km' => 'ជម្រើសថ្មី', 'is_active' => true])
            ->set('data.workflow_button_ids', [$button->getKey()])
            ->call('create')->assertHasFormErrors();
        $this->assertDatabaseMissing('workflow_stage_types', ['label_en' => 'New inactive selection']);
    }

    public function test_legacy_workflows_keep_their_existing_buttons_when_a_new_catalog_button_is_created(): void
    {
        $workflow = WorkflowNotification::query()->create(['name' => 'Legacy button workflow', 'stages' => [[
            'type' => 'stage',
            'data' => ['stage_type' => 'review', 'stage_name' => 'Review', 'responsible_role' => ['registrar_officer']],
        ]]]);
        $before = $workflow->getRawOriginal('stages');
        $this->button('New unrelated custom check');
        $workflow->update(['name' => 'Renamed legacy button workflow']);

        $this->assertSame($before, $workflow->fresh()->getRawOriginal('stages'));
        $this->assertArrayNotHasKey('buttons', $workflow->fresh()->stages[0]['data']);
    }

    private function button(string $label, string $action = 'accept', array $attributes = []): WorkflowButton
    {
        return WorkflowButton::query()->create([
            'label_en' => $label,
            'label_km' => null,
            'action_key' => $action,
            'icon' => 'heroicon-o-check-circle',
            'color' => 'success',
            'is_active' => true,
            ...$attributes,
        ]);
    }

    private function type(string $label, array $buttonIds): WorkflowStageType
    {
        return WorkflowStageType::query()->create([
            'label_en' => $label, 'label_km' => 'ខ្មែរ '.$label,
            'behavior' => 'review', 'workflow_button_ids' => $buttonIds,
        ]);
    }

    private function workflow(string $name, WorkflowStageType $type): WorkflowNotification
    {
        return WorkflowNotification::query()->create(['name' => $name, 'stages' => [[
            'type' => 'stage',
            'data' => ['stage_type_id' => $type->getKey(), 'stage_type' => $type->behavior, 'stage_name' => $type->label_en, 'responsible_role' => ['admin']],
        ]]]);
    }

    private function user(string $username): User
    {
        return User::query()->create([
            'name' => $username, 'username' => $username,
            'registration_type' => 'admin', 'email' => $username.'@example.test',
            'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true,
        ]);
    }

    private function entry(WorkflowNotification $workflow, string $slug): CustomFormEntry
    {
        $form = CustomForm::query()->create(['name' => $slug, 'slug' => $slug, 'is_active' => true]);
        $workflow->forms()->attach($form);
        DocumentTemplate::query()->where('type', 'custom_form_'.$form->getKey())->delete();

        return CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $form->getKey(), 'review_status' => 'pending',
            'data' => ['registration_status' => 'pending'],
        ]);
    }
}
