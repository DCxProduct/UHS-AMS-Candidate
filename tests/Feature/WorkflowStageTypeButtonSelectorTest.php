<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\CreateWorkflowStageType;
use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\EditWorkflowStageType;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowButton;
use App\Models\WorkflowStageType;
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
use Tests\TestCase;

class WorkflowStageTypeButtonSelectorTest extends TestCase
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
            'registration_type' => 'admin', 'name' => 'Button selector admin',
            'username' => 'button-selector-admin', 'email' => 'button-selector-admin@example.test',
            'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true,
        ]);
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
    }

    public function test_create_uses_only_the_custom_button_picker_in_english_and_khmer(): void
    {
        $button = $this->button('My custom check');
        foreach (['en', 'km'] as $locale) {
            app()->setLocale($locale);
            $component = Livewire::test(CreateWorkflowStageType::class)
                ->assertFormFieldVisible('workflow_button_ids')
                ->assertDontSee(__('workflow_stage_types.fields.enabled_actions'))
                ->assertDontSee(__('workflow_stage_types.picker_groups.candidate'))
                ->assertDontSee(__('workflow_stage_types.picker_groups.system'));

            $fields = $component->instance()->form->getFlatFields(withHidden: true);
            $this->assertInstanceOf(Hidden::class, $fields['enabled_actions']);
            $this->assertInstanceOf(Hidden::class, $fields['behavior']);
            $this->assertInstanceOf(Select::class, $fields['workflow_button_ids']);
            $this->assertTrue($fields['workflow_button_ids']->isMultiple());
            $this->assertTrue($fields['workflow_button_ids']->isRequired());
            $this->assertArrayHasKey($button->getKey(), $fields['workflow_button_ids']->getOptions());
            $document = new DOMDocument;
            $this->assertTrue($document->loadHTML('<meta charset="UTF-8">'.$component->html(), LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING));
            $this->assertSame(0, (new DOMXPath($document))->query('//fieldset[@data-stage-type-button-group]')->length);
        }
    }

    public function test_new_stage_requires_custom_buttons_even_if_hidden_values_are_posted_in_both_locales(): void
    {
        foreach (['en', 'km'] as $locale) {
            app()->setLocale($locale);
            foreach ([[], null] as $index => $selection) {
                $label = 'Missing selected buttons '.$locale.' '.$index;
                Livewire::test(CreateWorkflowStageType::class)
                    ->fillForm(['label_en' => $label, 'label_km' => 'ខ្វះប៊ូតុង '.$locale.' '.$index, 'is_active' => true])
                    ->set('data.workflow_button_ids', $selection)
                    ->set('data.enabled_actions', ['paid'])
                    ->set('data.behavior', 'payment')
                    ->call('create')->assertHasFormErrors(['workflow_button_ids']);

                $this->assertDatabaseMissing('workflow_stage_types', ['label_en' => $label]);
            }
        }
        Notification::assertNothingSent();
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_new_stage_cannot_select_then_clear_custom_buttons_before_saving(): void
    {
        $button = $this->button('Temporary payment selection', 'paid');
        Livewire::test(CreateWorkflowStageType::class)
            ->fillForm(['label_en' => 'Cleared new selection', 'label_km' => 'ជម្រើសថ្មីបានដកចេញ', 'is_active' => true])
            ->set('data.workflow_button_ids', [$button->getKey()])
            ->assertSet('data.behavior', 'payment')
            ->set('data.workflow_button_ids', [])
            ->call('create')->assertHasFormErrors(['workflow_button_ids']);

        $this->assertDatabaseMissing('workflow_stage_types', ['label_en' => 'Cleared new selection']);
        Notification::assertNothingSent();
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_selected_custom_buttons_control_the_saved_stage_instead_of_hidden_values(): void
    {
        $check = $this->button('Dynamic check');
        $edit = $this->button('Dynamic edit', 'send_back');
        Livewire::test(CreateWorkflowStageType::class)
            ->fillForm(['label_en' => 'Custom selector review', 'label_km' => 'ពិនិត្យតាមប៊ូតុង', 'is_active' => true])
            ->set('data.workflow_button_ids', [$edit->getKey(), $check->getKey()])
            ->set('data.enabled_actions', ['paid'])
            ->set('data.behavior', 'payment')
            ->call('create')->assertHasNoFormErrors();

        $saved = WorkflowStageType::query()->where('label_en', 'Custom selector review')->sole();
        $this->assertSame('review', $saved->behavior);
        $this->assertEqualsCanonicalizing([$edit->getKey(), $check->getKey()], $saved->workflow_button_ids);
        $this->assertSame(['accept', 'send_back'], $saved->enabled_actions);
        Notification::assertNothingSent();
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_existing_types_without_custom_buttons_require_selection_before_saving_in_both_locales(): void
    {
        $cases = [
            ['review', null, ['review_actions' => ['accept' => ['status_message' => 'Original checked status']]]],
            ['review', ['accept', 'reject'], ['review_actions' => ['reject' => ['notification_message' => 'Original rejection message']]]],
            ['payment', null, ['stage_settings' => ['status_message' => 'Original paid status']]],
            ['payment', ['unpaid'], ['stage_settings' => ['payment_statuses' => ['unpaid_status_message' => 'Original unpaid status']]]],
            ['awaiting_results', ['pending'], ['stage_settings' => ['result_statuses' => ['pending_status_message' => 'Original pending result status']]]],
        ];

        foreach (['en', 'km'] as $locale) {
            app()->setLocale($locale);
            foreach ($cases as $index => [$behavior, $selection, $settings]) {
                $type = WorkflowStageType::query()->create([
                    'label_en' => 'Legacy selector stage '.$locale.' '.$index, 'label_km' => 'ដំណាក់កាលចាស់ '.$locale.' '.$index,
                    'behavior' => $behavior, 'enabled_actions' => $selection, ...$settings,
                ]);
                $before = $type->fresh()->getRawOriginal();
                $component = Livewire::test(EditWorkflowStageType::class, ['record' => $type->getRouteKey()]);
                $fields = $component->instance()->form->getFlatFields(withHidden: true);
                $this->assertInstanceOf(Hidden::class, $fields['enabled_actions']);
                $this->assertTrue($fields['workflow_button_ids']->isRequired());
                $component->set('data.label_en', 'Renamed legacy selector stage '.$locale.' '.$index)
                    ->call('save')->assertHasFormErrors(['workflow_button_ids']);
                $this->assertSame($before, $type->fresh()->getRawOriginal());

                foreach ([[], null] as $emptySelection) {
                    Livewire::test(EditWorkflowStageType::class, ['record' => $type->getRouteKey()])
                        ->set('data.workflow_button_ids', $emptySelection)
                        ->call('save')->assertHasFormErrors(['workflow_button_ids']);
                    $this->assertSame($before, $type->fresh()->getRawOriginal());
                }
            }
        }
    }

    public function test_a_legacy_type_can_select_matching_custom_buttons_without_losing_its_messages(): void
    {
        $type = WorkflowStageType::query()->create([
            'label_en' => 'Legacy matching review', 'label_km' => 'ពិនិត្យចាស់',
            'behavior' => 'review', 'enabled_actions' => null,
            'review_actions' => ['accept' => ['notification_message' => 'Keep the original review message']],
        ]);
        $messages = $type->getRawOriginal('review_actions');
        $button = $this->button('Use my review button');
        Livewire::test(EditWorkflowStageType::class, ['record' => $type->getRouteKey()])
            ->set('data.workflow_button_ids', [$button->getKey()])
            ->call('save')->assertHasNoFormErrors();

        $this->assertSame('review', $type->fresh()->behavior);
        $this->assertEquals([$button->getKey()], $type->fresh()->workflow_button_ids);
        $this->assertSame(['accept'], $type->fresh()->enabled_actions);
        $this->assertSame($messages, $type->fresh()->getRawOriginal('review_actions'));
    }

    public function test_edit_rejects_custom_buttons_from_another_group_without_changing_the_saved_type(): void
    {
        $type = WorkflowStageType::query()->create([
            'label_en' => 'Fixed review group', 'label_km' => 'ក្រុមពិនិត្យ',
            'behavior' => 'review', 'enabled_actions' => ['accept'],
        ]);
        $before = $type->fresh()->getRawOriginal();
        $paid = $this->button('Different group payment', 'paid');
        Livewire::test(EditWorkflowStageType::class, ['record' => $type->getRouteKey()])
            ->set('data.workflow_button_ids', [$paid->getKey()])
            ->call('save')->assertHasFormErrors();

        $this->assertSame($before, $type->fresh()->getRawOriginal());
    }

    public function test_custom_button_selection_cannot_be_cleared_when_editing_in_both_locales(): void
    {
        $button = $this->button('Required custom button');
        foreach (['en', 'km'] as $locale) {
            app()->setLocale($locale);
            foreach ([[], null] as $index => $selection) {
                $type = WorkflowStageType::query()->create([
                    'label_en' => 'Existing custom selection '.$locale.' '.$index,
                    'label_km' => 'ជម្រើសប៊ូតុងមានស្រាប់ '.$locale.' '.$index,
                    'behavior' => 'review', 'workflow_button_ids' => [$button->getKey()],
                    'review_actions' => ['accept' => ['status_message' => 'Keep this configured status']],
                ]);
                $before = $type->fresh()->getRawOriginal();
                $component = Livewire::test(EditWorkflowStageType::class, ['record' => $type->getRouteKey()]);
                $this->assertTrue($component->instance()->form->getFlatFields(withHidden: true)['workflow_button_ids']->isRequired());
                $component->set('data.workflow_button_ids', $selection)
                    ->set('data.enabled_actions', ['paid'])
                    ->set('data.behavior', 'payment')
                    ->call('save')->assertHasFormErrors(['workflow_button_ids']);

                $this->assertSame($before, $type->fresh()->getRawOriginal());
                $this->assertSame(['accept'], $type->fresh()->enabled_actions);
            }
        }
        Notification::assertNothingSent();
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    private function button(string $label, string $action = 'accept'): WorkflowButton
    {
        return WorkflowButton::query()->create(['label_en' => $label, 'action_key' => $action]);
    }
}
