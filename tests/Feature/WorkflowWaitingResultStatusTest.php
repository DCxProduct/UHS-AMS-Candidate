<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\CandidateEntranceStatistics\Pages\ListCandidateEntranceStatistics;
use App\Filament\Admin\Resources\CandidateEntranceStatistics\Tables\CandidateEntranceStatisticsTable;
use App\Filament\Admin\Resources\CandidateExitStatistics\Pages\ListCandidateExitStatistics;
use App\Filament\Admin\Resources\CandidateExitStatistics\Tables\CandidateExitStatisticsTable;
use App\Models\CandidateExitStatistic;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Support\StatisticsMenuOptions;
use App\Enums\WorkflowStageType;
use App\Support\WorkflowStageMessages;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkflowWaitingResultStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;
    private CustomForm $form;
    private WorkflowNotification $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        $admin = $this->user('waiting_result_admin', 'admin');
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
        $this->candidate = $this->user('waiting_result_candidate', 'student');
        $this->form = CustomForm::query()->create([
            'name' => 'Waiting results', 'slug' => 'waiting-results', 'is_active' => true,
            'statistics_menu' => StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS, 'requires_payment' => true,
        ]);
        $this->workflow = WorkflowNotification::query()->create([
            'name' => 'Waiting result workflow',
            'stages' => [
                ['type' => 'stage', 'data' => ['stage_name' => 'Payment', 'stage_type' => 'payment', 'status_message' => 'Paid label']],
                ['type' => 'stage', 'data' => [
                    'stage_name' => 'Results', 'stage_type' => 'awaiting_results',
                    'responsible_role' => ['admin'],
                    'status_message' => 'បានជាប់',
                    'result_statuses' => ['pending_status_message' => 'សូមរង់ចាំលទ្ធផល'],
                ]],
                ['type' => 'stage', 'data' => ['stage_name' => 'Completed', 'stage_type' => 'completed', 'status_message' => 'Completed label']],
            ],
        ]);
        $this->workflow->forms()->attach($this->form);
    }

    public function test_paid_pending_result_uses_awaiting_results_text_and_preserves_payment_eligibility_and_passed_label(): void
    {
        $waiting = $this->entry('pending');
        $passed = $this->entry('passed');
        $unpaid = $this->entry('pending', $this->user('unpaid_result_candidate', 'student'));
        $this->pay($waiting);
        $this->pay($passed);

        $component = Livewire::test(ListCandidateEntranceStatistics::class)
            ->assertCanSeeTableRecords([$waiting, $passed])->assertCanNotSeeTableRecords([$unpaid])
            ->assertTableColumnStateSet('data.candidate_status', 'pending', $waiting)
            ->assertTableColumnStateSet('data.candidate_status', 'passed', $passed);
        $column = $component->instance()->getTable()->getColumn('data.candidate_status');
        $column->record($waiting);
        $this->assertSame('សូមរង់ចាំលទ្ធផល', $column->formatState($column->getState()));
        $column->record($passed);
        $this->assertSame('បានជាប់', $column->formatState($column->getState()));
        $this->assertSame('approved', $waiting->fresh()->review_status);
        $this->assertSame('pending', data_get($waiting->fresh()->data, 'candidate_status'));

        $component->set('tableFilters.application_review_filters.review_status', 'pending')
            ->assertCanSeeTableRecords([$waiting])->assertCanNotSeeTableRecords([$passed]);
        $component->set('tableFilters.application_review_filters.review_status', 'passed')
            ->assertCanSeeTableRecords([$passed])->assertCanNotSeeTableRecords([$waiting]);
        $this->assertDatabaseCount('notifications', 0);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public static function waitingStates(): array
    {
        return [['pending'], [null], ['']];
    }

    public function test_generic_application_status_shows_paid_then_passed_until_completion_is_released(): void
    {
        $entry = $this->entry('pending');
        $this->pay($entry);

        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageMessages::stageTypeFor($entry));
        $this->assertSame('Paid label', WorkflowStageMessages::statusText($entry));
        $this->assertSame('approved', $entry->fresh()->review_status);

        $entry->forceFill([
            'review_status' => 'passed',
            'data' => ['registration_status' => 'passed', 'candidate_status' => 'passed'],
        ])->saveQuietly();

        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageMessages::stageTypeFor($entry));
        $this->assertSame('បានជាប់', WorkflowStageMessages::statusText($entry));
        $stages = $this->workflow->stages;
        $stages[2]['data']['notification_message'] = 'The final result is now available.';
        $this->workflow->update(['stages' => $stages]);
        $this->assertTrue(WorkflowStageMessages::notifyConfiguredStage($entry, WorkflowStageType::Completed));
        $this->assertSame('Completed label', WorkflowStageMessages::statusText($entry));
    }

    public function test_generic_application_status_with_blank_paid_label_uses_pending_fallback_and_preserves_legacy_result_labels(): void
    {
        $entry = $this->entry('pending');
        $this->pay($entry);
        $stages = $this->workflow->stages;
        $stages[0]['data']['status_message'] = ' ';
        $stages[1]['data']['result_statuses']['pending_status_message'] = ' ';
        $this->workflow->update(['stages' => $stages]);

        $this->assertSame(__('candidate_entrance_statistics.statuses.pending'), WorkflowStageMessages::statusText($entry));
        $this->assertSame('approved', $entry->fresh()->review_status);
        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageMessages::stageTypeFor($entry));

        unset($stages[1]['data']['result_statuses']);
        $this->workflow->update(['stages' => $stages]);
        $this->assertSame('បានជាប់', WorkflowStageMessages::statusText($entry));
    }

    public function test_paid_entry_with_a_passed_candidate_result_shows_passed_before_completion_delivery_instead_of_paid_text(): void
    {
        $entry = $this->entry('passed');
        $this->pay($entry);

        $this->assertSame('approved', $entry->review_status);
        $this->assertSame('passed', data_get($entry->data, 'candidate_status'));
        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageMessages::stageTypeFor($entry));
        $this->assertSame('បានជាប់', WorkflowStageMessages::statusText($entry));
        $stages = $this->workflow->stages;
        $stages[2]['data']['notification_message'] = 'Final result released.';
        $this->workflow->update(['stages' => $stages]);

        $this->assertTrue(WorkflowStageMessages::notifyConfiguredStage($entry, WorkflowStageType::Completed));
        $this->assertSame('Completed label', WorkflowStageMessages::statusText($entry));
        $this->assertSame('approved', $entry->fresh()->review_status);
    }

    public function test_actual_passed_action_changes_pending_label_to_the_configured_passed_label(): void
    {
        $entry = $this->entry('pending');
        $this->pay($entry);
        $component = Livewire::test(ListCandidateEntranceStatistics::class)
            ->assertTableColumnStateSet('data.candidate_status', 'pending', $entry);
        $column = $component->instance()->getTable()->getColumn('data.candidate_status')->record($entry);
        $this->assertSame('សូមរង់ចាំលទ្ធផល', $column->formatState($column->getState()));

        $component->callTableAction('passed', $entry)->assertHasNoTableActionErrors()
            ->assertTableColumnStateSet('data.candidate_status', 'passed', $entry);
        $fresh = $entry->fresh();
        $column = $component->instance()->getTable()->getColumn('data.candidate_status')->record($fresh);
        $this->assertSame('បានជាប់', $column->formatState($column->getState()));
        $this->assertSame('passed', data_get($fresh->data, 'candidate_status'));
        $this->assertSame('passed', $fresh->review_status);

        $formatted = (new ReflectionMethod(CandidateEntranceStatisticsTable::class, 'excelRows'))->invoke(null, [$fresh], ['data.candidate_status']);
        $clean = (new ReflectionMethod(CandidateEntranceStatisticsTable::class, 'cleanDataRows'))->invoke(null, [$fresh], ['data.candidate_status']);
        $this->assertSame('បានជាប់', $formatted[1][0]);
        $this->assertSame('passed', $clean[1][0]);
    }

    public function test_blank_passed_label_keeps_the_default_passed_text_and_does_not_use_pending_text(): void
    {
        $entry = $this->entry('passed');
        $stages = $this->workflow->stages;
        $stages[1]['data']['status_message'] = ' ';
        $this->workflow->update(['stages' => $stages]);
        $this->assertNull(WorkflowStageMessages::passedResultStatusLabel($entry));
        $formatted = (new ReflectionMethod(CandidateEntranceStatisticsTable::class, 'excelRows'))->invoke(null, [$entry], ['data.candidate_status']);
        $this->assertSame(__('candidate_entrance_statistics.statuses.passed'), $formatted[1][0]);
    }

    #[DataProvider('waitingStates')]
    public function test_pending_and_missing_result_exports_use_configured_text_but_clean_export_keeps_raw_state(?string $status): void
    {
        $entry = $this->entry($status);
        $formatted = (new ReflectionMethod(CandidateEntranceStatisticsTable::class, 'excelRows'))
            ->invoke(null, [$entry], ['data.candidate_status']);
        $clean = (new ReflectionMethod(CandidateEntranceStatisticsTable::class, 'cleanDataRows'))
            ->invoke(null, [$entry], ['data.candidate_status']);

        $this->assertSame('សូមរង់ចាំលទ្ធផល', $formatted[1][0]);
        $this->assertSame((string) $status, $clean[1][0]);
    }

    public static function locales(): array
    {
        return [['en'], ['km']];
    }

    #[DataProvider('locales')]
    public function test_blank_unassigned_and_legacy_waiting_fields_keep_existing_translated_fallback(string $locale): void
    {
        app()->setLocale($locale);
        $entry = $this->entry('pending');
        $stages = $this->workflow->stages;
        $stages[1]['data']['result_statuses']['pending_status_message'] = ' ';
        $this->workflow->update(['stages' => $stages]);
        $this->assertNull(WorkflowStageMessages::waitingResultStatusLabel($entry));
        $formatted = (new ReflectionMethod(CandidateEntranceStatisticsTable::class, 'excelRows'))
            ->invoke(null, [$entry], ['data.candidate_status']);
        $this->assertSame(__('candidate_entrance_statistics.statuses.pending'), $formatted[1][0]);
        $this->assertSame('បានជាប់', WorkflowStageMessages::passedResultStatusLabel($entry));

        unset($stages[1]['data']['result_statuses']);
        $this->workflow->update(['stages' => $stages]);
        $this->assertNull(WorkflowStageMessages::waitingResultStatusLabel($entry));

        unset($stages[1]);
        $this->workflow->update(['stages' => array_values($stages)]);
        $this->assertNull(WorkflowStageMessages::waitingResultStatusLabel($entry));
        $this->workflow->forms()->detach($this->form);
        $this->assertNull(WorkflowStageMessages::waitingResultStatusLabel($entry));
    }

    public function test_exit_statistics_reuse_linked_awaiting_stage_for_pending_badge_and_formatted_export_only(): void
    {
        $entry = $this->entry('pending');
        $waiting = CandidateExitStatistic::query()->create([
            'custom_form_entry_id' => $entry->getKey(), 'candidate_status' => 'pending',
            'form_type' => 'exit-test',
        ]);
        $passedEntry = $this->entry('passed');
        $passed = CandidateExitStatistic::query()->create([
            'custom_form_entry_id' => $passedEntry->getKey(), 'candidate_status' => 'passed',
            'form_type' => 'exit-test',
        ]);
        $this->pay($entry);
        $this->pay($passedEntry);
        $unlinked = CandidateExitStatistic::query()->create(['candidate_status' => 'pending', 'form_type' => 'unlinked-test']);
        $viewer = $this->user('exit_waiting_viewer', 'admin');
        $viewer->givePermissionTo(Permission::findOrCreate('ViewAny:CandidateExitStatistic', 'web'));
        $this->actingAs($viewer);
        $component = Livewire::test(ListCandidateExitStatistics::class)
            ->assertCanSeeTableRecords([$waiting, $passed])
            ->assertCanNotSeeTableRecords([$unlinked]);
        $column = $component->instance()->getTable()->getColumn('candidate_status');
        $column->record($waiting);
        $this->assertSame('សូមរង់ចាំលទ្ធផល', $column->formatState($column->getState()));
        $column->record($passed);
        $this->assertSame('បានជាប់', $column->formatState($column->getState()));
        $column->record($unlinked);
        $this->assertSame(__('candidate_exit_statistics.options.candidate_status.pending'), $column->formatState($column->getState()));

        $formatted = (new ReflectionMethod(CandidateExitStatisticsTable::class, 'excelRows'))->invoke(null, [$waiting], ['candidate_status']);
        $clean = (new ReflectionMethod(CandidateExitStatisticsTable::class, 'cleanDataRows'))->invoke(null, [$waiting], ['candidate_status']);
        $this->assertSame('សូមរង់ចាំលទ្ធផល', $formatted[1][0]);
        $this->assertSame('pending', $clean[1][0]);
    }

    private function entry(?string $status, ?User $candidate = null): CustomFormEntry
    {
        return CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $this->form->getKey(), 'created_by' => ($candidate ?? $this->candidate)->getKey(),
            'review_status' => 'approved', 'data' => ['registration_status' => 'approved', 'candidate_status' => $status],
        ]);
    }

    private function pay(CustomFormEntry $entry): void
    {
        Payment::query()->create([
            'custom_form_entry_id' => $entry->getKey(), 'users_id' => $entry->created_by,
            'form_id' => $entry->custom_form_id, 'receipt_number' => 'WAITING-'.$entry->getKey(),
            'status_payt' => 'paid', 'amount_usd' => 5, 'datetime_pay' => now(),
        ]);
    }

    private function user(string $username, string $type): User
    {
        return User::query()->forceCreate([
            'registration_type' => $type, 'name' => $username, 'username' => $username,
            'email' => $username.'@example.test', 'date_of_birth' => '2000-01-01',
            'password' => 'password', 'is_active' => true,
        ]);
    }
}
