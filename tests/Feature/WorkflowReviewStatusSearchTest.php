<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\ListCustomFormEntries;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class WorkflowReviewStatusSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;

    private CustomForm $form;

    private array $stages;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        DB::statement('PRAGMA case_sensitive_like = ON');
        $admin = $this->user('review-search-admin', 'admin');
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
        $this->candidate = $this->user('review-search-candidate', 'student');
        $this->form = CustomForm::create(['name' => 'State Search', 'slug' => 'state-search', 'is_active' => true]);
        $workflow = WorkflowNotification::create(['name' => 'State Search Template', 'stages' => [
            ['stage_type' => 'form_submission', 'submission_messages' => ['candidate_status_message' => 'Application Received']],
            ['stage_type' => 'review', 'status_message' => 'Documents Awaiting Review', 'review_actions' => [
                'accept' => ['status_message' => 'Documents Checked'], 'send_back' => ['status_message' => 'Please Correct Documents'],
                'reject' => ['status_message' => 'Documents Rejected Forever'],
            ]],
            ['stage_type' => 'verification', 'status_message' => 'Eligibility Verified'],
            ['stage_type' => 'payment', 'status_message' => 'Tuition Payment Confirmed', 'payment_statuses' => ['unpaid_status_message' => 'Tuition Payment Due']],
            ['stage_type' => 'awaiting_results', 'status_message' => 'Exam Successfully Passed', 'result_statuses' => ['pending_status_message' => 'Exam Results Pending']],
            ['stage_type' => 'completed', 'status_message' => 'Enrollment Finally Released'],
        ]]);
        $workflow->forms()->attach($this->form);
        $this->stages = $workflow->fresh()->stages;
    }

    protected function tearDown(): void
    {
        DB::statement('PRAGMA case_sensitive_like = OFF');
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public function test_ordered_submission_and_review_actions_match_only_the_label_currently_displayed(): void
    {
        $pending = $this->entry('pending', 1, 'review');
        $returned = $this->entry('rejected', 1, 'review');
        $rejected = $this->entry('failed', 1, 'review');
        $before = $pending->fresh()->getRawOriginal();
        $list = Livewire::test(ListCustomFormEntries::class);
        $this->assertLabel($list, $pending, 'Application Received');
        $this->assertLabel($list, $returned, 'Please Correct Documents');
        $this->assertLabel($list, $rejected, 'Documents Rejected Forever');
        foreach ([['APPLICATION RECEIVED', $pending], ['pLeAsE CoRrEcT', $returned], ['REJECTED FOREVER', $rejected]] as [$term, $entry]) {
            $list->searchTable($term)->assertCanSeeTableRecords([$entry])->assertCountTableRecords(1);
        }
        foreach (['Documents Checked', 'Tuition Payment Due', 'Exam Results Pending', 'Enrollment Finally Released', 'Documents Awaiting Review'] as $hidden) {
            $list->searchTable($hidden)->assertCountTableRecords(0);
        }
        $this->assertSame($before, $pending->fresh()->getRawOriginal());
    }

    public function test_ordered_unpaid_payment_keeps_review_or_verification_override_then_paid_text_takes_precedence(): void
    {
        $checked = $this->entry('approved', 3, 'payment', [0, 1]);
        $verified = $this->entry('approved', 3, 'payment', [0, 1, 2], ['verification_completed' => true]);
        $paid = $this->entry('approved', 4, 'awaiting_results', [0, 1, 2, 3], ['verification_completed' => true]);
        Payment::create(['custom_form_entry_id' => $paid->id, 'users_id' => $this->candidate->id, 'form_id' => $this->form->id, 'receipt_number' => 'SEARCH-PAYMENT', 'amount_usd' => 10, 'status_payt' => 'paid']);
        $list = Livewire::test(ListCustomFormEntries::class);
        foreach ([[$checked, 'Documents Checked'], [$verified, 'Eligibility Verified'], [$paid, 'Tuition Payment Confirmed']] as [$entry, $label]) {
            $this->assertLabel($list, $entry, $label);
            $list->searchTable(strtoupper($label))->assertCanSeeTableRecords([$entry])->assertCountTableRecords(1);
        }
        $list->searchTable('Tuition Payment Due')->assertCountTableRecords(0);
        $list->searchTable('Exam Results Pending')->assertCountTableRecords(0);
    }

    public function test_ordered_final_status_is_hidden_until_the_matching_candidate_notification_is_delivered(): void
    {
        $waiting = $this->entry('passed', 5, 'completed', [0, 1, 2, 3, 4], ['ready_for_final' => true, 'result_passed' => true]);
        $released = $this->entry('passed', null, null, [0, 1, 2, 3, 4, 5], ['finished' => true, 'ready_for_final' => true, 'result_passed' => true]);
        $this->notify($released, $this->candidate->id);
        $this->notify($waiting, auth()->id());
        $list = Livewire::test(ListCustomFormEntries::class);
        $this->assertLabel($list, $waiting, 'Exam Successfully Passed');
        $this->assertLabel($list, $released, 'Enrollment Finally Released');
        $list->searchTable('ENROLLMENT FINALLY')->assertCanSeeTableRecords([$released])->assertCanNotSeeTableRecords([$waiting])->assertCountTableRecords(1);
        $list->searchTable('EXAM SUCCESSFULLY')->assertCanSeeTableRecords([$waiting])->assertCanNotSeeTableRecords([$released])->assertCountTableRecords(1);
    }

    private function assertLabel($list, CustomFormEntry $entry, string $expected): void
    {
        $column = clone $list->instance()->getTable()->getColumn('review_status');
        $column->record($entry->fresh());
        $column->clearCachedState();
        $this->assertSame($expected, $column->formatState($column->getState()));
    }

    private function notify(CustomFormEntry $entry, int $owner): void
    {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(), 'type' => 'Filament\\Notifications\\DatabaseNotification',
            'notifiable_type' => User::class, 'notifiable_id' => $owner,
            'data' => json_encode(['viewData' => ['workflow_entry_id' => (string) $entry->id, 'workflow_stage_type' => 'completed']]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function user(string $name, string $type): User
    {
        return User::create(['registration_type' => $type, 'name' => $name, 'username' => $name, 'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true]);
    }

    private function entry(string $status, ?int $index, ?string $type, array $completed = [0], array $progress = []): CustomFormEntry
    {
        $entry = new CustomFormEntry;
        $entry->forceFill([
            'custom_form_id' => $this->form->id, 'created_by' => $this->candidate->id, 'review_status' => $status,
            'workflow_snapshot' => ['schema_version' => 2, 'has_workflow' => true, 'stages' => $this->stages],
            'data' => ['registration_status' => $status, 'workflow_progress' => [
                'version' => 1, 'submitted' => true, 'current_index' => $index, 'current_type' => $type,
                'finished' => false, 'ready_for_final' => false, 'result_passed' => false,
                'completed_at' => array_fill_keys($completed, now()->toIso8601String()), ...$progress,
            ]],
        ])->saveQuietly();

        return $entry;
    }
}
