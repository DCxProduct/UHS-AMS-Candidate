<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Support\WorkflowStageMessages;
use App\Support\WorkflowSubmissionMessages;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\CreateCustomFormEntry;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\ListCustomFormEntries;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkflowSubmissionMessagesTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;

    private User $staff;

    private CustomForm $form;

    private WorkflowNotification $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        // Existing applications keep their saved status-driven workflow semantics.
        CustomFormEntry::creating(static function (CustomFormEntry $entry): void {
            if (is_array($entry->workflow_snapshot)) {
                $entry->workflow_snapshot = [...$entry->workflow_snapshot, 'schema_version' => 1];
            }
        });
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        $this->candidate = $this->user('submission_candidate', 'student');
        $this->staff = $this->user('submission_staff', 'admin');
        $this->form = CustomForm::query()->create([
            'name' => 'Submission Form',
            'slug' => 'submission-form',
            'is_active' => true,
            'allowed_roles' => ['candidate', 'admin'],
        ]);
        $this->workflow = WorkflowNotification::query()->create([
            'name' => 'Submission Workflow',
            'stages' => [
                ['type' => 'stage', 'data' => [
                    'stage_name' => 'Submit',
                    'stage_type' => 'form_submission',
                ]],
                ['type' => 'stage', 'data' => [
                    'stage_name' => 'Review',
                    'stage_type' => 'review',
                    'responsible_role' => ['admin'],
                    'status_message' => 'Original review status',
                ]],
            ],
        ]);
        $this->workflow->forms()->attach($this->form);
    }

    public function test_actual_submission_uses_optional_candidate_and_selected_staff_messages_once(): void
    {
        $otherStaff = $this->user('unselected_staff', 'admin');
        $this->configure();
        $entry = $this->entry();

        $this->assertSame('Your application was submitted.', $this->candidate->notifications()->sole()->data['body']);
        $this->assertSame('A new application needs checking.', $this->staff->notifications()->sole()->data['body']);
        $this->assertSame('submission_staff', data_get($this->staff->notifications()->sole()->data, 'viewData.workflow_event'));
        $this->assertSame(0, $otherStaff->notifications()->count());
        $this->assertSame('Application submitted', WorkflowStageMessages::statusText($entry));
        $this->assertSame(WorkflowStageType::Review, WorkflowStageMessages::stageTypeFor($entry));
        $this->assertSame('pending', $entry->fresh()->review_status);
        $this->assertSame(0, WorkflowSubmissionMessages::notify($entry));
        $this->assertSame(1, $this->candidate->notifications()->count());
        $this->assertSame(1, $this->staff->notifications()->count());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_legacy_hidden_submission_messages_and_generic_responsible_role_alerts_stay_disabled(): void
    {
        $stages = $this->workflow->stages;
        $stages[0]['data']['status_message'] = 'Legacy hidden status';
        $stages[0]['data']['notification_message'] = 'Legacy hidden notification';
        $this->workflow->update(['stages' => $stages]);
        $entry = $this->entry();

        $this->assertSame('Original review status', WorkflowStageMessages::statusText($entry));
        $this->assertSame(0, WorkflowSubmissionMessages::notify($entry));
        $this->assertSame(0, WorkflowStageMessages::notifyResponsibleRole($entry));
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_shared_submission_status_is_the_same_for_candidates_and_staff_in_a_payment_workflow(): void
    {
        $this->configure([
            'candidate_status_message' => 'បានដាក់ពាក្យ',
            'candidate_notification_message' => '',
            'staff_notification_message' => '',
        ]);
        $stages = $this->workflow->stages;
        $stages[] = ['type' => 'stage', 'data' => [
            'stage_name' => 'Payment',
            'stage_type' => 'payment',
            'responsible_role' => ['cashier_officer'],
        ]];
        $this->workflow->update(['stages' => $stages]);
        $entry = $this->entry();
        $this->staff->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($this->staff);

        $column = Livewire::test(ListCustomFormEntries::class)
            ->instance()->getTable()->getColumn('review_status')->record($entry);

        $this->assertTrue(WorkflowStageMessages::requiresPayment($entry));
        $this->assertSame('បានដាក់ពាក្យ', $column->formatState($column->getState()));
        $this->actingAs($this->candidate);
        $this->assertSame('បានដាក់ពាក្យ', $column->formatState($column->getState()));
        $this->assertSame('pending', $entry->fresh()->review_status);
        $this->assertSame('pending', data_get($entry->fresh()->data, 'registration_status'));
        $this->assertSame(WorkflowStageType::Review, WorkflowStageMessages::stageTypeFor($entry));
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_draft_transition_sends_only_after_submission_and_ordinary_updates_do_not_repeat_it(): void
    {
        $this->configure();
        $entry = $this->entry('draft');
        $this->assertDatabaseCount('notifications', 0);

        $entry->forceFill([
            'review_status' => 'pending',
            'data' => ['registration_status' => 'pending', 'submitted_at' => now()->toDateTimeString()],
        ])->save();

        $this->assertDatabaseCount('notifications', 2);
        $entry->forceFill(['data' => [...$entry->data, 'full_name' => 'Updated name']])->save();
        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame(0, WorkflowSubmissionMessages::notify($entry));
    }

    public function test_done_shows_submitted_then_actual_review_shows_checked_and_real_payment_advances_to_results(): void
    {
        $submitted = 'បានដាក់ពាក្យ';
        $checked = 'បានពិនិត្យ';
        $this->configure(['candidate_status_message' => $submitted]);
        $stages = $this->workflow->stages;
        $stages[1]['data']['review_actions']['accept'] = [
            'status_message' => $checked,
            'notification_message' => 'Your application was checked.',
        ];
        $stages[] = ['type' => 'stage', 'data' => [
            'stage_name' => 'Payment',
            'stage_type' => 'payment',
            'responsible_role' => ['admin'],
            'status_message' => 'Paid',
        ]];
        $stages[] = ['type' => 'stage', 'data' => [
            'stage_name' => 'Results',
            'stage_type' => 'awaiting_results',
            'status_message' => 'Waiting for results',
        ]];
        $this->workflow->update(['stages' => $stages]);
        $role = Role::findOrCreate('candidate', 'web');
        foreach (['ViewAny', 'View', 'Create', 'Update'] as $action) {
            $role->givePermissionTo(Permission::findOrCreate($action.':CustomFormEntry', 'web'));
        }
        $this->candidate->assignRole($role);
        $this->candidate->assignRole(Role::findOrCreate('submission_applicant', 'web'));
        $this->form->update(['allowed_roles' => ['submission_applicant', 'admin']]);
        $profile = CustomForm::query()->create(['name' => 'Profile', 'slug' => 'profile', 'is_active' => true]);
        CustomFormEntry::query()->forceCreate(['custom_form_id' => $profile->getKey(), 'created_by' => $this->candidate->getKey(), 'review_status' => 'pending', 'data' => ['registration_status' => 'pending']]);
        $this->form->fields()->create(['name' => 'full_name', 'label' => 'Full name', 'type' => 'text_input', 'required' => true]);
        $draft = $this->entry('draft');
        $this->actingAs($this->candidate);

        Livewire::withQueryParams(['form_id' => (string) $this->form->getKey(), 'draft_id' => (string) $draft->getKey()])
            ->test(CreateCustomFormEntry::class, ['form_id' => (string) $this->form->getKey(), 'draft_id' => (string) $draft->getKey()])
            ->assertSuccessful()
            ->assertNoRedirect()
            ->assertSet('form_id', (string) $this->form->getKey())
            ->assertSet('draftEntryId', $draft->getKey())
            ->set('data.data.full_name', 'Candidate Done')
            ->callAction(TestAction::make('submit')->schemaComponent('form-actions', schema: 'content'))
            ->assertHasNoFormErrors();

        $this->assertSame('pending', $draft->fresh()->review_status);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame(1, $this->candidate->notifications()->count());
        $this->assertSame(1, $this->staff->notifications()->count());
        $draft->refresh();
        $this->assertNotEmpty(data_get($draft->data, 'submitted_at'));
        $this->assertSame(WorkflowStageType::Review, WorkflowStageMessages::stageTypeFor($draft));
        $this->assertSame($submitted, WorkflowStageMessages::statusText($draft));
        $this->assertNull(WorkflowStageMessages::sharedReviewStatusText($draft));
        $column = $this->column($draft);
        $this->assertSame($submitted, $column->formatState($column->getState()));
        $this->actingAs($this->candidate);
        $this->assertSame($submitted, $column->formatState($column->getState()));

        $this->actingAs($this->staff);
        $this->post(route('admin.custom-form-entries.approve', $draft))->assertRedirect();
        $draft->refresh();
        $this->assertSame('approved', $draft->review_status);
        $this->assertSame('approved', data_get($draft->data, 'registration_status'));
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageMessages::stageTypeFor($draft));
        $this->assertSame($checked, WorkflowStageMessages::statusText($draft));
        $this->assertDatabaseCount('payments', 0);
        $column = $this->column($draft);
        $this->assertSame($checked, $column->formatState($column->getState()));
        $this->actingAs($this->candidate);
        $this->assertSame($checked, $column->formatState($column->getState()));
        $this->assertSame(2, $this->candidate->notifications()->count());
        $this->assertContains('Your application was checked.', $this->candidate->notifications()->get()->pluck('data.body')->all());

        Payment::query()->forceCreate([
            'users_id' => $this->candidate->getKey(),
            'form_id' => $this->form->getKey(),
            'custom_form_entry_id' => $draft->getKey(),
            'receipt_number' => 'DONE-REVIEW-PAID-'.$draft->getKey(),
            'status_payt' => 'paid',
            'amount_usd' => 10,
            'amount_kh' => null,
            'exchange_rate' => 4100,
            'datetime_pay' => now(),
        ]);
        $draft->refresh();
        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageMessages::stageTypeFor($draft));
        $this->assertSame('Paid', WorkflowStageMessages::statusText($draft));
        $column = $this->column($draft);
        $this->assertSame('Paid', $column->formatState($column->getState()));
        $this->actingAs($this->candidate);
        $this->assertSame('Paid', $column->formatState($column->getState()));
        $this->assertDatabaseCount('payments', 1);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_legacy_pending_entry_without_submission_timestamp_displays_submitted_without_sending_notifications(): void
    {
        $this->configure(['candidate_status_message' => 'បានដាក់ពាក្យ']);
        $stages = $this->workflow->stages;
        $stages[1]['data']['review_actions']['accept']['status_message'] = 'បានពិនិត្យ';
        $this->workflow->update(['stages' => $stages]);
        $entry = $this->entry('pending', submitted: false);

        $this->assertNull(data_get($entry->data, 'submitted_at'));
        $this->assertSame('បានដាក់ពាក្យ', WorkflowStageMessages::statusText($entry));
        $this->assertNull(WorkflowStageMessages::sharedReviewStatusText($entry));
        $column = $this->column($entry);
        $this->assertSame('បានដាក់ពាក្យ', $column->formatState($column->getState()));
        $this->actingAs($this->candidate);
        $this->assertSame('បានដាក់ពាក្យ', $column->formatState($column->getState()));
        $this->assertSame(0, WorkflowSubmissionMessages::notify($entry));
        $this->assertDatabaseCount('notifications', 0);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_staff_created_pending_entry_uses_submission_display_without_enabling_candidate_notifications(): void
    {
        $this->configure(['candidate_status_message' => 'បានដាក់ពាក្យ']);
        $entry = CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $this->form->getKey(),
            'created_by' => $this->staff->getKey(),
            'review_status' => 'pending',
            'data' => ['registration_status' => 'pending', 'submitted_at' => now()->toDateTimeString()],
        ]);

        $this->assertSame('បានដាក់ពាក្យ', WorkflowSubmissionMessages::statusText($entry));
        $this->assertSame('បានដាក់ពាក្យ', WorkflowStageMessages::statusText($entry));
        $column = $this->column($entry);
        $this->assertSame('បានដាក់ពាក្យ', $column->formatState($column->getState()));
        $this->assertSame(0, WorkflowSubmissionMessages::notify($entry));
        $this->assertDatabaseCount('notifications', 0);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_draft_rejected_and_completed_entries_do_not_use_submission_status_or_send_submission_notifications(): void
    {
        $this->configure(['candidate_status_message' => 'បានដាក់ពាក្យ']);
        foreach (['draft', 'rejected', 'failed', 'completed'] as $status) {
            $entry = $this->entry($status);
            $this->assertNull(WorkflowSubmissionMessages::statusText($entry));
            $this->assertNotSame('បានដាក់ពាក្យ', WorkflowStageMessages::statusText($entry));
            $this->assertSame(0, WorkflowSubmissionMessages::notify($entry));
        }
        $this->assertDatabaseCount('notifications', 0);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_staff_selection_excludes_inactive_deleted_candidate_and_unselected_accounts(): void
    {
        $inactive = $this->user('inactive_staff', 'admin', false);
        $deleted = $this->user('deleted_staff', 'admin');
        $deleted->delete();
        $unselected = $this->user('not_selected_staff', 'admin');
        $this->configure([
            'candidate_notification_message' => '',
            'staff_recipient_ids' => [$this->staff->getKey(), $this->staff->getKey(), $inactive->getKey(), $deleted->getKey(), $this->candidate->getKey(), 'invalid'],
        ]);
        $options = WorkflowSubmissionMessages::recipientOptions();
        $this->assertArrayHasKey($this->staff->getKey(), $options);
        $this->assertArrayHasKey($unselected->getKey(), $options);
        $this->assertArrayNotHasKey($inactive->getKey(), $options);
        $this->assertArrayNotHasKey($deleted->getKey(), $options);
        $this->assertArrayNotHasKey($this->candidate->getKey(), $options);

        $this->entry();
        $this->assertDatabaseCount('notifications', 1);
        $this->assertSame(1, $this->staff->notifications()->count());
        $this->assertSame(0, $unselected->notifications()->count());
        $this->assertSame(0, $this->candidate->notifications()->count());
        Http::assertNothingSent();
    }

    public function test_blank_messages_or_records_that_are_not_actual_submissions_send_nothing(): void
    {
        $this->configure(['candidate_notification_message' => '', 'staff_notification_message' => '']);
        $entry = $this->entry();
        $this->assertSame('Application submitted', WorkflowStageMessages::statusText($entry));
        $this->assertDatabaseCount('notifications', 0);

        $this->configure();
        $this->entry('approved');
        $this->entry('pending', submitted: false);
        $this->entry('draft');
        $profile = CustomForm::query()->create(['name' => 'Profile', 'slug' => 'profile', 'is_active' => true]);
        $this->workflow->forms()->attach($profile);
        $this->entry(form: $profile);
        $this->assertDatabaseCount('notifications', 0);
    }

    private function configure(array $overrides = []): void
    {
        $stages = $this->workflow->stages;
        $stages[0]['data']['submission_messages'] = array_replace([
            'candidate_status_message' => 'Application submitted',
            'candidate_notification_message' => 'Your application was submitted.',
            'staff_notification_message' => 'A new application needs checking.',
            'staff_recipient_ids' => [$this->staff->getKey()],
        ], $overrides);
        $this->workflow->update(['stages' => $stages]);
    }

    private function column(CustomFormEntry $entry)
    {
        $this->staff->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($this->staff);

        return Livewire::test(ListCustomFormEntries::class)
            ->instance()->getTable()->getColumn('review_status')->record($entry);
    }

    private function entry(string $status = 'pending', bool $submitted = true, ?CustomForm $form = null): CustomFormEntry
    {
        return CustomFormEntry::query()->forceCreate([
            'custom_form_id' => ($form ?? $this->form)->getKey(),
            'created_by' => $this->candidate->getKey(),
            'review_status' => $status,
            'data' => array_filter([
                'registration_status' => $status,
                'submitted_at' => $submitted && $status !== 'draft' ? now()->toDateTimeString() : null,
            ], fn ($value): bool => $value !== null),
        ]);
    }

    private function user(string $name, string $type, bool $active = true): User
    {
        return User::query()->forceCreate([
            'registration_type' => $type,
            'name' => $name,
            'username' => $name,
            'email' => $name.'@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => 'password',
            'is_active' => $active,
        ]);
    }
}
