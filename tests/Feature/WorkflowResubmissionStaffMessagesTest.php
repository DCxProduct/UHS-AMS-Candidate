<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\EditWorkflowNotification;
use App\Models\ClosingDate;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Support\NotificationLanguage;
use App\Support\UserTypeOptions;
use App\Support\WorkflowResubmissionStaffMessages;
use App\Support\WorkflowStageProgression;
use Carbon\Carbon;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\EditCustomFormEntry;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkflowResubmissionStaffMessagesTest extends TestCase
{
    use RefreshDatabase;

    private const TYPE = 'national_entrance_exam_application_bachelor';

    private User $candidate;

    private User $admin;

    private User $initialStaff;

    private User $resubmissionStaff;

    private CustomForm $profile;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        $this->travelTo(Carbon::parse('2026-11-06 09:00:00'));
        Http::preventStrayRequests();
        Http::fake();
        Mail::fake();
        UserTypeOptions::allQuery()->get();
        $this->admin = $this->user('resubmission-admin', 'admin');
        $this->admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->initialStaff = $this->user('initial-staff', 'admin');
        $this->initialStaff->assignRole(Role::findOrCreate('initial_submission_reviewers', 'web'));
        $this->resubmissionStaff = $this->user('resubmission-staff', 'admin');
        $this->resubmissionStaff->assignRole(Role::findOrCreate('correction_reviewers', 'web'));
        $this->candidate = $this->user('candidate-dara', 'student');
        $base = Role::findOrCreate('candidate', 'web');
        foreach (['ViewAny', 'View', 'Create', 'Update'] as $action) {
            $base->givePermissionTo(Permission::findOrCreate($action.':CustomFormEntry', 'web'));
        }
        $this->candidate->assignRole($base, Role::findOrCreate(self::TYPE, 'web'));
        $this->actingAs($this->candidate);
        $this->profile = CustomForm::query()->create(['name' => 'Profile', 'slug' => 'profile', 'is_active' => true]);
        CustomFormEntry::query()->forceCreate(['custom_form_id' => $this->profile->id, 'created_by' => $this->candidate->id, 'review_status' => 'pending', 'data' => ['registration_status' => 'pending']]);
    }

    public static function workflowVersions(): array
    {
        return ['legacy workflow' => [1], 'ordered workflow' => [2]];
    }

    #[DataProvider('workflowVersions')]
    public function test_native_configured_resubmissions_send_only_the_frozen_staff_alert_once_per_server_event(int $version): void
    {
        [$form, $workflow] = $this->workflow();
        $settings = ['title' => 'Recheck :candidate', 'message' => ':candidate corrected :form', 'staff_recipient_roles' => ['correction_reviewers']];
        $this->configure($workflow, $settings);
        $entry = $this->entry($form, version: $version);
        $snapshot = $entry->workflow_snapshot;
        $initial = DB::table('notifications')->get()->keyBy('id')->map(fn ($row): array => (array) $row)->all();
        $this->assertSame(1, $this->candidate->notifications()->count());
        $this->assertSame('Initial candidate confirmation', $this->candidate->notifications()->sole()->data['body']);
        $this->assertSame('Initial staff alert', $this->initialStaff->notifications()->sole()->data['body']);
        $this->assertSame(0, $this->resubmissionStaff->notifications()->count());
        $this->configure($workflow, ['title' => 'Changed title', 'message' => 'Changed message', 'staff_recipient_roles' => ['admin']]);
        $events = [];

        foreach ([1, 2] as $cycle) {
            $this->actingAs($this->admin)->post(route('admin.custom-form-entries.reject', $entry), ['review_note' => 'Correction '.$cycle])->assertRedirect();
            $this->assertSame('rejected', $entry->fresh()->review_status);
            $this->saveAsCandidate($entry, 'Corrected answer '.$cycle);
            $entry->refresh();
            $this->assertSame('pending', $entry->review_status);
            $this->assertSame('Corrected answer '.$cycle, $entry->data['correction']);
            $this->assertSame($snapshot, $entry->workflow_snapshot);
            if ($version === 2) {
                $this->assertSame(WorkflowStageType::Review, WorkflowStageProgression::currentType($entry));
            }
            $alerts = $this->resubmissionStaff->notifications()->get();
            $this->assertCount($cycle, $alerts);
            $alert = $alerts->first(fn ($notification): bool => data_get($notification->data, 'viewData.registrar_review_note') === 'Correction '.$cycle);
            $this->assertNotNull($alert);
            $this->assertSame('Recheck Application Dara', $alert->data['title']);
            $this->assertSame('Application Dara corrected Degree application', $alert->data['body']);
            $this->assertSame((string) $entry->id, data_get($alert->data, 'viewData.resubmission_entry_id'));
            $this->assertSame('resubmission_staff', data_get($alert->data, 'viewData.workflow_event'));
            $event = data_get($alert->data, 'viewData.resubmission_event_id');
            $this->assertTrue(Str::isUuid($event));
            $events[] = $event;
            $beforeRetry = $entry->getRawOriginal();
            $this->assertSame(0, WorkflowResubmissionStaffMessages::notifyConfigured($entry, $event));
            $this->assertSame(0, WorkflowResubmissionStaffMessages::notifyConfigured($entry, $event));
            $this->assertSame($beforeRetry, $entry->fresh()->getRawOriginal());
        }
        $this->assertNotSame($events[0], $events[1]);
        foreach ($initial as $id => $row) {
            $this->assertSame($row, (array) DB::table('notifications')->find($id));
        }
        $this->assertSame(1, $this->candidate->notifications()->count());
        $this->assertSame(1, $this->initialStaff->notifications()->count());
        $this->assertSame(0, $this->admin->notifications()->count());
        $this->assertArrayNotHasKey('workflow_resubmission_id', $entry->fresh()->data);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_an_application_started_without_a_template_keeps_the_legacy_static_staff_alert(): void
    {
        [$form, $workflow] = $this->workflow(initialMessages: false);
        $workflow->forms()->detach($form);
        $this->resubmissionStaff->forceFill(['locale' => 'km'])->save();
        $entry = $this->entry($form, 'rejected');
        $entry->forceFill(['review_note' => 'Static correction'])->save();
        $this->assertFalse($entry->workflow_snapshot['has_workflow']);
        $this->assertNull(WorkflowNotification::forEntry($entry));

        $this->saveAsCandidate($entry);

        foreach ([$this->admin, $this->initialStaff, $this->resubmissionStaff] as $staff) {
            $alert = $staff->notifications()->sole();
            $this->assertSame(NotificationLanguage::transForUser($staff, 'candidate_entrance_statistics.notifications.admin_resubmitted_title'), $alert->data['title']);
            $this->assertSame(NotificationLanguage::transForUser($staff, 'candidate_entrance_statistics.notifications.admin_resubmitted_body', ['student' => $this->candidate->name, 'form' => $form->display_name]), $alert->data['body']);
            $this->assertSame((string) $entry->id, data_get($alert->data, 'viewData.resubmission_entry_id'));
            $this->assertSame('Static correction', data_get($alert->data, 'viewData.registrar_review_note'));
        }
        $this->assertSame(0, $this->candidate->notifications()->count());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public static function silentTemplateConfigurations(): array
    {
        return [
            'missing settings' => ['missing'],
            'null settings' => ['null'],
            'all blank settings' => ['blank'],
            'template without submission stage' => ['no_submission'],
        ];
    }

    #[DataProvider('silentTemplateConfigurations')]
    public function test_connected_templates_without_resubmission_messages_never_use_the_static_alert(string $configuration): void
    {
        [$form, $workflow] = $this->workflow(initialMessages: false);
        if ($configuration === 'blank') {
            $this->configure($workflow, ['title' => '', 'message' => '', 'staff_recipient_roles' => []]);
        } elseif ($configuration === 'null') {
            $stages = $workflow->stages;
            $stages[0]['data']['submission_messages']['resubmission_staff'] = null;
            DB::table('workflow_notifications')->where('id', $workflow->id)->update(['stages' => json_encode($stages)]);
        } elseif ($configuration === 'no_submission') {
            $workflow->update(['stages' => array_slice($workflow->stages, 1)]);
        }
        $entry = $this->entry($form);
        $this->assertTrue($entry->workflow_snapshot['has_workflow']);
        $this->actingAs($this->admin)->post(route('admin.custom-form-entries.reject', $entry), ['review_note' => 'Silent template correction'])->assertRedirect();

        $this->saveAsCandidate($entry);

        $this->assertSame(0, WorkflowResubmissionStaffMessages::notifyConfigured($entry, (string) Str::uuid()));
        $this->assertDatabaseCount('notifications', 0);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_a_blank_title_uses_the_localized_frozen_workflow_stage_title(): void
    {
        [$form, $workflow] = $this->workflow(initialMessages: false);
        $this->resubmissionStaff->forceFill(['locale' => 'km'])->save();
        $this->configure($workflow, ['title' => '', 'message' => 'Review :candidate again', 'staff_recipient_roles' => ['correction_reviewers']]);
        $stages = $workflow->fresh()->stages;
        $stages[0]['data']['stage_name'] = 'Original correction stage';
        $workflow->update(['stages' => $stages]);
        $entry = $this->entry($form);
        $stages[0]['data']['stage_name'] = 'Changed template stage';
        $workflow->update(['stages' => $stages]);
        $this->actingAs($this->admin)->post(route('admin.custom-form-entries.reject', $entry), ['review_note' => 'Dynamic title correction'])->assertRedirect();

        $this->saveAsCandidate($entry);

        $alert = $this->resubmissionStaff->notifications()->sole();
        $this->assertSame(NotificationLanguage::transForUser($this->resubmissionStaff, 'workflow_notifications.staff_notification_title', ['form' => $form->display_name, 'stage' => 'Original correction stage']), $alert->data['title']);
        $this->assertSame('Review Application Dara again', $alert->data['body']);
        $this->assertSame(0, $this->admin->notifications()->count());
        $this->assertSame(0, $this->initialStaff->notifications()->count());
        $this->assertSame(0, $this->candidate->notifications()->count());
    }

    public static function partialConfigurations(): array
    {
        return [
            'title only' => [['title' => 'Recheck :candidate', 'message' => '', 'staff_recipient_roles' => []]],
            'message only' => [['title' => '', 'message' => 'Please recheck :candidate', 'staff_recipient_roles' => []]],
            'roles only' => [['title' => '', 'message' => '', 'staff_recipient_roles' => ['correction_reviewers']]],
            'title and message without recipients' => [['title' => 'Recheck :candidate', 'message' => 'Please recheck :candidate', 'staff_recipient_roles' => []]],
        ];
    }

    #[DataProvider('partialConfigurations')]
    public function test_native_partial_settings_save_but_do_not_send_without_both_message_and_roles(array $settings): void
    {
        [$form, $workflow] = $this->workflow(initialMessages: false);
        $this->configure($workflow, $settings);
        $entry = $this->entry($form);
        $this->actingAs($this->admin)->post(route('admin.custom-form-entries.reject', $entry), ['review_note' => 'Partial settings correction'])->assertRedirect();

        $this->saveAsCandidate($entry);

        $this->assertSame(0, WorkflowResubmissionStaffMessages::notifyConfigured($entry, (string) Str::uuid()));
        $this->assertDatabaseCount('notifications', 0);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_native_settings_reject_invalid_roles_and_oversized_text_without_saving_invalid_configuration(): void
    {
        [, $workflow] = $this->workflow(initialMessages: false);
        $before = $workflow->getRawOriginal('stages');
        foreach ([
            [['title' => '', 'message' => '', 'staff_recipient_roles' => ['candidate']], ['staff_recipient_roles.0']],
            [['title' => str_repeat('T', 256), 'message' => '', 'staff_recipient_roles' => []], ['title']],
            [['title' => '', 'message' => str_repeat('M', 1001), 'staff_recipient_roles' => []], ['message']],
        ] as [$settings, $errors]) {
            $this->actingAs($this->admin);
            $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->id]);
            $key = array_key_first($component->get('data.stages'));
            $path = 'data.stages.'.$key.'.data.submission_messages.resubmission_staff';
            foreach ($settings as $field => $value) {
                $component->set($path.'.'.$field, $value);
            }
            $component->call('save')->assertHasErrors(array_map(fn (string $field): string => $path.'.'.$field, $errors));
            $this->assertSame($before, $workflow->fresh()->getRawOriginal('stages'));
        }
    }

    public function test_drafts_profiles_and_staff_edits_do_not_trigger_the_custom_candidate_resubmission_alert(): void
    {
        [$form, $workflow] = $this->workflow(initialMessages: false);
        $this->configure($workflow, ['title' => 'Correction alert', 'message' => 'A candidate corrected an answer', 'staff_recipient_roles' => ['correction_reviewers']]);
        $profileDraft = $this->entry($this->profile, 'draft');
        $this->saveAsCandidate($profileDraft);
        $draft = $this->entry($form, 'draft');
        $this->actingAs($this->candidate);
        Livewire::withQueryParams([])->test(EditCustomFormEntry::class, ['record' => $draft->id])
            ->set('data.data.correction', 'Still a draft')->callAction('save_draft')->assertHasNoFormErrors();
        $this->assertSame('draft', $draft->fresh()->review_status);
        $this->saveAsCandidate($draft);
        $returned = $this->entry($form, 'rejected');
        $this->actingAs($this->admin);
        Livewire::withQueryParams([])->test(EditCustomFormEntry::class, ['record' => $returned->id])
            ->set('data.data.correction', 'Staff edit')->call('save')->assertHasNoFormErrors();

        $this->assertDatabaseCount('notifications', 0);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    private function configure(WorkflowNotification $workflow, array $settings): void
    {
        $this->actingAs($this->admin);
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->id]);
        $key = array_key_first($component->get('data.stages'));
        $path = 'data.stages.'.$key.'.data.submission_messages.resubmission_staff';
        foreach ($settings as $field => $value) {
            $component->set($path.'.'.$field, $value);
        }
        $component->call('save')->assertHasNoFormErrors();
        $saved = data_get($workflow->fresh()->stages, '0.data.submission_messages.resubmission_staff');
        if (filled($settings['message'] ?? null)) {
            $this->assertSame($settings['message'], $saved['message']);
            $this->assertSame($settings['staff_recipient_roles'], (array) ($saved['staff_recipient_roles'] ?? []));
        }
    }

    private function workflow(bool $initialMessages = true): array
    {
        $form = CustomForm::query()->create(['name' => 'Degree application', 'slug' => 'resubmission-staff-form', 'is_active' => true, 'allowed_roles' => [self::TYPE]]);
        foreach (['correction', 'full_name_en'] as $field) {
            $form->fields()->create(['name' => $field, 'label' => $field, 'type' => 'text_input', 'required' => $field === 'correction']);
        }
        ClosingDate::query()->create(['name' => 'Open application', 'type' => ClosingDate::customFormTypeKey($form->id), 'status' => 'open', 'start_date' => '2026-11-01', 'end_date' => '2026-11-30']);
        $workflow = WorkflowNotification::query()->create(['name' => 'Staff resubmission workflow', 'stages' => [
            ['type' => 'stage', 'data' => ['stage_name' => 'Submit', 'stage_type' => 'form_submission', 'notification_channels' => ['system'], 'submission_messages' => [
                'candidate_notification_message' => $initialMessages ? 'Initial candidate confirmation' : '',
                'staff_notification_message' => $initialMessages ? 'Initial staff alert' : '', 'staff_recipient_roles' => ['initial_submission_reviewers'],
            ]]],
            ['type' => 'stage', 'data' => ['stage_name' => 'Review', 'stage_type' => 'review', 'responsible_role' => ['admin'], 'notification_channels' => []]],
            ['type' => 'stage', 'data' => ['stage_name' => 'Complete', 'stage_type' => 'completed', 'notification_channels' => []]],
        ]]);
        $workflow->forms()->attach($form);

        return [$form, $workflow];
    }

    private function entry(CustomForm $form, string $status = 'pending', int $version = 2): CustomFormEntry
    {
        $this->actingAs($this->candidate);
        $entry = CustomFormEntry::query()->forceCreate(['custom_form_id' => $form->id, 'created_by' => $this->candidate->id, 'review_status' => $status, 'data' => [
            'registration_status' => $status, 'correction' => 'Original answer', 'full_name_en' => 'Application Dara',
            ...($status === 'draft' ? [] : ['submitted_at' => now()->toDateTimeString()]),
        ]]);
        if ($version === 1) {
            $snapshot = [...$entry->workflow_snapshot, 'schema_version' => 1];
            $data = $entry->data;
            unset($data['workflow_progress']);
            DB::table('custom_form_entries')->where('id', $entry->id)->update(['workflow_snapshot' => json_encode($snapshot), 'data' => json_encode($data)]);
        }

        return $entry->refresh();
    }

    private function saveAsCandidate(CustomFormEntry $entry, string $answer = 'Corrected answer'): void
    {
        $this->actingAs($this->candidate);
        Livewire::withQueryParams([])->test(EditCustomFormEntry::class, ['record' => $entry->id])
            ->set('data.data.correction', $answer)->callAction(TestAction::make('save')->schemaComponent('form-actions', schema: 'content'))->assertHasNoFormErrors();
    }

    private function user(string $username, string $type): User
    {
        return User::query()->forceCreate(['name' => $type === 'student' ? 'Candidate Dara' : $username, 'username' => $username, 'email' => $username.'@example.test', 'registration_type' => $type, 'date_of_birth' => '2000-01-01', 'password' => 'password', 'is_active' => true, 'locale' => 'en']);
    }
}
