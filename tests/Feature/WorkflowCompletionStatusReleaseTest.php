<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\CandidateEntranceStatistics\Pages\ListCandidateEntranceStatistics;
use App\Filament\Admin\Resources\ExamResults\Pages\ListExamResults;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Support\PassedResultMenuOptions;
use App\Support\StatisticsMenuOptions;
use App\Support\WorkflowStageMessages;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\ListCustomFormEntries;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkflowCompletionStatusReleaseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $candidate;
    private CustomForm $form;
    private WorkflowNotification $workflow;
    private bool $smsShouldFail = false;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        config(['services.plasgate.private_key' => 'test-key', 'services.plasgate.secret' => 'test-secret',
            'services.plasgate.base_url' => 'https://cloudapi.plasgate.com/rest', 'services.plasgate.test_phone' => null]);
        Http::fake(fn () => Http::response($this->smsShouldFail ? ['error' => 'Test failure'] : ['batchId' => 1], $this->smsShouldFail ? 503 : 200));
        Mail::fake();
        $this->admin = $this->user('completion_admin', 'admin');
        $this->admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($this->admin);
        $this->candidate = $this->user('completion_candidate', 'student', ['phone' => '012345678']);
        $this->form = CustomForm::query()->create([
            'name' => 'Completion release', 'slug' => 'completion-release', 'is_active' => true,
            'allowed_roles' => ['candidate', 'admin'],
            'statistics_menu' => StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS,
            'passed_result_menu' => PassedResultMenuOptions::EXAM_RESULTS,
        ]);
        $this->workflow = WorkflowNotification::query()->create([
            'name' => 'Completion release workflow',
            'stages' => [
                ['type' => 'stage', 'data' => ['stage_name' => 'Payment', 'stage_type' => 'payment']],
                ['type' => 'stage', 'data' => [
                    'stage_name' => 'Results', 'stage_type' => 'awaiting_results', 'responsible_role' => ['admin'],
                    'status_message' => 'បានជាប់', 'result_statuses' => ['pending_status_message' => 'កំពុងរង់ចាំ'],
                    'notification_message' => 'Your result is being prepared.', 'notification_channels' => ['system'],
                ]],
                ['type' => 'stage', 'data' => [
                    'stage_name' => 'Completed', 'stage_type' => 'completed', 'status_message' => 'ជោគជ័យ',
                    'notification_message' => 'Your final result is available.', 'notification_channels' => ['system'],
                ]],
            ],
        ]);
        $this->workflow->forms()->attach($this->form);
    }

    public function test_actual_pass_action_shows_passed_to_candidate_and_staff_until_notify_student_releases_completed(): void
    {
        $entry = $this->entry('pending');
        $statistics = Livewire::test(ListCandidateEntranceStatistics::class)
            ->assertCanSeeTableRecords([$entry])
            ->callTableAction('passed', $entry)->assertHasNoTableActionErrors();
        $entry->refresh();
        $this->assertSame('passed', $entry->review_status);
        $this->assertSame('passed', data_get($entry->data, 'candidate_status'));
        $this->assertTrue(WorkflowStageMessages::hasSentNotification($entry, WorkflowStageType::AwaitingResults));
        $this->assertFalse(WorkflowStageMessages::hasSentNotification($entry, WorkflowStageType::Completed));
        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageMessages::stageTypeFor($entry));
        $this->assertSame('បានជាប់', $this->candidateLabel($entry));
        $this->assertSame('បានជាប់', $this->staffLabel($entry));
        $statisticsColumn = $statistics->instance()->getTable()->getColumn('data.candidate_status')->record($entry);
        $this->assertSame('បានជាប់', $statisticsColumn->formatState($statisticsColumn->getState()));

        $this->notifyStudent($entry);

        $this->assertTrue(WorkflowStageMessages::hasSentNotification($entry, WorkflowStageType::Completed));
        $this->assertSame('ជោគជ័យ', $this->candidateLabel($entry));
        $this->assertSame('ជោគជ័យ', $this->staffLabel($entry));
        $this->assertSame('passed', $entry->fresh()->review_status);
        $this->assertSame('passed', data_get($entry->fresh()->data, 'candidate_status'));
        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageMessages::stageTypeFor($entry));
        $this->assertDatabaseCount('payments', 1);
        $this->assertContains('Your final result is available.', $this->candidate->notifications()->get()->pluck('data.body')->all());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_bulk_notify_releases_only_selected_entry_and_candidate(): void
    {
        $selected = $this->entry();
        $sameCandidateOtherEntry = $this->entry();
        $otherCandidate = $this->user('other_completion_candidate', 'student');
        $otherEntry = $this->entry(candidate: $otherCandidate);
        Livewire::test(ListExamResults::class)
            ->callAction('notify_all_students', arguments: ['selectedRecordKeys' => [$selected->getKey()], 'selectedRecordsCount' => 1])
            ->assertHasNoActionErrors();

        $this->assertSame('ជោគជ័យ', $this->candidateLabel($selected));
        $this->assertSame('បានជាប់', $this->candidateLabel($sameCandidateOtherEntry));
        $this->assertSame('បានជាប់', $this->candidateLabel($otherEntry));
        $this->assertFalse(WorkflowStageMessages::hasSentNotification($sameCandidateOtherEntry, WorkflowStageType::Completed));
        $this->assertSame(0, $otherCandidate->notifications()->count());
    }

    public static function unsuccessfulDeliveries(): array
    {
        return [['no_channels'], ['blank_body'], ['sms_failure']];
    }

    #[DataProvider('unsuccessfulDeliveries')]
    public function test_unsuccessful_completed_notification_does_not_release_the_success_label(string $mode): void
    {
        $stages = $this->workflow->stages;
        if ($mode === 'no_channels') {
            $stages[2]['data']['notification_channels'] = [];
        } elseif ($mode === 'blank_body') {
            $stages[2]['data']['notification_message'] = '';
        } else {
            $stages[2]['data']['notification_channels'] = ['sms'];
            $this->smsShouldFail = true;
        }
        $this->workflow->update(['stages' => $stages]);
        $entry = $this->entry();

        $this->notifyStudent($entry);

        $this->assertFalse(WorkflowStageMessages::hasSentNotification($entry, WorkflowStageType::Completed));
        $this->assertSame('បានជាប់', $this->candidateLabel($entry));
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('workflow_notification_deliveries', 0);
        $this->assertSame('passed', $entry->fresh()->review_status);
        if ($mode === 'sms_failure') {
            Http::assertSentCount(1);
        }
        Mail::assertNothingSent();
    }

    public static function nonBellChannels(): array
    {
        return [['sms'], ['email']];
    }

    #[DataProvider('nonBellChannels')]
    public function test_successful_sms_or_email_delivery_releases_success_without_a_bell_record(string $channel): void
    {
        $stages = $this->workflow->stages;
        $stages[2]['data']['notification_channels'] = [$channel];
        $this->workflow->update(['stages' => $stages]);
        $entry = $this->entry();

        $this->notifyStudent($entry);

        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseHas('workflow_notification_deliveries', [
            'custom_form_entry_id' => $entry->getKey(), 'user_id' => $this->candidate->getKey(), 'stage_type' => 'completed',
        ]);
        $this->assertTrue(WorkflowStageMessages::hasSentNotification($entry, WorkflowStageType::Completed));
        $this->assertSame('ជោគជ័យ', $this->candidateLabel($entry));
    }

    public function test_historical_completed_bell_releases_only_its_exact_entry_and_recipient(): void
    {
        $entry = $this->entry();
        $otherEntry = $this->entry();
        $otherCandidate = $this->user('historical_other_candidate', 'student');
        $wrongOwnerEntry = $this->entry(candidate: $otherCandidate);
        $this->historicalCompletedBell($entry, $otherCandidate);
        $this->assertSame('បានជាប់', $this->candidateLabel($entry));
        $this->historicalCompletedBell($entry, $this->candidate);

        $this->assertSame('ជោគជ័យ', $this->candidateLabel($entry));
        $this->assertSame('បានជាប់', $this->candidateLabel($otherEntry));
        $this->assertSame('បានជាប់', $this->candidateLabel($wrongOwnerEntry));
    }

    public function test_explicit_completed_state_also_waits_for_successful_delivery(): void
    {
        $entry = $this->entry();
        $entry->forceFill(['review_status' => 'completed', 'data' => ['registration_status' => 'completed', 'candidate_status' => 'completed']])->saveQuietly();
        $this->assertSame(WorkflowStageType::Completed, WorkflowStageMessages::stageTypeFor($entry));
        $this->assertSame('កំពុងរង់ចាំ', $this->candidateLabel($entry));
        $this->assertTrue(WorkflowStageMessages::notifyConfiguredStage($entry, WorkflowStageType::Completed));
        $this->assertSame('ជោគជ័យ', $this->candidateLabel($entry));
        $this->assertSame('completed', $entry->fresh()->review_status);
    }

    public static function unconfiguredPassedStatuses(): array
    {
        return [['missing'], ['blank']];
    }

    #[DataProvider('unconfiguredPassedStatuses')]
    public function test_missing_or_blank_passed_text_uses_default_passed_independently_of_pending_before_completed_delivery(string $mode): void
    {
        $stages = $this->workflow->stages;
        if ($mode === 'missing') {
            unset($stages[1]['data']['status_message']);
        } else {
            $stages[1]['data']['status_message'] = ' ';
        }
        $this->workflow->update(['stages' => $stages]);
        $entry = $this->entry();
        $this->assertSame('កំពុងរង់ចាំ', WorkflowStageMessages::waitingResultStatusLabel($entry));
        $this->assertNull(WorkflowStageMessages::passedResultStatusLabel($entry));
        $this->assertSame(__('candidate_entrance_statistics.statuses.passed'), $this->candidateLabel($entry));
        $this->assertSame(__('candidate_entrance_statistics.statuses.passed'), $this->staffLabel($entry));
        $this->assertFalse(WorkflowStageMessages::hasSentNotification($entry, WorkflowStageType::Completed));
        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageMessages::stageTypeFor($entry));
    }

    public static function unconfiguredCompletionStatuses(): array
    {
        return [['missing'], ['blank']];
    }

    #[DataProvider('unconfiguredCompletionStatuses')]
    public function test_no_configured_completion_status_keeps_the_existing_result_fallback(string $mode): void
    {
        $stages = $this->workflow->stages;
        if ($mode === 'missing') {
            unset($stages[2]);
        } else {
            $stages[2]['data']['status_message'] = ' ';
        }
        $this->workflow->update(['stages' => array_values($stages)]);
        $entry = $this->entry();
        $this->assertSame('បានជាប់', $this->candidateLabel($entry));
    }

    private function notifyStudent(CustomFormEntry $entry): void
    {
        $this->actingAs($this->admin);
        Livewire::test(ListExamResults::class)->assertCanSeeTableRecords([$entry])
            ->callTableAction('notify_student', $entry)->assertHasNoTableActionErrors();
    }

    private function candidateLabel(CustomFormEntry $entry): string
    {
        $this->actingAs($this->admin);
        $column = Livewire::test(ListCustomFormEntries::class)->instance()->getTable()->getColumn('review_status')->record($entry->fresh());
        $this->actingAs(User::query()->findOrFail($entry->created_by));

        return $column->formatState($column->getState());
    }

    private function staffLabel(CustomFormEntry $entry): string
    {
        $this->actingAs($this->admin);
        $column = Livewire::test(ListCustomFormEntries::class)->instance()->getTable()->getColumn('review_status')->record($entry->fresh());

        return $column->formatState($column->getState());
    }

    private function historicalCompletedBell(CustomFormEntry $entry, User $recipient): void
    {
        Notification::make()->title('Historical result')->body('Previously sent')
            ->viewData(['workflow_entry_id' => (string) $entry->getKey(), 'workflow_stage_type' => 'completed'])
            ->sendToDatabase($recipient);
        DB::table('notifications')->where('notifiable_id', $recipient->getKey())->update(['created_at' => now()->subDays(3)]);
    }

    private function entry(string $status = 'passed', ?User $candidate = null): CustomFormEntry
    {
        $entry = CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $this->form->getKey(), 'created_by' => ($candidate ?? $this->candidate)->getKey(),
            'review_status' => $status === 'pending' ? 'approved' : 'passed',
            'data' => ['registration_status' => $status === 'pending' ? 'approved' : 'passed', 'candidate_status' => $status],
        ]);
        Payment::query()->create([
            'custom_form_entry_id' => $entry->getKey(), 'users_id' => $entry->created_by, 'form_id' => $this->form->getKey(),
            'receipt_number' => 'COMPLETION-'.$entry->getKey(), 'status_payt' => 'paid', 'amount_usd' => 5, 'datetime_pay' => now(),
        ]);

        return $entry;
    }

    private function user(string $username, string $type, array $attributes = []): User
    {
        return User::query()->forceCreate(array_replace([
            'registration_type' => $type, 'name' => $username, 'username' => $username,
            'email' => $username.'@example.test', 'date_of_birth' => '2000-01-01', 'password' => 'password', 'is_active' => true,
        ], $attributes));
    }
}
