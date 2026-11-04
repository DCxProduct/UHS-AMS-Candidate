<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Tables\CustomFormEntriesTable;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class CandidateWorkflowProgressColumnTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;

    private CustomForm $form;

    private WorkflowNotification $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'Asia/Phnom_Penh']);
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        Notification::fake();
        $this->candidate = $this->user('workflow-progress-candidate', 'student');
        $this->actingAs($this->candidate);
        $this->form = CustomForm::query()->create([
            'name' => 'Progress application',
            'slug' => 'progress-application',
            'schema' => [],
            'is_active' => true,
        ]);
        $this->workflow = WorkflowNotification::query()->create([
            'name' => 'Original application workflow',
            'stages' => [
                $this->stage('Submit application', 'form_submission'),
                $this->stage('Review documents', 'review'),
                $this->stage('Pay application fee', 'payment'),
                $this->stage('Await exam results', 'awaiting_results'),
                $this->stage('Release final result', 'completed'),
            ],
        ]);
        $this->workflow->forms()->attach($this->form);
    }

    public function test_progress_column_is_shown_after_uploaded_files_for_candidates_and_hidden_from_managers(): void
    {
        $this->form->fields()->create(['name' => 'document', 'label' => 'File', 'type' => 'file_upload']);
        $columns = $this->columns();
        $names = array_map(fn ($column): string => $column->getName(), $columns);
        $column = collect($columns)->first(fn ($column): bool => $column->getName() === 'workflow_progress');

        $this->assertInstanceOf(ViewColumn::class, $column);
        $this->assertSame(__('candidate_entrance_statistics.workflow_progress'), $column->getLabel());
        $this->assertSame(array_search('uploaded_files', $names, true) + 1, array_search('workflow_progress', $names, true));

        $admin = $this->user('workflow-progress-admin', 'admin');
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
        $this->assertNotContains('workflow_progress', array_map(fn ($column): string => $column->getName(), $this->columns()));
    }

    public static function orderedProgressStates(): array
    {
        return [
            'awaiting review' => [1, 1, false, 'pending', ['completed', 'current', 'pending', 'pending', 'pending']],
            'awaiting payment' => [2, 2, false, 'approved', ['completed', 'completed', 'current', 'pending', 'pending']],
            'awaiting result' => [3, 3, false, 'approved', ['completed', 'completed', 'completed', 'current', 'pending']],
            'final result not yet released' => [4, 4, false, 'passed', ['completed', 'completed', 'completed', 'completed', 'current']],
            'final result released' => [5, null, true, 'completed', ['completed', 'completed', 'completed', 'completed', 'completed']],
        ];
    }

    #[DataProvider('orderedProgressStates')]
    public function test_ordered_progress_uses_stored_milestones_and_only_reaches_full_completion_after_final_release(
        int $completed,
        ?int $currentIndex,
        bool $finished,
        string $status,
        array $states,
    ): void {
        $entry = $this->entry($completed, $currentIndex, $finished, $status);
        $column = $this->column($entry);
        $progress = $column->getState();

        $this->assertSame(5, $progress['total']);
        $this->assertSame($completed, $progress['completed']);
        $this->assertSame($completed * 20, $progress['percent']);
        $this->assertSame($status, $progress['status']);
        $this->assertSame($states, array_column($progress['stages'], 'state'));
        $html = $column->render()->render();
        $this->assertRenderedStageIcons($html, $states);
        if ($completed > 0) {
            $this->assertStringContainsString('01-November-2026 at 4:00 PM', $html);
        }
        $this->assertSame(array_column(array_column($this->workflow->stages, 'data'), 'stage_name'), array_column($progress['stages'], 'name'));
        if ($currentIndex !== null) {
            $this->assertSame($progress['stages'][$currentIndex]['name'], $progress['label']);
        }
    }

    public function test_draft_does_not_display_completed_or_active_workflow_stages(): void
    {
        $entry = $this->entry(2, 2, false, 'draft');
        $progress = $this->progress($entry);

        $this->assertSame(0, $progress['completed']);
        $this->assertSame(0, $progress['percent']);
        $this->assertSame(array_fill(0, 5, 'pending'), array_column($progress['stages'], 'state'));
        $this->assertRenderedStageIcons($this->column($entry)->render()->render(), array_fill(0, 5, 'pending'));
    }

    public static function stoppedStatuses(): array
    {
        return [['rejected'], ['failed']];
    }

    #[DataProvider('stoppedStatuses')]
    public function test_returned_or_rejected_application_preserves_completed_milestones_without_active_stage(string $status): void
    {
        $entry = $this->entry(1, 1, false, $status);
        $progress = $this->progress($entry);

        $this->assertSame(1, $progress['completed']);
        $this->assertSame(20, $progress['percent']);
        $this->assertSame($status, $progress['status']);
        $this->assertSame(['completed', 'pending', 'pending', 'pending', 'pending'], array_column($progress['stages'], 'state'));
        $this->assertRenderedStageIcons($this->column($entry)->render()->render(), ['completed', 'pending', 'pending', 'pending', 'pending']);
        $this->assertNotSame('Review documents', $progress['label']);
        $this->assertNotSame('', $progress['label']);
    }

    public function test_application_without_configured_workflow_has_neutral_progress(): void
    {
        $entry = $this->entry(0, null, false, 'pending', hasWorkflow: false);
        $progress = $this->progress($entry);

        $this->assertSame([], $progress['stages']);
        $this->assertSame(0, $progress['total']);
        $this->assertSame(0, $progress['completed']);
        $this->assertSame(0, $progress['percent']);
        $html = $this->column($entry)->render()->render();
        $this->assertStringNotContainsString('role="progressbar"', $html);
        $this->assertStringContainsString('—', $html);
    }

    public function test_legacy_application_uses_the_same_stage_states_as_the_existing_workflow_details(): void
    {
        $entry = $this->entry(2, 2, false, 'approved', version: 1);
        $details = (new ReflectionMethod(CustomFormEntriesTable::class, 'applicationStageViewData'))->invoke(null, $entry);
        $progress = $this->progress($entry);

        $this->assertSame(array_column($details['stages'], 'name'), array_column($progress['stages'], 'name'));
        $this->assertSame(array_column($details['stages'], 'state'), array_column($progress['stages'], 'state'));
        $this->assertSame(2, $progress['completed']);
        $this->assertSame(40, $progress['percent']);
        $this->assertSame('Pay application fee', $progress['label']);
    }

    public function test_rendering_follows_the_frozen_snapshot_and_does_not_write_application_state_or_send_notifications(): void
    {
        $entry = $this->entry(2, 2, false, 'approved');
        $snapshot = $entry->workflow_snapshot;
        $this->workflow->update(['stages' => [$this->stage('Changed template stage', 'completed')]]);
        $before = $entry->fresh()->getRawOriginal();
        $notificationCount = DB::table('notifications')->count();
        $column = $this->column($entry);
        $progress = $column->getState();
        $html = $column->render()->render();

        $this->assertSame(5, $progress['total']);
        $this->assertSame('Pay application fee', $progress['label']);
        $this->assertStringContainsString('Pay application fee', $html);
        $this->assertStringNotContainsString('Changed template stage', $html);
        $this->assertSame($snapshot, $entry->fresh()->workflow_snapshot);
        $this->assertSame($before, $entry->fresh()->getRawOriginal());
        $this->assertSame($notificationCount, DB::table('notifications')->count());
        Notification::assertNothingSent();
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public static function workflowTimestampLocales(): array
    {
        return [
            'English' => ['en', '04-November-2026 at 3:03 PM'],
            'Khmer' => ['km', '០៤-វិច្ឆិកា-២០២៦ ម៉ោង ៣:០៣ ល្ងាច'],
        ];
    }

    #[DataProvider('workflowTimestampLocales')]
    public function test_completed_timestamps_follow_current_language_and_local_timezone_without_changing_saved_workflow(string $locale, string $expected): void
    {
        app()->setLocale($locale);
        $entry = $this->entry(1, 1, false, 'pending', completedTimestamp: '2026-11-04T08:03:00+00:00');
        $before = $entry->fresh()->getRawOriginal();
        $progress = $this->progress($entry);
        $html = $this->column($entry)->render()->render();

        $this->assertSame($expected, $progress['stages'][0]['completed_at']);
        $this->assertStringContainsString($expected, $html);
        $this->assertSame($before, $entry->fresh()->getRawOriginal());
        $this->assertSame('2026-11-04T08:03:00+00:00', data_get($entry->fresh()->data, 'workflow_progress.completed_at.0'));
    }

    private function columns(): array
    {
        return (new ReflectionMethod(CustomFormEntriesTable::class, 'getColumns'))->invoke(null, (string) $this->form->getKey());
    }

    private function assertRenderedStageIcons(string $html, array $states): void
    {
        $document = new \DOMDocument;
        $previousErrors = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
        $xpath = new \DOMXPath($document);
        foreach (['completed', 'current', 'pending'] as $state) {
            $expectedCount = count(array_filter($states, fn (string $value): bool => $value === $state));
            $this->assertSame($expectedCount, $xpath->query('//*[@data-workflow-'.$state.']')->length);
        }
        if ($states[0] === 'completed') {
            $this->assertSame(1, $xpath->query('(//*[@data-workflow-stage])[1]//*[@data-workflow-completed]')->length);
        }
    }

    private function progress(CustomFormEntry $entry): array
    {
        return (new ReflectionMethod(CustomFormEntriesTable::class, 'workflowProgressViewData'))->invoke(null, $entry);
    }

    private function column(CustomFormEntry $entry): ViewColumn
    {
        $column = collect($this->columns())->first(fn ($column): bool => $column->getName() === 'workflow_progress');
        $livewire = \Mockery::mock(HasTable::class);
        $livewire->shouldReceive('getTableRecordKey')->andReturnUsing(fn ($record): string => (string) $record->getKey());

        return $column->table(Table::make($livewire))->record($entry);
    }

    private function entry(int $completed, ?int $currentIndex, bool $finished, string $status, int $version = 2, bool $hasWorkflow = true, string $completedTimestamp = '2026-11-01T09:00:00+00:00'): CustomFormEntry
    {
        $completedAt = [];
        foreach (array_slice(array_keys($this->workflow->stages), 0, $completed) as $index) {
            $completedAt[$index] = $completedTimestamp;
        }
        $data = ['registration_status' => $status, 'candidate_status' => $completed >= 4 ? 'passed' : 'pending'];
        if ($version >= 2) {
            $data['workflow_progress'] = [
                'version' => 1,
                'submitted' => $status !== 'draft',
                'reviewed' => $completed >= 2,
                'payment_completed' => $completed >= 3,
                'result_passed' => $completed >= 4,
                'current_index' => $currentIndex,
                'current_type' => $currentIndex === null ? null : $this->workflow->stages[$currentIndex]['data']['stage_type'],
                'completed_at' => $completedAt,
                'ready_for_final' => $currentIndex === 4 || $finished,
                'finished' => $finished,
                'final_notified_at' => $finished ? '2026-11-01T09:00:00+00:00' : null,
            ];
        }

        return CustomFormEntry::withoutEvents(fn (): CustomFormEntry => CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $this->form->getKey(),
            'created_by' => $this->candidate->getKey(),
            'review_status' => $status,
            'reviewed_at' => $completed >= 2 ? now() : null,
            'data' => $data,
            'workflow_snapshot' => [
                'schema_version' => $version,
                'has_workflow' => $hasWorkflow,
                'workflow_id' => $hasWorkflow ? $this->workflow->getKey() : null,
                'name' => $hasWorkflow ? $this->workflow->name : null,
                'stages' => $hasWorkflow ? $this->workflow->stages : [],
                'requires_payment' => $hasWorkflow,
                'captured_at' => now()->toIso8601String(),
            ],
        ]));
    }

    private function stage(string $name, string $type): array
    {
        return ['type' => 'stage', 'data' => ['stage_name' => $name, 'stage_type' => $type, 'responsible_role' => ['admin']]];
    }

    private function user(string $name, string $registrationType): User
    {
        return User::query()->create([
            'registration_type' => $registrationType,
            'name' => $name,
            'username' => $name,
            'email' => $name.'@example.test',
            'password' => 'password',
            'is_active' => true,
            'date_of_birth' => '2000-01-01',
        ]);
    }
}
