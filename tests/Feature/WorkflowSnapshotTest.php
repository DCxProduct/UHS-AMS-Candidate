<?php

namespace Tests\Feature;

use App\Enums\WorkflowPaymentSource;
use App\Enums\WorkflowStageType;
use App\Models\CandidateEntranceStatistic;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Services\AmsPaymentSync;
use App\Support\WorkflowEntrySnapshot;
use App\Support\WorkflowStageMessages;
use App\Support\WorkflowStageProgression;
use App\Support\WorkflowSubmissionMessages;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WorkflowSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;

    private CustomForm $form;

    private WorkflowNotification $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('km');
        Http::fake();
        Mail::fake();
        $this->candidate = $this->user('snapshot_candidate', 'student');
        $this->form = $this->form('snapshot-form');
        $this->workflow = WorkflowNotification::query()->create([
            'name' => 'Original workflow',
            'stages' => $this->stages(),
        ]);
        $this->workflow->forms()->attach($this->form);
    }

    public function test_existing_application_keeps_its_khmer_status_after_template_edit_and_new_application_uses_the_new_status(): void
    {
        $old = $this->entry('approved', legacy: true);
        $originalSnapshot = $old->workflow_snapshot;
        $this->assertSame('បានពិនិត្យ', WorkflowStageMessages::statusText($old));

        $stages = $this->workflow->stages;
        $stages[1]['data']['review_actions']['accept']['status_message'] = 'បានពិនិត្យពាក្យ';
        $this->workflow->update(['name' => 'Updated workflow', 'stages' => $stages]);
        $new = $this->entry('approved', legacy: true);

        $this->assertSame('បានពិនិត្យ', WorkflowStageMessages::statusText($old->fresh()));
        $this->assertSame('បានពិនិត្យពាក្យ', WorkflowStageMessages::statusText($new->fresh()));
        $this->assertSame($originalSnapshot, $old->fresh()->workflow_snapshot);
        $this->assertSame('Original workflow', WorkflowNotification::forEntry($old->fresh())?->name);
        $this->assertSame('Updated workflow', WorkflowNotification::forEntry($new->fresh())?->name);
        $this->assertSame('បានពិនិត្យ', data_get($originalSnapshot, 'stages.1.data.review_actions.accept.status_message'));
        $storedSnapshot = json_decode(DB::table('custom_form_entries')->where('id', $old->id)->value('workflow_snapshot'), true);
        $this->assertSame('បានពិនិត្យ', data_get($storedSnapshot, 'stages.1.data.review_actions.accept.status_message'));
        $this->assertSame('approved', $old->fresh()->review_status);
        $this->assertSame('approved', $new->fresh()->review_status);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_future_stages_roles_channels_messages_and_payment_source_are_frozen_for_the_entire_application(): void
    {
        $old = $this->entry();
        $originalStages = $this->workflow->stages;
        $stages = $originalStages;
        $stages[1]['data']['responsible_role'] = ['new_reviewer'];
        $stages[1]['data']['notification_channels'] = ['email', 'sms'];
        $stages[1]['data']['email_template_key'] = 'updated-email';
        $stages[1]['data']['sms_template_key'] = 'updated-sms';
        $stages[1]['data']['review_actions']['send_back']['notification_message'] = 'New correction instructions';
        $stages[2]['data']['payment_source'] = 'ams';
        $stages[2]['data']['status_message'] = 'New paid label';
        $stages[2]['data']['payment_statuses']['unpaid_status_message'] = 'New unpaid label';
        $stages[2]['data']['audience_messages']['staff_recipient_roles'] = ['new_reviewer'];
        $stages[3]['data']['result_statuses']['pending_status_message'] = 'New pending label';
        $stages[4]['data']['status_message'] = 'New completed label';
        $this->workflow->update(['stages' => $stages]);
        $new = $this->entry();

        $frozen = WorkflowNotification::forEntry($old->fresh());
        $latest = WorkflowNotification::forEntry($new->fresh());
        $this->assertSame($originalStages, $frozen?->stages);
        $this->assertSame($this->workflow->fresh()->stages, $latest?->stages);
        $this->assertSame(['system'], WorkflowStageMessages::channels($frozen->stages[1]['data']));
        $this->assertSame(['sms', 'email'], WorkflowStageMessages::channels($latest->stages[1]['data']));
        $this->assertSame(WorkflowPaymentSource::System, $frozen->paymentSource());
        $this->assertSame(WorkflowPaymentSource::Ams, $latest->paymentSource());
        $this->assertFalse(AmsPaymentSync::usesAms($old->fresh()));
        $this->assertTrue(AmsPaymentSync::usesAms($new->fresh()));
        $this->assertSame('រង់ចាំលទ្ធផល', WorkflowStageMessages::waitingResultStatusLabel($old->fresh()));
        $this->assertSame('New pending label', WorkflowStageMessages::waitingResultStatusLabel($new->fresh()));

        $reviewer = $this->user('original_reviewer');
        $reviewer->assignRole(Role::findOrCreate('old_reviewer', 'web'));
        $this->actingAs($reviewer);
        $this->assertTrue(WorkflowStageMessages::canCurrentUserHandleStage($old->fresh(), WorkflowStageType::Review));
        $this->assertFalse(WorkflowStageMessages::canCurrentUserHandleStage($new->fresh(), WorkflowStageType::Review));
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_review_notifications_continue_using_the_original_messages_and_selected_roles(): void
    {
        $oldStaff = $this->user('old_staff');
        $oldStaff->assignRole(Role::findOrCreate('old_reviewer', 'web'));
        $newStaff = $this->user('new_staff');
        $newStaff->assignRole(Role::findOrCreate('new_reviewer', 'web'));
        $stages = $this->workflow->stages;
        $stages[1]['data']['review_actions']['send_back'] = [
            'status_message' => 'Original correction status',
            'notification_message' => 'Original candidate instructions',
            'staff_notification_message' => 'Original staff instructions',
            'staff_recipient_roles' => ['old_reviewer'],
        ];
        $this->workflow->update(['stages' => $stages]);
        $old = $this->entry();
        $stages[1]['data']['review_actions']['send_back'] = [
            'status_message' => 'Updated correction status',
            'notification_message' => 'Updated candidate instructions',
            'staff_notification_message' => 'Updated staff instructions',
            'staff_recipient_roles' => ['new_reviewer'],
        ];
        $this->workflow->update(['stages' => $stages]);
        $new = $this->entry();

        $this->assertTrue(WorkflowStageMessages::notifyReviewAction($old->fresh(), 'send_back'));
        $this->assertSame('Original candidate instructions', $this->candidate->notifications()->sole()->data['body']);
        $this->assertSame('Original staff instructions', $oldStaff->notifications()->sole()->data['body']);
        $this->assertSame(0, $newStaff->notifications()->count());
        $this->assertTrue(WorkflowStageMessages::notifyReviewAction($new->fresh(), 'send_back'));
        $this->assertSame('Updated staff instructions', $newStaff->notifications()->sole()->data['body']);
        $this->assertSame(1, $oldStaff->notifications()->count());
        $this->assertEqualsCanonicalizing(
            ['Original candidate instructions', 'Updated candidate instructions'],
            $this->candidate->notifications()->get()->pluck('data.body')->all(),
        );
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_draft_submission_and_send_back_resubmission_keep_the_same_original_workflow(): void
    {
        $draft = $this->entry('draft', legacy: true);
        $snapshot = $draft->workflow_snapshot;
        $stages = $this->workflow->stages;
        $stages[0]['data']['submission_messages']['candidate_status_message'] = 'New submitted label';
        $stages[1]['data']['review_actions']['accept']['status_message'] = 'New checked label';
        $this->workflow->update(['stages' => $stages]);

        $draft->forceFill([
            'review_status' => 'pending',
            'data' => ['registration_status' => 'pending', 'submitted_at' => now()->toIso8601String()],
        ])->save();
        $this->assertSame('បានដាក់ពាក្យ', WorkflowSubmissionMessages::statusText($draft->fresh()));
        $draft->forceFill(['review_status' => 'rejected', 'data' => ['registration_status' => 'rejected']])->save();
        $draft->forceFill(['review_status' => 'pending', 'data' => ['registration_status' => 'pending']])->save();
        $this->assertSame($snapshot, $draft->fresh()->workflow_snapshot);
        $this->assertSame('បានដាក់ពាក្យ', WorkflowSubmissionMessages::statusText($draft->fresh()));
        $draft->forceFill(['review_status' => 'approved', 'data' => ['registration_status' => 'approved']])->save();
        $this->assertSame('បានពិនិត្យ', WorkflowStageMessages::statusText($draft->fresh()));
        $this->assertSame('New checked label', WorkflowStageMessages::statusText($this->entry('approved', legacy: true)));
    }

    public function test_an_application_started_without_a_template_does_not_switch_when_a_template_is_attached(): void
    {
        $form = $this->form('originally-static');
        $old = $this->entry(form: $form);
        $this->assertFalse($old->workflow_snapshot['has_workflow']);
        $this->assertNull(WorkflowNotification::forEntry($old));
        $this->workflow->forms()->attach($form);

        $this->assertNull(WorkflowNotification::forEntry($old->fresh()));
        $this->assertFalse(WorkflowStageMessages::hasConfiguredWorkflow($old->fresh()));
        $this->assertNotNull(WorkflowNotification::forEntry($this->entry(form: $form)));
        $this->assertSame($this->workflow->id, WorkflowNotification::forForm($form->id)?->id);
    }

    public function test_reassigning_and_deleting_the_template_does_not_remove_old_application_configuration(): void
    {
        $old = $this->entry('approved', legacy: true);
        $snapshot = $old->workflow_snapshot;
        $replacement = WorkflowNotification::query()->create([
            'name' => 'Replacement workflow',
            'stages' => [['type' => 'stage', 'data' => ['stage_type' => 'review', 'stage_name' => 'New review']]],
        ]);
        WorkflowNotification::assignForm($this->form->id, $replacement->id);
        $new = $this->entry();
        $this->workflow->delete();

        $this->assertSame($snapshot, $old->fresh()->workflow_snapshot);
        $this->assertSame('Original workflow', WorkflowNotification::forEntry($old->fresh())?->name);
        $this->assertSame('បានពិនិត្យ', WorkflowStageMessages::statusText($old->fresh()));
        $this->assertTrue(WorkflowStageMessages::requiresPayment($old->fresh()));
        $this->assertSame('Replacement workflow', WorkflowNotification::forEntry($new->fresh())?->name);
        $this->assertFalse(WorkflowStageMessages::requiresPayment($new->fresh()));
        WorkflowNotification::assignForm($this->form->id, null);
        $this->assertSame('Replacement workflow', WorkflowNotification::forEntry($new->fresh())?->name);
        $this->assertNull(WorkflowNotification::forEntry($this->entry()));
    }

    public function test_completed_applications_keep_their_original_status_after_later_template_edits(): void
    {
        $old = $this->entry();
        $this->complete($old);
        $this->assertTrue(WorkflowStageMessages::notifyConfiguredStage($old, WorkflowStageType::Completed));
        $this->assertSame('បានបញ្ចប់ដើម', WorkflowStageMessages::statusText($old->fresh()));
        $stages = $this->workflow->stages;
        $stages[4]['data']['status_message'] = 'បានបញ្ចប់ថ្មី';
        $this->workflow->update(['stages' => $stages]);
        $new = $this->entry();
        $this->complete($new);
        $this->assertTrue(WorkflowStageMessages::notifyConfiguredStage($new, WorkflowStageType::Completed));

        $this->assertSame('បានបញ្ចប់ដើម', WorkflowStageMessages::statusText($old->fresh()));
        $this->assertSame('បានបញ្ចប់ថ្មី', WorkflowStageMessages::statusText($new->fresh()));
    }

    public function test_statistics_subclass_creation_captures_a_snapshot_and_normal_updates_cannot_replace_it(): void
    {
        $entry = CandidateEntranceStatistic::query()->forceCreate([
            'custom_form_id' => $this->form->id,
            'created_by' => $this->candidate->id,
            'review_status' => 'pending',
            'data' => ['registration_status' => 'pending'],
        ]);
        $original = $entry->workflow_snapshot;
        $this->assertTrue($original['has_workflow']);
        $this->assertSame($this->workflow->id, $original['workflow_id']);
        $this->assertSame(2, $original['schema_version']);
        $this->assertNotEmpty($original['captured_at']);

        $stages = $this->workflow->stages;
        $stages[1]['data']['review_actions']['accept']['status_message'] = 'Unexpected replacement';
        $this->workflow->update(['stages' => $stages]);
        $entry->forceFill([
            'workflow_snapshot' => WorkflowEntrySnapshot::forForm($this->form->id),
            'review_status' => 'approved',
            'data' => ['registration_status' => 'approved', 'note' => 'An ordinary update'],
        ])->save();
        WorkflowStageProgression::reviewed($entry);

        $this->assertSame($original, $entry->fresh()->workflow_snapshot);
        $this->assertSame('បានពិនិត្យ', data_get($entry->fresh()->workflow_snapshot, 'stages.1.data.review_actions.accept.status_message'));
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageMessages::stageTypeFor($entry->fresh()));
        $this->assertSame('មិនទាន់បង់ប្រាក់', WorkflowStageMessages::statusText($entry->fresh()));
        $this->assertSame('An ordinary update', data_get($entry->fresh()->data, 'note'));
    }

    public function test_migration_backfills_legacy_workflow_and_static_entries_without_overwriting_existing_snapshots(): void
    {
        $existing = $this->entry();
        $original = $existing->workflow_snapshot;
        $staticForm = $this->form('legacy-static');
        $legacyIds = [];
        foreach ([$this->form, $staticForm] as $form) {
            $legacyIds[] = DB::table('custom_form_entries')->insertGetId([
                'custom_form_id' => $form->id,
                'created_by' => $this->candidate->id,
                'review_status' => 'pending',
                'data' => json_encode(['registration_status' => 'pending']),
                'workflow_snapshot' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        (require database_path('migrations/2026_10_30_000002_freeze_application_workflows.php'))->up();
        $legacy = CustomFormEntry::query()->findOrFail($legacyIds[0]);
        $static = CustomFormEntry::query()->findOrFail($legacyIds[1]);

        $this->assertTrue($legacy->workflow_snapshot['has_workflow']);
        $this->assertSame($this->workflow->stages, $legacy->workflow_snapshot['stages']);
        $this->assertFalse($static->workflow_snapshot['has_workflow']);
        $this->assertNull(WorkflowNotification::forEntry($static));
        $this->assertSame($original, $existing->fresh()->workflow_snapshot);

        $stages = $this->workflow->stages;
        $stages[0]['data']['submission_messages']['candidate_status_message'] = 'Updated after migration';
        $this->workflow->update(['stages' => $stages]);
        $this->assertSame('បានដាក់ពាក្យ', WorkflowSubmissionMessages::statusText($legacy->fresh()));
        $this->assertSame('Updated after migration', WorkflowSubmissionMessages::statusText($this->entry()));
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_partially_loaded_models_read_and_preserve_the_persisted_original_snapshot(): void
    {
        $entry = $this->entry('approved');
        $original = $entry->workflow_snapshot;
        $progress = data_get($entry->data, 'workflow_progress');
        $stages = $this->workflow->stages;
        $stages[1]['data']['review_actions']['accept']['status_message'] = 'Updated checked label';
        $this->workflow->update(['stages' => $stages]);
        $partial = CustomFormEntry::query()
            ->select(['id', 'custom_form_id', 'created_by', 'review_status', 'data'])
            ->findOrFail($entry->id);

        $this->assertArrayNotHasKey('workflow_snapshot', $partial->getAttributes());
        $this->assertSame(WorkflowStageMessages::statusText($entry->fresh()), WorkflowStageMessages::statusText($partial));
        $this->assertSame(WorkflowStageMessages::stageTypeFor($entry->fresh()), WorkflowStageMessages::stageTypeFor($partial));
        $this->assertSame($original['stages'], WorkflowNotification::forEntry($partial)?->stages);
        $partial->forceFill([
            'workflow_snapshot' => WorkflowEntrySnapshot::forForm($this->form->id),
            'data' => [...$partial->data, 'note' => 'Partial model update'],
        ])->save();

        $this->assertSame($original, $entry->fresh()->workflow_snapshot);
        $this->assertSame($progress, data_get($entry->fresh()->data, 'workflow_progress'));
        $this->assertSame('Partial model update', data_get($entry->fresh()->data, 'note'));
    }

    public function test_a_model_loaded_before_backfill_uses_and_preserves_the_snapshot_captured_concurrently(): void
    {
        $entry = $this->entry('approved');
        $original = $entry->workflow_snapshot;
        DB::table('custom_form_entries')->where('id', $entry->id)->update(['workflow_snapshot' => null]);
        $stale = CustomFormEntry::query()->findOrFail($entry->id);
        $this->assertNull($stale->workflow_snapshot);

        // A migration or another request captures the original workflow after this model was loaded.
        DB::table('custom_form_entries')->where('id', $entry->id)->update([
            'workflow_snapshot' => json_encode($original, JSON_UNESCAPED_UNICODE),
        ]);
        $stages = $this->workflow->stages;
        $stages[1]['data']['review_actions']['accept']['status_message'] = 'Later template edit';
        $this->workflow->update(['stages' => $stages]);

        $this->assertSame(WorkflowStageMessages::statusText($entry->fresh()), WorkflowStageMessages::statusText($stale));
        $this->assertSame(WorkflowStageMessages::stageTypeFor($entry->fresh()), WorkflowStageMessages::stageTypeFor($stale));
        $stale->forceFill(['data' => [...$stale->data, 'note' => 'Loaded before deployment']])->save();
        $this->assertSame($original, $entry->fresh()->workflow_snapshot);
        $this->assertSame('Loaded before deployment', data_get($entry->fresh()->data, 'note'));
    }

    public function test_an_ordinary_data_update_cannot_drop_persisted_ordered_progress(): void
    {
        $entry = $this->entry();
        WorkflowStageProgression::submitted($entry);
        WorkflowStageProgression::reviewed($entry);
        $progress = data_get($entry->fresh()->data, 'workflow_progress');
        $snapshot = $entry->fresh()->workflow_snapshot;
        $partial = CustomFormEntry::query()->select(['id', 'custom_form_id', 'review_status'])->findOrFail($entry->id);
        $partial->forceFill(['data' => ['note' => 'Updated without loading workflow fields']])->save();

        $this->assertSame($progress, data_get($entry->fresh()->data, 'workflow_progress'));
        $this->assertSame($snapshot, $entry->fresh()->workflow_snapshot);
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageMessages::stageTypeFor($entry->fresh()));
        $this->assertSame('Updated without loading workflow fields', data_get($entry->fresh()->data, 'note'));
    }

    public function test_client_supplied_progress_cannot_skip_stages_on_creation_or_update(): void
    {
        $forged = [
            'version' => 1, 'submitted' => true, 'reviewed' => true, 'result_passed' => true,
            'payment_completed' => true, 'finished' => true, 'ready_for_final' => true,
            'current_type' => 'completed', 'current_index' => 4, 'completed_at' => ['forged'],
        ];
        $entry = CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $this->form->id, 'created_by' => $this->candidate->id,
            'review_status' => 'pending', 'data' => ['registration_status' => 'pending', 'workflow_progress' => $forged],
        ]);
        $progress = data_get($entry->fresh()->data, 'workflow_progress');
        $this->assertSame(WorkflowStageType::Review, WorkflowStageProgression::currentType($entry->fresh()));
        foreach (['reviewed', 'result_passed', 'payment_completed', 'finished', 'ready_for_final'] as $flag) {
            $this->assertFalse($progress[$flag], $flag);
        }
        $this->assertNotSame($forged, $progress);

        $entry->forceFill(['data' => [...$entry->data, 'workflow_progress' => $forged, 'note' => 'Ordinary edit']])->save();
        $this->assertSame($progress, data_get($entry->fresh()->data, 'workflow_progress'));
        $this->assertSame(WorkflowStageType::Review, WorkflowStageProgression::currentType($entry->fresh()));
        $this->assertSame('Ordinary edit', data_get($entry->fresh()->data, 'note'));
    }

    private function stages(): array
    {
        return [
            ['type' => 'stage', 'data' => [
                'stage_type' => 'form_submission', 'stage_name' => 'Submit',
                'notification_channels' => ['system'],
                'submission_messages' => ['candidate_status_message' => 'បានដាក់ពាក្យ'],
            ]],
            ['type' => 'stage', 'data' => [
                'stage_type' => 'review', 'stage_name' => 'Review',
                'responsible_role' => ['old_reviewer'], 'notification_channels' => ['system'],
                'review_actions' => [
                    'accept' => ['status_message' => 'បានពិនិត្យ'],
                    'send_back' => ['status_message' => 'បញ្ជូនត្រឡប់'],
                    'reject' => ['status_message' => 'បានបដិសេធ'],
                ],
            ]],
            ['type' => 'stage', 'data' => [
                'stage_type' => 'payment', 'stage_name' => 'Payment', 'payment_source' => 'system',
                'responsible_role' => ['cashier_officer'], 'notification_channels' => ['system'],
                'status_message' => 'បានបង់ប្រាក់',
                'payment_statuses' => ['unpaid_status_message' => 'មិនទាន់បង់ប្រាក់'],
                'audience_messages' => ['staff_recipient_roles' => ['old_reviewer']],
            ]],
            ['type' => 'stage', 'data' => [
                'stage_type' => 'awaiting_results', 'stage_name' => 'Results',
                'responsible_role' => ['old_reviewer'], 'notification_channels' => ['system'],
                'status_message' => 'បានជាប់',
                'result_statuses' => ['pending_status_message' => 'រង់ចាំលទ្ធផល'],
            ]],
            ['type' => 'stage', 'data' => [
                'stage_type' => 'completed', 'stage_name' => 'Completed',
                'notification_channels' => ['system'], 'status_message' => 'បានបញ្ចប់ដើម',
                'notification_message' => 'Your original application process is complete.',
            ]],
        ];
    }

    private function entry(string $status = 'pending', ?CustomForm $form = null, bool $legacy = false): CustomFormEntry
    {
        $attributes = [
            'custom_form_id' => ($form ?? $this->form)->id,
            'created_by' => $this->candidate->id,
            'review_status' => $status,
            'data' => ['registration_status' => $status],
        ];
        if ($legacy) {
            $snapshot = WorkflowEntrySnapshot::forForm($attributes['custom_form_id']);
            $snapshot['schema_version'] = 1;

            return CustomFormEntry::withoutEvents(fn (): CustomFormEntry => CustomFormEntry::query()->forceCreate([
                ...$attributes, 'workflow_snapshot' => $snapshot,
            ]));
        }

        return CustomFormEntry::query()->forceCreate($attributes);
    }

    private function complete(CustomFormEntry $entry): void
    {
        WorkflowStageProgression::submitted($entry);
        WorkflowStageProgression::reviewed($entry);
        $this->pay($entry);
        WorkflowStageProgression::paid($entry);
        WorkflowStageProgression::passed($entry);
        $this->assertTrue(WorkflowStageProgression::readyForFinal($entry->fresh()));
    }

    private function form(string $slug): CustomForm
    {
        return CustomForm::query()->create(['name' => $slug, 'slug' => $slug, 'is_active' => true]);
    }

    private function user(string $name, string $type = 'admin'): User
    {
        return User::query()->forceCreate([
            'name' => $name, 'username' => $name, 'registration_type' => $type,
            'email' => $name.'@example.test', 'date_of_birth' => '2000-01-01',
            'password' => 'password', 'is_active' => true, 'locale' => 'km',
        ]);
    }

    private function pay(CustomFormEntry $entry): void
    {
        DB::table('payments')->insert([
            'form_id' => $entry->custom_form_id, 'custom_form_entry_id' => $entry->id,
            'users_id' => $entry->created_by, 'status_payt' => 'paid',
            'datetime_pay' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
