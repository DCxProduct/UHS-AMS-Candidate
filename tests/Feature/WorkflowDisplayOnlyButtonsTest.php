<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType as StageBehavior;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\CreateWorkflowNotification;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\EditWorkflowNotification;
use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\CreateWorkflowStageType;
use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\EditWorkflowStageType;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowButton;
use App\Models\WorkflowNotification;
use App\Models\WorkflowStageType;
use App\Support\WorkflowButtons;
use App\Support\WorkflowStageMessages;
use App\Support\WorkflowStageProgression;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\ListCustomFormEntries;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class WorkflowDisplayOnlyButtonsTest extends TestCase
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
        $admin = User::query()->create([
            'registration_type' => 'admin', 'name' => 'Display only admin', 'username' => 'display-only-admin',
            'email' => 'display-only-admin@example.test', 'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true,
        ]);
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public static function locales(): array
    {
        return [['en'], ['km']];
    }

    #[DataProvider('locales')]
    public function test_display_only_button_is_available_and_creates_an_inert_stage_in_both_languages(string $locale): void
    {
        app()->setLocale($locale);
        $button = $this->button('Display only '.$locale);
        $component = Livewire::test(CreateWorkflowStageType::class);
        $field = $component->instance()->form->getFlatFields()['workflow_button_ids'];
        $this->assertTrue($field->isRequired());
        $this->assertArrayHasKey($button->id, $field->getOptions());
        $component->fillForm(['label_en' => 'Display stage '.$locale, 'label_km' => 'ដំណាក់កាលបង្ហាញ '.$locale])
            ->set('data.workflow_button_ids', [$button->id])
            ->set('data.behavior', 'payment')
            ->set('data.enabled_actions', ['paid'])
            ->call('create')->assertHasNoFormErrors();

        $type = WorkflowStageType::query()->where('label_en', 'Display stage '.$locale)->sole();
        $this->assertNull($type->behavior);
        $this->assertSame([], $type->enabled_actions);
        $this->assertSame([], $type->enabledActionKeys());
        $this->assertSame([$button->id], $type->workflow_button_ids);
        $this->assertNull($type->buttonDefinitions()[0]['action_key']);
        Livewire::test(EditWorkflowStageType::class, ['record' => $type->getRouteKey()])
            ->set('data.label_en', 'Renamed display stage '.$locale)
            ->call('save')->assertHasNoFormErrors();
        $this->assertNull($type->fresh()->behavior);
        $this->assertSame([], $type->fresh()->enabled_actions);
        $this->assertNoMessages();
    }

    public function test_unused_custom_stage_can_switch_to_display_only_and_back_without_a_default_action(): void
    {
        $display = $this->button('Only a label');
        $check = $this->button('Real check', 'accept');
        $type = $this->type('Unused custom stage', [$check->id]);
        Livewire::test(EditWorkflowStageType::class, ['record' => $type->getRouteKey()])
            ->set('data.workflow_button_ids', [$display->id])->call('save')->assertHasNoFormErrors();
        $this->assertNull($type->fresh()->behavior);
        $this->assertSame([], $type->fresh()->enabled_actions);
        Livewire::test(EditWorkflowStageType::class, ['record' => $type->getRouteKey()])
            ->set('data.workflow_button_ids', [$check->id])->call('save')->assertHasNoFormErrors();
        $this->assertSame('review', $type->fresh()->behavior);
        $this->assertSame(['accept'], $type->fresh()->enabled_actions);
        $this->assertNoMessages();
    }

    public function test_multiple_display_buttons_do_not_change_the_real_action_group_or_duplicate_checks(): void
    {
        $first = $this->button('First display');
        $second = $this->button('Second display');
        $check = $this->button('Only execute check', 'accept');
        $type = $this->type('Mixed display and check', [$first->id, $check->id, $second->id]);
        $this->assertSame('review', $type->behavior);
        $this->assertSame(['accept'], $type->enabled_actions);
        $this->assertSame([null, 'accept', null], array_column($type->buttonDefinitions(), 'action_key'));

        $paid = $this->button('Other action group', 'paid');
        $duplicate = $this->button('Duplicate check action', 'accept');
        foreach ([[$first->id, $check->id, $paid->id], [$first->id, $check->id, $duplicate->id], [$first->id, $first->id]] as $index => $ids) {
            Livewire::test(CreateWorkflowStageType::class)
                ->fillForm(['label_en' => 'Invalid display mix '.$index, 'label_km' => 'ប៊ូតុងមិនត្រឹមត្រូវ '.$index])
                ->set('data.workflow_button_ids', $ids)->call('create')->assertHasFormErrors();
            $this->assertDatabaseMissing('workflow_stage_types', ['label_en' => 'Invalid display mix '.$index]);
        }
    }

    #[DataProvider('locales')]
    public function test_workflow_form_preserves_custom_selection_and_waits_for_an_explicit_click(string $locale): void
    {
        app()->setLocale($locale);
        $display = $this->button('Display workflow '.$locale);
        $type = $this->type('Inert workflow stage '.$locale, [$display->id]);
        $component = Livewire::test(CreateWorkflowNotification::class);
        $key = array_key_first($component->get('data.stages'));
        $path = 'data.stages.'.$key.'.data';
        $component->set($path.'.stage_type_id', WorkflowStageType::selectionForBehavior('review'))
            ->set($path.'.stage_type_id', $type->id)
            ->assertSet($path.'.stage_type', 'custom')
            ->assertSet($path.'.enabled_actions', [])
            ->set($path.'.stage_name', 'Display heading '.$locale)
            ->set('data.name', 'Inert workflow '.$locale)->call('create')->assertHasNoFormErrors();
        $workflow = WorkflowNotification::query()->where('name', 'Inert workflow '.$locale)->sole();
        $data = $workflow->stages[0]['data'];
        $this->assertSame('custom', $data['stage_type']);
        $this->assertSame($type->id, $data['stage_type_id']);
        $this->assertSame($display->id, $data['buttons'][0]['id']);
        $this->assertNull($data['buttons'][0]['action_key']);
        $this->assertSame($type->labels(), $data['stage_type_labels']);

        $edit = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $editKey = array_key_first($edit->get('data.stages'));
        $edit->assertSet('data.stages.'.$editKey.'.data.stage_type_id', $type->id)
            ->assertSet('data.stages.'.$editKey.'.data.buttons.0.action_key', null)
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame($data, $workflow->fresh()->stages[0]['data']);

        $entry = $this->entry($workflow, 'inert-'.$locale);
        $this->assertSame('custom', data_get($entry->workflow_snapshot, 'stages.0.data.stage_type'));
        $before = $entry->fresh()->getRawOriginal();
        $this->assertSame(StageBehavior::Custom, WorkflowStageProgression::currentType($entry));
        $this->assertNull(WorkflowStageProgression::completedAt($entry, 0));
        $this->assertFalse(WorkflowStageProgression::advance($entry));
        $this->assertFalse(WorkflowStageMessages::notifyConfiguredStage($entry, StageBehavior::Review));
        $this->assertSame($before, $entry->fresh()->getRawOriginal());
        $this->assertNoMessages();
    }

    public function test_mixed_workflow_freezes_display_buttons_without_expanding_real_actions_or_notifications(): void
    {
        $display = $this->button('Frozen display');
        $check = $this->button('Frozen real check', 'accept');
        $type = $this->type('Frozen mixed stage', [$display->id, $check->id]);
        $create = Livewire::test(CreateWorkflowNotification::class);
        $key = array_key_first($create->get('data.stages'));
        $create->set('data.stages.'.$key.'.data.stage_type_id', $type->id)
            ->set('data.stages.'.$key.'.data.stage_name', 'Frozen mixed review')
            ->set('data.stages.'.$key.'.data.responsible_role', ['admin'])
            ->set('data.name', 'Frozen mixed workflow')->call('create')->assertHasNoFormErrors();
        $workflow = WorkflowNotification::query()->where('name', 'Frozen mixed workflow')->sole();
        $entry = $this->entry($workflow, 'mixed');
        $before = $entry->fresh()->getRawOriginal();
        $stagesBefore = $workflow->getRawOriginal('stages');
        $this->assertSame(['accept'], $workflow->stages[0]['data']['enabled_actions']);
        $this->assertSame([null, 'accept'], array_column($entry->workflow_snapshot['stages'][0]['data']['buttons'], 'action_key'));
        $display->update(['label_en' => 'Edited display', 'color' => 'danger']);
        $check->update(['label_en' => 'Edited real check']);
        Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()])
            ->set('data.name', 'Renamed frozen mixed workflow')->call('save')->assertHasNoFormErrors();
        $this->assertSame($stagesBefore, $workflow->fresh()->getRawOriginal('stages'));
        $this->assertSame($before, $entry->fresh()->getRawOriginal());
        $this->assertSame(StageBehavior::Review, WorkflowStageProgression::currentType($entry));
        $this->assertTrue(WorkflowButtons::allows($entry, 'accepted', StageBehavior::Review));
        $this->assertFalse(WorkflowButtons::allows($entry, 'reject', StageBehavior::Review));
        $this->assertSame('Frozen real check', WorkflowButtons::label($entry, 'accepted', 'Fallback'));
        $list = Livewire::test(ListCustomFormEntries::class)->assertTableActionHidden('reject', $entry);
        $callback = $list->instance()->getTable()->getAction('reject')->getActionFunction();
        try {
            $callback($entry, ['review_note' => 'Must never run']);
            $this->fail('A display-only button must never grant a workflow action.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame($before, $entry->fresh()->getRawOriginal());
        $this->assertNoMessages();
    }

    public function test_used_stage_behavior_and_real_action_groups_remain_locked(): void
    {
        $check = $this->button('Locked check', 'accept');
        $paid = $this->button('Locked payment', 'paid');
        $display = $this->button('Locked display');
        $type = $this->type('Locked action stage', [$check->id]);
        $before = $type->fresh()->getRawOriginal();
        Livewire::test(EditWorkflowStageType::class, ['record' => $type->getRouteKey()])
            ->set('data.workflow_button_ids', [$paid->id])->call('save')->assertHasFormErrors();
        $this->assertSame($before, $type->fresh()->getRawOriginal());
        $this->workflow('Used action stage workflow', $type);
        Livewire::test(EditWorkflowStageType::class, ['record' => $type->getRouteKey()])
            ->set('data.workflow_button_ids', [$display->id])->call('save')->assertHasFormErrors();
        $this->assertSame($before, $type->fresh()->getRawOriginal());

        $inert = $this->type('Locked inert stage', [$display->id]);
        $this->workflow('Used inert stage workflow', $inert);
        $inertBefore = $inert->fresh()->getRawOriginal();
        Livewire::test(EditWorkflowStageType::class, ['record' => $inert->getRouteKey()])
            ->set('data.workflow_button_ids', [$check->id])->call('save')->assertHasFormErrors();
        $this->assertSame($inertBefore, $inert->fresh()->getRawOriginal());
    }

    private function button(string $label, ?string $action = null): WorkflowButton
    {
        return WorkflowButton::query()->create(['label_en' => $label, 'label_km' => 'ប៊ូតុង '.$label, 'action_key' => $action]);
    }

    private function type(string $label, array $ids): WorkflowStageType
    {
        return WorkflowStageType::query()->create(['label_en' => $label, 'label_km' => 'ដំណាក់កាល '.$label, 'workflow_button_ids' => $ids]);
    }

    private function workflow(string $label, WorkflowStageType $type): WorkflowNotification
    {
        return WorkflowNotification::query()->create(['name' => $label, 'stages' => [[
            'type' => 'stage', 'data' => ['stage_name' => $label, 'stage_type_id' => $type->id, 'stage_type' => $type->behavior, 'responsible_role' => ['admin']],
        ]]]);
    }

    private function entry(WorkflowNotification $workflow, string $suffix): CustomFormEntry
    {
        $form = CustomForm::query()->create(['name' => $suffix, 'slug' => 'display-only-'.$suffix, 'is_active' => true]);
        $workflow->forms()->attach($form);

        return CustomFormEntry::query()->forceCreate(['custom_form_id' => $form->id, 'review_status' => 'pending', 'data' => ['registration_status' => 'pending']])->fresh();
    }

    private function assertNoMessages(): void
    {
        Notification::assertNothingSent();
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }
}
