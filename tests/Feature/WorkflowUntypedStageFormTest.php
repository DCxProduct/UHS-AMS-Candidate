<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType as StageBehavior;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\CreateWorkflowNotification;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\EditWorkflowNotification;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowButton;
use App\Models\WorkflowNotification;
use App\Models\WorkflowStageType;
use App\Support\WorkflowStageMessages;
use App\Support\WorkflowStageProgression;
use App\Support\WorkflowSubmissionMessages;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkflowUntypedStageFormTest extends TestCase
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
        $admin = $this->user('untyped-stage-admin', 'admin');
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

    public static function configurations(): array
    {
        return [['en', false], ['km', false], ['en', true], ['km', true]];
    }

    #[DataProvider('locales')]
    public function test_initial_and_added_blank_stages_show_optional_message_fields(string $locale): void
    {
        app()->setLocale($locale);
        $component = Livewire::test(CreateWorkflowNotification::class);
        $first = array_key_first($component->get('data.stages'));
        $this->assertMessageFields($component, $first);
        $component->assertSet('data.stages.'.$first.'.data.stage_type', null);

        $component->callAction(TestAction::make('addStage')->schemaComponent('stages-section', schema: 'form'));
        $keys = array_keys($component->get('data.stages'));
        $this->assertCount(2, $keys);
        $this->assertMessageFields($component, $keys[1]);
        $component->assertSet('data.stages.'.$keys[1].'.data.stage_type', null);
        $component->assertSee(__('workflow_notifications.fields.notification_channels'))
            ->assertSee(__('workflow_notifications.sections.for_candidates'))
            ->assertSee(__('workflow_notifications.sections.for_system_users'));
        $this->assertNoMessages();
    }

    #[DataProvider('configurations')]
    public function test_blank_and_display_only_stages_preserve_message_settings_on_create_and_edit(string $locale, bool $displayOnly): void
    {
        app()->setLocale($locale);
        $name = 'Untyped message settings '.$locale.' '.(int) $displayOnly;
        $component = Livewire::test(CreateWorkflowNotification::class);
        $key = array_key_first($component->get('data.stages'));
        $path = 'data.stages.'.$key.'.data';
        if ($displayOnly) {
            $button = WorkflowButton::query()->create(['label_en' => 'Display only', 'label_km' => 'សម្រាប់បង្ហាញ', 'action_key' => null]);
            $type = WorkflowStageType::query()->create(['label_en' => 'Display stage', 'label_km' => 'ដំណាក់កាលបង្ហាញ', 'workflow_button_ids' => [$button->id]]);
            $component->set($path.'.stage_type_id', $type->id);
        }
        $this->assertMessageFields($component, $key);
        $messages = $this->messages();
        $component->set('data.name', $name)->set($path.'.stage_name', 'Named message draft')
            ->set($path.'.notification_channels', ['system', 'sms', 'email'])
            ->set($path.'.alert_message', 'Stored optional alert');
        foreach ($messages as $field => $value) {
            $component->set($path.'.submission_messages.'.$field, $value);
        }
        $component->call('create')->assertHasNoFormErrors();

        $workflow = WorkflowNotification::query()->where('name', $name)->sole();
        $data = $workflow->stages[0]['data'];
        $this->assertSame($displayOnly ? 'custom' : null, $data['stage_type']);
        $this->assertSame($messages, $data['submission_messages']);
        $this->assertSame(['system', 'sms', 'email'], $data['notification_channels']);
        $this->assertSame('Stored optional alert', $data['alert_message']);
        if ($displayOnly) {
            $this->assertSame($type->id, $data['stage_type_id']);
            $this->assertNull($data['buttons'][0]['action_key']);
        }

        $edit = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $editKey = array_key_first($edit->get('data.stages'));
        $editPath = 'data.stages.'.$editKey.'.data';
        $this->assertMessageFields($edit, $editKey);
        foreach ($messages as $field => $value) {
            $edit->assertSet($editPath.'.submission_messages.'.$field, $value);
        }
        $edit->set($editPath.'.submission_messages.candidate_status_message', 'Updated stored status')
            ->set($editPath.'.submission_messages.candidate_notification_message', 'Updated stored candidate message')
            ->set($editPath.'.submission_messages.staff_notification_message', 'Updated stored staff message')
            ->set($editPath.'.notification_channels', ['system'])
            ->set($editPath.'.alert_message', 'Updated stored alert')
            ->call('save')->assertHasNoFormErrors();
        $saved = $workflow->fresh()->stages[0]['data'];
        $this->assertSame($displayOnly ? 'custom' : null, $saved['stage_type']);
        $this->assertSame('Updated stored status', $saved['submission_messages']['candidate_status_message']);
        $this->assertSame('Updated stored candidate message', $saved['submission_messages']['candidate_notification_message']);
        $this->assertSame('Updated stored staff message', $saved['submission_messages']['staff_notification_message']);
        $this->assertSame(['admin'], $saved['submission_messages']['staff_recipient_roles']);
        $this->assertSame(['system'], $saved['notification_channels']);
        $this->assertSame('Updated stored alert', $saved['alert_message']);
        $this->assertNoMessages();
    }

    public function test_stored_messages_on_an_untyped_stage_cannot_execute_or_change_application_state(): void
    {
        $workflow = WorkflowNotification::query()->create(['name' => 'Inert message draft', 'stages' => [['type' => 'stage', 'data' => [
            'stage_name' => 'Draft with messages', 'stage_type' => null, 'stage_type_id' => null,
            'submission_messages' => $this->messages(), 'alert_message' => 'Never execute',
            'notification_channels' => ['system', 'sms', 'email'],
        ]]]]);
        $form = CustomForm::query()->create(['name' => 'Inert message form', 'slug' => 'inert-message-form', 'is_active' => true]);
        $workflow->forms()->attach($form);
        $candidate = $this->user('untyped-stage-candidate', 'student');
        $entry = CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $form->id, 'created_by' => $candidate->id, 'review_status' => 'pending',
            'data' => ['registration_status' => 'pending', 'submitted_at' => now()->toIso8601String()],
        ])->fresh();
        $before = $entry->getRawOriginal();
        $counts = [DB::table('notifications')->count(), DB::table('workflow_notification_deliveries')->count()];

        $this->assertSame([], $entry->workflow_snapshot['stages']);
        $this->assertNull(WorkflowStageProgression::currentType($entry));
        $this->assertFalse(WorkflowStageProgression::submitted($entry));
        $this->assertFalse(WorkflowStageProgression::advance($entry));
        $this->assertSame(0, WorkflowSubmissionMessages::notify($entry));
        $this->assertFalse(WorkflowStageMessages::notifyConfiguredStage($entry, StageBehavior::FormSubmission));
        $this->assertSame($before, $entry->fresh()->getRawOriginal());
        $this->assertSame($counts, [DB::table('notifications')->count(), DB::table('workflow_notification_deliveries')->count()]);
        $this->assertNoMessages();
    }

    public function test_real_submission_stage_keeps_its_existing_message_and_resubmission_fields(): void
    {
        $component = Livewire::test(CreateWorkflowNotification::class);
        $key = array_key_first($component->get('data.stages'));
        $component->set('data.stages.'.$key.'.data.stage_type_id', WorkflowStageType::selectionForBehavior('form_submission'));
        $this->assertMessageFields($component, $key, true);
        $component->assertSet('data.stages.'.$key.'.data.stage_type', 'form_submission');
    }

    private function assertMessageFields($component, string $key, bool $submission = false): void
    {
        $path = 'data.stages.'.$key.'.data.';
        $fields = collect($component->instance()->form->getFlatFields())->keyBy(fn ($field): string => $field->getStatePath());
        $this->assertTrue($fields->has($path.'stage_name'));
        $this->assertTrue($fields[$path.'stage_name']->isRequired());
        foreach (['notification_channels', 'alert_message', 'submission_messages.candidate_status_message', 'submission_messages.candidate_notification_message', 'submission_messages.staff_notification_message', 'submission_messages.staff_recipient_roles'] as $field) {
            $this->assertTrue($fields->has($path.$field), 'Expected visible optional field: '.$path.$field);
            $this->assertFalse($fields[$path.$field]->isRequired());
        }
        $this->assertSame($submission, $fields->has($path.'submission_messages.resubmission_staff.message'));
    }

    private function messages(): array
    {
        return [
            'candidate_status_message' => 'Stored candidate status',
            'candidate_notification_message' => 'Stored candidate message',
            'staff_notification_message' => ':candidate submitted :form',
            'staff_recipient_roles' => ['admin'],
        ];
    }

    private function assertNoMessages(): void
    {
        Notification::assertNothingSent();
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    private function user(string $name, string $type): User
    {
        return User::query()->create(['registration_type' => $type, 'name' => $name, 'username' => $name, 'email' => $name.'@example.test', 'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true]);
    }
}
