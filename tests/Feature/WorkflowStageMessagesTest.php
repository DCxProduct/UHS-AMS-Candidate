<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\CandidatePaymentLists\CandidatePaymentListResource;
use App\Filament\Admin\Resources\CandidateEntranceStatistics\Tables\CandidateEntranceStatisticsTable;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Support\WorkflowStageMessages;
use App\Support\NotificationLanguage;
use App\Support\PassedResultMenuOptions;
use App\Support\StatisticsMenuOptions;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\ListCustomFormEntries;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class WorkflowStageMessagesTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private CustomForm $form;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = $this->user('candidate_user', 'student');
        $this->form = $this->customForm();

        WorkflowNotification::query()->create([
            'name' => 'Admission',
            'stages' => [
                $this->stage('Submit', 'form_submission'),
                $this->stage('Review', 'review', 'Under review', 'We are checking your documents.'),
                $this->stage('Payment', 'payment', 'Waiting for payment', 'Please pay the fee.'),
                $this->stage('Awaiting Results', 'awaiting_results', 'Waiting for results', 'Your exam is coming.'),
                $this->stage('Completed', 'completed', 'Completed', 'Congratulations.'),
            ],
        ])->forms()->attach($this->form);
    }

    public function test_stage_follows_the_existing_review_statuses(): void
    {
        $this->assertNull(WorkflowStageMessages::stageTypeFor($this->entry('draft')));
        $this->assertSame(WorkflowStageType::Review, WorkflowStageMessages::stageTypeFor($this->entry('pending')));
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageMessages::stageTypeFor($this->entry('approved')));
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageMessages::stageTypeFor($this->entry('accepted')));
        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageMessages::stageTypeFor($this->entry('passed')));

        $exitForm = $this->customForm();
        $exitForm->update([
            'statistics_menu' => StatisticsMenuOptions::EXIT_EXAM_STATISTICS,
            'passed_result_menu' => PassedResultMenuOptions::EXIT_EXAM_RESULTS,
        ]);
        WorkflowNotification::query()->where('name', 'Admission')->firstOrFail()->forms()->attach($exitForm);
        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageMessages::stageTypeFor($this->entry('passed', form: $exitForm)));
        $this->assertSame(WorkflowStageType::Rejected, WorkflowStageMessages::stageTypeFor($this->entry('rejected')));
        $this->assertSame(WorkflowStageType::Rejected, WorkflowStageMessages::stageTypeFor($this->entry('failed')));

        $received = $this->entry('approved', ['original_application_received' => true]);
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageMessages::stageTypeFor($received));

        $paid = $this->entry('approved');
        $this->pay($paid);
        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageMessages::stageTypeFor($paid));

        $noPaymentForm = $this->customForm();
        $noPaymentForm->update(['requires_payment' => false]);
        $noPayment = $this->entry('approved', form: $noPaymentForm);
        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageMessages::stageTypeFor($noPayment));

        $profile = CustomForm::query()->create([
            'name' => 'Profile', 'slug' => 'profile', 'is_active' => true, 'menu_placement' => 'sidebar',
        ]);
        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageMessages::stageTypeFor($this->entry('approved', form: $profile)));
    }

    public function test_payment_stage_is_the_source_of_truth_for_payment_behavior(): void
    {
        $entry = $this->entry('approved');
        $this->actingAs($this->user('payment_admin', 'admin'));

        $this->assertTrue(WorkflowStageMessages::requiresPayment($entry));
        $this->assertTrue(CandidatePaymentListResource::getEloquentQuery()->whereKey($entry->getKey())->exists());

        $this->pay($entry);

        $this->assertFalse(CandidatePaymentListResource::getEloquentQuery()->whereKey($entry->getKey())->exists());
    }

    public function test_workflow_payment_stage_overrides_the_form_payment_toggle(): void
    {
        $form = $this->customForm();
        $form->update(['requires_payment' => false]);
        WorkflowNotification::query()->where('name', 'Admission')->firstOrFail()->forms()->attach($form);

        $entry = $this->entry('approved', form: $form);
        $this->actingAs($this->user('payment_admin', 'admin'));

        $this->assertTrue(WorkflowStageMessages::requiresPayment($entry));
        $this->assertTrue(CandidatePaymentListResource::getEloquentQuery()->whereKey($entry->getKey())->exists());
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageMessages::stageTypeFor($entry));
    }

    public function test_workflow_without_payment_stage_skips_payment(): void
    {
        $form = $this->customForm();
        WorkflowNotification::query()->create([
            'name' => 'No Payment',
            'stages' => [
                $this->stage('Review', 'review', 'Under review'),
                $this->stage('Awaiting Results', 'awaiting_results', 'Waiting for results'),
            ],
        ])->forms()->attach($form);

        $entry = $this->entry('approved', form: $form);

        $this->assertFalse(WorkflowStageMessages::requiresPayment($entry));
        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageMessages::stageTypeFor($entry));
        $this->assertFalse(CandidatePaymentListResource::getEloquentQuery()->whereKey($entry->getKey())->exists());
    }

    public function test_status_text_comes_from_the_forms_workflow_stage(): void
    {
        $this->assertSame('Under review', WorkflowStageMessages::statusText($this->entry('pending')));
        $this->assertSame('Waiting for payment', WorkflowStageMessages::statusText($this->entry('approved')));
        $this->assertSame('Completed', WorkflowStageMessages::statusText($this->entry('passed')));

        $editedResult = $this->entry('passed', ['candidate_status' => 'pending']);
        $this->assertSame('Waiting for results', WorkflowStageMessages::statusText($editedResult));

        $this->assertNull(WorkflowStageMessages::statusText($this->entry('rejected')));
        $this->assertNull(WorkflowStageMessages::statusText($this->entry('draft')));
    }

    public function test_forms_without_a_workflow_keep_the_existing_behaviour(): void
    {
        $entry = $this->entry('pending', form: $this->customForm());

        $this->assertNull(WorkflowStageMessages::statusText($entry));

        WorkflowStageMessages::notify($entry, WorkflowStageType::Review);
        $this->assertSame(0, $this->student->notifications()->count());
    }

    public function test_unassigned_forms_use_the_legacy_result_notification(): void
    {
        $form = $this->customForm();
        $form->update(['requires_payment' => false]);
        $entry = $this->entry('passed', ['candidate_status' => 'passed'], $form);

        $this->assertTrue(CandidateEntranceStatisticsTable::notifyStudentReviewResult($entry, 'passed'));
        $this->assertSame(
            [NotificationLanguage::transForUser($this->student, 'candidate_entrance_statistics.notifications.student_accepted_body', ['student' => $this->student->name])],
            $this->student->notifications()->get()->pluck('data.body')->all(),
        );
        $this->assertTrue(CandidateEntranceStatisticsTable::hasStudentReviewResultNotification($entry, 'passed'));
    }

    public function test_unassigned_forms_keep_the_legacy_payment_toggle(): void
    {
        $entry = $this->entry('approved', form: $this->customForm());
        $this->actingAs($this->user('payment_admin', 'admin'));

        $this->assertTrue(WorkflowStageMessages::requiresPayment($entry));
        $this->assertTrue(CandidatePaymentListResource::getEloquentQuery()->whereKey($entry->getKey())->exists());
    }

    public function test_notification_text_is_sent_to_the_candidate_at_the_expected_stage(): void
    {
        $entry = $this->entry('pending');

        WorkflowStageMessages::notify($entry, WorkflowStageType::Review);

        $notification = $this->student->notifications()->sole();
        $this->assertSame('We are checking your documents.', $notification->data['body']);
        $this->assertStringContainsString('Review', $notification->data['title']);
    }

    public function test_responsible_role_receives_the_workflow_staff_notification(): void
    {
        $entry = $this->entry('pending');

        Role::query()->create([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);
        Role::query()->create([
            'name' => 'cashier_officer',
            'guard_name' => 'web',
        ]);

        $assignedUser = $this->user('assigned_admin', 'admin');
        $assignedUser->assignRole('admin');
        $otherUser = $this->user('other_staff', 'admin');
        $otherUser->assignRole('cashier_officer');

        $this->assertSame(1, WorkflowStageMessages::notifyResponsibleRole($entry));
        $this->assertSame(1, $assignedUser->notifications()->count());
        $this->assertSame(0, $otherUser->notifications()->count());
        $this->assertSame(
            0,
            WorkflowStageMessages::notifyResponsibleRole($entry),
        );
    }

    public function test_only_the_responsible_role_can_handle_a_configured_stage(): void
    {
        $entry = $this->entry('pending');

        Role::query()->create([
            'name' => 'registrar_officer',
            'guard_name' => 'web',
        ]);
        Role::query()->create([
            'name' => 'cashier_officer',
            'guard_name' => 'web',
        ]);

        $assignedUser = $this->user('assigned_reviewer', 'admin');
        $assignedUser->assignRole('registrar_officer');
        $otherUser = $this->user('other_reviewer', 'admin');
        $otherUser->assignRole('cashier_officer');

        WorkflowNotification::query()
            ->where('name', 'Admission')
            ->update([
                'stages' => [
                    $this->stage('Submit', 'form_submission'),
                    [
                        'type' => 'stage',
                        'data' => [
                            'stage_name' => 'Review',
                            'stage_type' => 'review',
                            'responsible_role' => 'registrar_officer',
                        ],
                    ],
                ],
            ]);

        $this->actingAs($assignedUser);
        $this->assertTrue(WorkflowStageMessages::canCurrentUserHandleStage($entry, WorkflowStageType::Review));

        $this->actingAs($otherUser);
        $this->assertFalse(WorkflowStageMessages::canCurrentUserHandleStage($entry, WorkflowStageType::Review));
    }

    public function test_nothing_is_sent_when_the_stage_is_not_the_expected_one(): void
    {
        // Payment recorded while the submission is still waiting for review.
        WorkflowStageMessages::notify($this->entry('pending'), WorkflowStageType::AwaitingResults);

        $this->assertSame(0, $this->student->notifications()->count());
    }

    public function test_nothing_is_sent_when_the_stage_has_no_notification_text(): void
    {
        $form = $this->customForm();
        WorkflowNotification::query()->create([
            'name' => 'Quiet',
            'stages' => [$this->stage('Review', 'review')],
        ])->forms()->attach($form);

        WorkflowStageMessages::notify($this->entry('pending', form: $form), WorkflowStageType::Review);

        $this->assertSame(0, $this->student->notifications()->count());
    }

    public function test_approving_from_the_pdf_review_sends_the_review_notification(): void
    {
        $entry = $this->entry('pending');
        $this->actingAs($this->user('admin_user', 'admin'));

        $this->post(route('admin.custom-form-entries.approve', $entry))->assertRedirect();

        $entry->refresh();
        $this->assertSame('approved', $entry->review_status);
        $this->assertSame('Waiting for payment', WorkflowStageMessages::statusText($entry));
        $this->assertSame(['We are checking your documents.'], $this->student->notifications()->get()->pluck('data.body')->all());
    }

    public function test_status_message_is_not_sent_when_notification_message_is_empty(): void
    {
        $form = $this->customForm();
        WorkflowNotification::query()->create([
            'name' => 'Status Only',
            'stages' => [$this->stage('Payment', 'payment', 'Waiting for payment')],
        ])->forms()->attach($form);

        $entry = $this->entry('approved', form: $form);

        $this->assertSame('Waiting for payment', WorkflowStageMessages::statusText($entry));
        $this->assertFalse(WorkflowStageMessages::notify($entry, WorkflowStageType::Payment));
        $this->assertSame(0, $this->student->notifications()->count());
    }

    public function test_identical_status_and_notification_messages_are_not_sent_twice(): void
    {
        $form = $this->customForm();
        WorkflowNotification::query()->create([
            'name' => 'Duplicate Text',
            'stages' => [$this->stage('Payment', 'payment', 'Payment Done', 'Payment Done')],
        ])->forms()->attach($form);

        $entry = $this->entry('approved', form: $form);

        $this->assertSame('Payment Done', WorkflowStageMessages::statusText($entry));
        $this->assertFalse(WorkflowStageMessages::notify($entry, WorkflowStageType::Payment));
        $this->assertSame(0, $this->student->notifications()->count());
    }

    public function test_recording_a_payment_after_approval_sends_the_payment_text(): void
    {
        $entry = $this->entry('approved');
        $this->pay($entry);

        WorkflowStageMessages::notifyConfiguredStage($entry, WorkflowStageType::Payment);

        $this->assertSame(['Please pay the fee.'], $this->student->notifications()->get()->pluck('data.body')->all());
    }

    public function test_passed_entrance_result_sends_the_awaiting_results_stage_text(): void
    {
        $entry = $this->entry('passed', ['candidate_status' => 'passed']);
        $this->pay($entry);

        $this->assertTrue(CandidateEntranceStatisticsTable::notifyStudentReviewResult($entry, 'passed'));

        $this->assertSame(['Your exam is coming.'], $this->student->notifications()->get()->pluck('data.body')->all());
    }

    public function test_result_stage_notification_is_sent_only_once_after_pass(): void
    {
        $entry = $this->entry('passed', ['candidate_status' => 'passed']);
        $this->pay($entry);

        $this->assertTrue(WorkflowStageMessages::notifyResultStage($entry));
        $this->assertFalse(WorkflowStageMessages::notifyResultStage($entry));
        $this->assertSame(['Your exam is coming.'], $this->student->notifications()->get()->pluck('data.body')->all());
    }

    public function test_notify_student_uses_the_completed_stage_after_pass(): void
    {
        $entry = $this->entry('passed', ['candidate_status' => 'passed']);
        $this->pay($entry);

        $this->assertTrue(CandidateEntranceStatisticsTable::notifyStudentReviewResult(
            record: $entry,
            status: 'passed',
            notificationStage: WorkflowStageType::Completed,
        ));

        $this->assertSame(['Congratulations.'], $this->student->notifications()->get()->pluck('data.body')->all());
    }

    public function test_passed_exit_result_sends_the_awaiting_results_stage_text(): void
    {
        $exitForm = $this->customForm();
        $exitForm->update([
            'statistics_menu' => StatisticsMenuOptions::EXIT_EXAM_STATISTICS,
            'passed_result_menu' => PassedResultMenuOptions::EXIT_EXAM_RESULTS,
        ]);
        WorkflowNotification::query()->where('name', 'Admission')->firstOrFail()->forms()->attach($exitForm);

        $entry = $this->entry('passed', ['candidate_status' => 'passed'], $exitForm);
        $this->pay($entry);

        $this->assertTrue(CandidateEntranceStatisticsTable::notifyStudentReviewResult($entry, 'passed'));

        $this->assertSame(['Your exam is coming.'], $this->student->notifications()->get()->pluck('data.body')->all());
    }

    public function test_final_result_notification_can_use_the_completed_stage(): void
    {
        $entry = $this->entry('passed', ['candidate_status' => 'passed']);
        $this->pay($entry);

        $this->assertTrue(CandidateEntranceStatisticsTable::notifyStudentReviewResult(
            record: $entry,
            status: 'passed',
            notificationStage: WorkflowStageType::Completed,
        ));

        $this->assertSame(['Congratulations.'], $this->student->notifications()->get()->pluck('data.body')->all());
    }

    public function test_manual_result_notification_can_replace_the_same_stage_notification(): void
    {
        $entry = $this->entry('passed', ['candidate_status' => 'passed']);
        $this->pay($entry);

        $this->assertTrue(WorkflowStageMessages::notifyConfiguredStage($entry, WorkflowStageType::AwaitingResults));
        $this->assertTrue(CandidateEntranceStatisticsTable::notifyStudentReviewResult(
            record: $entry,
            status: 'passed',
            notificationStage: WorkflowStageType::AwaitingResults,
            force: true,
        ));

        $this->assertSame(1, $this->student->notifications()->count());
        $this->assertSame(['Your exam is coming.'], $this->student->notifications()->get()->pluck('data.body')->all());
    }

    public function test_entrance_result_removes_a_stale_completed_notification(): void
    {
        $entry = $this->entry('passed', ['candidate_status' => 'passed']);
        $this->pay($entry);

        $this->assertTrue(WorkflowStageMessages::notifyConfiguredStage($entry, WorkflowStageType::Completed));
        $this->assertTrue(CandidateEntranceStatisticsTable::notifyStudentReviewResult($entry, 'passed'));

        $this->assertSame(['Your exam is coming.'], $this->student->notifications()->get()->pluck('data.body')->all());
    }

    public function test_a_configured_stage_can_be_notified_when_the_entry_status_is_external(): void
    {
        $entry = $this->entry('approved');
        $this->pay($entry);

        $this->assertTrue(WorkflowStageMessages::notifyConfiguredStage($entry, WorkflowStageType::Completed));
        $this->assertSame(['Congratulations.'], $this->student->notifications()->get()->pluck('data.body')->all());
    }

    public function test_completed_notification_waits_until_required_payment_is_paid(): void
    {
        $exitForm = $this->customForm();
        $exitForm->update([
            'statistics_menu' => StatisticsMenuOptions::EXIT_EXAM_STATISTICS,
            'passed_result_menu' => PassedResultMenuOptions::EXIT_EXAM_RESULTS,
        ]);
        WorkflowNotification::query()->where('name', 'Admission')->firstOrFail()->forms()->attach($exitForm);
        $entry = $this->entry('passed', ['candidate_status' => 'passed'], $exitForm);

        $this->assertFalse(WorkflowStageMessages::notifyConfiguredStage($entry, WorkflowStageType::Completed));
        $this->assertSame(0, $this->student->notifications()->count());

        $this->pay($entry);

        $this->assertTrue(WorkflowStageMessages::notifyConfiguredStage($entry, WorkflowStageType::Completed));
        $this->assertSame(['Congratulations.'], $this->student->notifications()->get()->pluck('data.body')->all());
    }

    public function test_candidates_see_the_status_text_and_staff_keep_the_built_in_label(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('app'));
        $entry = $this->entry('pending');
        $admin = $this->user('admin_user', 'admin');
        Role::query()->create(['name' => 'admin', 'guard_name' => 'web']);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $column = Livewire::test(ListCustomFormEntries::class)
            ->instance()
            ->getTable()
            ->getColumn('review_status')
            ->record($entry);

        $this->assertSame(__('candidate_entrance_statistics.statuses.pending'), $column->formatState($column->getState()));

        $approved = $this->entry('approved');
        $column->record($approved);
        $this->assertSame(__('candidate_entrance_statistics.statuses.accepted'), $column->formatState($column->getState()));

        $this->actingAs($this->student);
        $column->record($entry);
        $this->assertSame('Under review', $column->formatState($column->getState()));
    }

    private function stage(string $name, string $type, ?string $status = null, ?string $notification = null): array
    {
        return ['type' => 'stage', 'data' => array_filter([
            'stage_name' => $name,
            'stage_type' => $type,
            'responsible_role' => $type === 'form_submission' || $type === 'completed' ? null : 'admin',
            'status_message' => $status,
            'notification_message' => $notification,
        ])];
    }

    private function entry(string $reviewStatus, array $data = [], ?CustomForm $form = null): CustomFormEntry
    {
        $entry = CustomFormEntry::query()->forceCreate([
            'custom_form_id' => ($form ?? $this->form)->id,
            'data' => ['registration_status' => $reviewStatus === 'pending' ? 'pending' : $reviewStatus, ...$data],
            'created_by' => $this->student->id,
        ]);

        DB::table('custom_form_entries')->where('id', $entry->id)->update(['review_status' => $reviewStatus]);

        return $entry->refresh();
    }

    private function pay(CustomFormEntry $entry): void
    {
        Payment::query()->forceCreate([
            'users_id' => $this->student->id,
            'form_id' => $entry->custom_form_id,
            'custom_form_entry_id' => $entry->id,
            'receipt_number' => 'R-'.$entry->id,
            'status_payt' => 'paid',
            'amount_usd' => 10,
            'amount_kh' => 41000,
            'exchange_rate' => 4100,
            'datetime_pay' => now(),
        ]);
    }

    private function customForm(): CustomForm
    {
        return CustomForm::query()->create([
            'name' => 'Admission Form '.uniqid(),
            'slug' => 'admission-form-'.uniqid(),
            'is_active' => true,
            'menu_placement' => 'sidebar',
        ]);
    }

    private function user(string $username, string $registrationType): User
    {
        return User::query()->forceCreate([
            'registration_type' => $registrationType,
            'name' => $username,
            'username' => $username,
            'email' => $username.'@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => Hash::make('password'),
            'is_active' => true,
            'locale' => 'en',
        ]);
    }
}
