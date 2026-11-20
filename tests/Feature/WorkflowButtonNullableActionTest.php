<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType as StageBehavior;
use App\Filament\Admin\Resources\WorkflowButtons\Pages\CreateWorkflowButton;
use App\Filament\Admin\Resources\WorkflowButtons\Pages\EditWorkflowButton;
use App\Filament\Admin\Resources\WorkflowButtons\Pages\ListWorkflowButtons;
use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\CreateWorkflowStageType;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowButton;
use App\Models\WorkflowNotification;
use App\Models\WorkflowStageType;
use App\Support\WorkflowButtons;
use App\Support\WorkflowStageProgression;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkflowButtonNullableActionTest extends TestCase
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
            'registration_type' => 'admin', 'name' => 'Nullable button admin',
            'username' => 'nullable-button-admin', 'email' => 'nullable-button-admin@example.test',
            'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true,
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
    public function test_blank_null_and_omitted_actions_create_a_visible_button_preview(string $locale): void
    {
        app()->setLocale($locale);
        foreach ([[], ['action_key' => null], ['action_key' => '']] as $index => $selection) {
            $label = 'Display only '.$locale.' '.$index;
            $component = Livewire::test(CreateWorkflowButton::class);
            $this->assertFalse($component->instance()->form->getFlatFields()['action_key']->isRequired());
            $component->fillForm([
                'label_en' => $label, 'label_km' => 'ប៊ូតុងបង្ហាញ '.$index,
                'icon' => 'heroicon-o-pencil-square', 'color' => 'info', 'is_active' => true,
                ...$selection,
            ])->call('create')->assertHasNoFormErrors();

            $button = WorkflowButton::query()->where('label_en', $label)->sole();
            $this->assertNull($button->action_key);
            $this->assertNull($button->behavior());
            $list = Livewire::test(ListWorkflowButtons::class)
                ->searchTable($label)
                ->assertCanSeeTableRecords([$button])
                ->assertTableColumnStateSet('label', $button->label(), $button)
                ->assertTableColumnStateSet('action_key', null, $button)
                ->assertSee(__('workflow_buttons.states.no_action'));
            $preview = $list->instance()->getTable()->getColumn('label')->record($button);
            $this->assertSame('heroicon-o-pencil-square', $preview->getIcon($button->label()));
            $this->assertSame('info', $preview->getColor($button->label()));
        }
        $this->assertNoMessages();
    }

    public function test_direct_model_creation_can_omit_the_action_and_an_unused_action_can_be_cleared(): void
    {
        $withoutAction = WorkflowButton::query()->create(['label_en' => 'Omitted model action']);
        $this->assertNull($withoutAction->fresh()->action_key);

        foreach ([null, ''] as $index => $selection) {
            $button = WorkflowButton::query()->create(['label_en' => 'Clear unused action '.$index, 'action_key' => 'accept']);
            Livewire::test(EditWorkflowButton::class, ['record' => $button->getRouteKey()])
                ->fillForm(['action_key' => $selection])->call('save')->assertHasNoFormErrors();
            $this->assertNull($button->fresh()->action_key);
            $this->assertNull($button->fresh()->behavior());
        }
        $this->assertNoMessages();
    }

    public function test_buttons_without_actions_can_be_selected_alone_or_with_a_real_action(): void
    {
        $inert = WorkflowButton::query()->create(['label_en' => 'Display only option', 'action_key' => null]);
        $actionable = WorkflowButton::query()->create(['label_en' => 'Real action option', 'action_key' => 'accept']);
        $this->assertArrayHasKey($actionable->getKey(), WorkflowButton::selectOptions());
        $this->assertArrayHasKey($inert->getKey(), WorkflowButton::selectOptions());
        $this->assertArrayHasKey($inert->getKey(), WorkflowButton::selectOptions([$inert->getKey()]));

        foreach ([[$inert->getKey()], [$actionable->getKey(), $inert->getKey()]] as $index => $selection) {
            $name = 'Display only button stage '.$index;
            Livewire::test(CreateWorkflowStageType::class)
                ->fillForm(['label_en' => $name, 'label_km' => 'ប៊ូតុងគ្មានសកម្មភាព '.$index, 'is_active' => true])
                ->set('data.workflow_button_ids', $selection)->call('create')->assertHasNoFormErrors();
            $type = WorkflowStageType::query()->where('label_en', $name)->sole();
            $this->assertSame($selection, $type->workflow_button_ids);
            $this->assertSame($index === 0 ? null : 'review', $type->behavior);
            $this->assertSame($index === 0 ? [] : ['accept'], $type->enabled_actions);
        }
        $this->assertNoMessages();
    }

    public function test_an_action_already_used_by_a_stage_cannot_be_cleared(): void
    {
        $button = WorkflowButton::query()->create(['label_en' => 'Protected real action', 'action_key' => 'accept']);
        $type = $this->type($button);
        Livewire::test(EditWorkflowButton::class, ['record' => $button->getRouteKey()])->assertFormFieldDisabled('action_key');
        foreach ([null, ''] as $selection) {
            try {
                $button->refresh()->update(['action_key' => $selection]);
                $this->fail('Referenced workflow actions must remain fixed.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('action_key', $exception->errors());
            }
            $this->assertSame('accept', $button->fresh()->action_key);
            $this->assertSame(['accept'], $type->fresh()->enabled_actions);
        }
    }

    public function test_creating_and_editing_a_display_only_button_leaves_workflows_applications_and_notifications_untouched(): void
    {
        $real = WorkflowButton::query()->create(['label_en' => 'Frozen real action', 'action_key' => 'accept']);
        $type = $this->type($real);
        $workflow = WorkflowNotification::query()->create(['name' => 'Frozen nullable action workflow', 'stages' => [[
            'type' => 'stage', 'data' => ['stage_type_id' => $type->getKey(), 'stage_type' => 'review', 'stage_name' => 'Review', 'responsible_role' => ['admin']],
        ]]]);
        $form = CustomForm::query()->create(['name' => 'Nullable action form', 'slug' => 'nullable-action-form', 'is_active' => true]);
        $workflow->forms()->attach($form);
        $entry = CustomFormEntry::query()->forceCreate(['custom_form_id' => $form->getKey(), 'review_status' => 'pending', 'data' => ['registration_status' => 'pending']]);
        $before = [$type->fresh()->getRawOriginal(), $workflow->fresh()->getRawOriginal(), $entry->fresh()->getRawOriginal()];
        $notificationCount = DB::table('notifications')->count();
        $deliveryCount = DB::table('workflow_notification_deliveries')->count();

        Livewire::test(CreateWorkflowButton::class)->fillForm(['label_en' => 'Inert catalog preview', 'action_key' => null])->call('create')->assertHasNoFormErrors();
        $inert = WorkflowButton::query()->where('label_en', 'Inert catalog preview')->sole();
        Livewire::test(EditWorkflowButton::class, ['record' => $inert->getRouteKey()])
            ->fillForm(['label_en' => 'Renamed inert preview', 'color' => 'danger', 'is_active' => false])
            ->call('save')->assertHasNoFormErrors();

        $this->assertNull($inert->fresh()->action_key);
        $this->assertSame($before, [$type->fresh()->getRawOriginal(), $workflow->fresh()->getRawOriginal(), $entry->fresh()->getRawOriginal()]);
        $this->assertSame(StageBehavior::Review, WorkflowStageProgression::currentType($entry->fresh()));
        $this->assertTrue(WorkflowButtons::allows($entry, 'accepted', StageBehavior::Review));
        $this->assertFalse(WorkflowButtons::allows($entry, 'reject', StageBehavior::Review));
        $this->assertSame('Frozen real action', WorkflowButtons::label($entry, 'accepted', 'Fallback'));
        $this->assertSame($notificationCount, DB::table('notifications')->count());
        $this->assertSame($deliveryCount, DB::table('workflow_notification_deliveries')->count());
        $this->assertNoMessages();
    }

    private function type(WorkflowButton $button): WorkflowStageType
    {
        return WorkflowStageType::query()->create(['label_en' => 'Protected action type', 'label_km' => 'ប្រភេទសកម្មភាព', 'behavior' => 'review', 'workflow_button_ids' => [$button->getKey()]]);
    }

    private function assertNoMessages(): void
    {
        Notification::assertNothingSent();
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }
}
