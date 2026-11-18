<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Support\LegacyWorkflowStatusSearch;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\ListCustomFormEntries;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class LegacyWorkflowStatusSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private CustomForm $form;

    private array $stages;

    private ?Table $table = null;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');
        Filament::setCurrentPanel('app');
        DB::statement('PRAGMA case_sensitive_like = ON');
        $staff = User::create(['registration_type' => 'admin', 'name' => 'Legacy search staff', 'username' => 'legacy-search-staff', 'password' => 'password', 'date_of_birth' => '1990-01-01', 'is_active' => true]);
        $staff->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($staff);
        $this->owner = User::create(['registration_type' => 'student', 'name' => 'Legacy search owner', 'username' => 'legacy-search-owner', 'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true]);
        $this->form = CustomForm::create(['name' => 'Legacy status search', 'slug' => 'legacy-status-search', 'is_active' => true]);
        $this->stages = [
            ['data' => ['stage_type' => 'form_submission', 'submission_messages' => ['candidate_status_message' => 'Submission received']]],
            ['data' => ['stage_type' => 'review', 'status_message' => 'Review stage hidden label', 'review_actions' => [
                'accept' => ['status_message' => 'Checked by staff'],
                'send_back' => ['status_message' => 'Update details'],
                'reject' => ['status_message' => 'Final decline'],
            ]]],
            ['data' => ['stage_type' => 'payment', 'status_message' => 'Paid confirmed', 'payment_statuses' => ['unpaid_status_message' => 'Unpaid configuration label']]],
            ['data' => ['stage_type' => 'awaiting_results', 'status_message' => 'Passed published', 'result_statuses' => ['pending_status_message' => 'Waiting results']]],
            ['data' => ['stage_type' => 'completed', 'status_message' => 'Final completion released']],
        ];
        $workflow = WorkflowNotification::create(['name' => 'Legacy search workflow', 'stages' => $this->stages]);
        $workflow->forms()->attach($this->form);
    }

    protected function tearDown(): void
    {
        DB::statement('PRAGMA case_sensitive_like = OFF');
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public function test_search_matches_only_the_current_submission_review_or_payment_label(): void
    {
        $pending = $this->entry('pending');
        $returned = $this->entry('rejected');
        $failed = $this->entry('failed');
        $checked = $this->entry('approved');
        $paid = $this->entry('approved');
        Payment::create(['custom_form_entry_id' => $paid->id, 'users_id' => $this->owner->id, 'form_id' => $this->form->id, 'receipt_number' => 'LEGACY-STATUS-PAID', 'amount_usd' => 1, 'status_payt' => 'paid']);

        foreach ([[$pending, 'Submission received'], [$returned, 'Update details'], [$failed, 'Final decline'], [$checked, 'Checked by staff'], [$paid, 'Paid confirmed']] as [$entry, $label]) {
            $this->assertSame($label, $this->display($entry));
            $this->assertSame([$entry->id], $this->search(strtoupper($label)), $label);
        }
        $this->assertSame([], $this->search('Review stage hidden label'));
        $this->assertSame([], $this->search('Unpaid configuration label'));
        $this->assertSame([], $this->search('Final completion released'));
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_completion_search_waits_for_the_matching_owner_notification_and_preserves_outer_scopes(): void
    {
        $passed = $this->entry('passed', ['candidate_status' => 'passed']);
        $waiting = $this->entry('passed', ['candidate_status' => 'pending']);
        $otherOwner = User::create(['registration_type' => 'student', 'name' => 'Other owner', 'username' => 'legacy-other-owner', 'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true]);
        $this->notification($passed, $otherOwner->id);
        $this->assertSame('Passed published', $this->display($passed));
        $this->assertSame('Waiting results', $this->display($waiting));
        $this->assertSame([$passed->id], $this->search('PASSED PUBLISHED'));
        $this->assertSame([$waiting->id], $this->search('waiting RESULTS'));
        $this->assertSame([], $this->search('Final completion released'));

        $this->notification($passed, $this->owner->id);
        $this->assertSame('Final completion released', $this->display($passed->fresh()));
        $this->assertSame([$passed->id], $this->search('FINAL COMPLETION RELEASED'));
        $this->assertSame([], $this->search('Passed published'));
        $query = CustomFormEntry::query()->where('id', $waiting->id);
        LegacyWorkflowStatusSearch::apply($query, 'Final completion released');
        $this->assertSame([], $query->pluck('id')->all());
    }

    public function test_empty_result_status_configuration_shows_pending_instead_of_the_passed_stage_label(): void
    {
        $stages = $this->stages;
        $stages[3]['data']['result_statuses'] = [];
        $entry = $this->entry('passed', ['candidate_status' => 'pending'], $stages);

        $this->assertSame(__('candidate_entrance_statistics.statuses.pending'), $this->display($entry));
        $this->assertSame([$entry->id], $this->search(__('candidate_entrance_statistics.statuses.pending')));
        $this->assertSame([], $this->search('Passed published'));
    }

    private function entry(string $status, array $data = [], ?array $stages = null): CustomFormEntry
    {
        $entry = new CustomFormEntry;
        $entry->forceFill([
            'custom_form_id' => $this->form->id, 'created_by' => $this->owner->id,
            'review_status' => $status, 'data' => ['registration_status' => $status, ...$data],
            'workflow_snapshot' => ['schema_version' => 1, 'has_workflow' => true, 'stages' => $stages ?? $this->stages],
        ])->saveQuietly();

        return $entry;
    }

    private function search(string $term): array
    {
        return LegacyWorkflowStatusSearch::apply(CustomFormEntry::query(), $term)->orderBy('id')->pluck('id')->all();
    }

    private function display(CustomFormEntry $entry): string
    {
        $this->table ??= Livewire::test(ListCustomFormEntries::class)->instance()->getTable();
        $column = clone $this->table->getColumn('review_status');
        $column->record($entry);
        $column->clearCachedState();

        return $column->formatState($column->getState());
    }

    private function notification(CustomFormEntry $entry, int $owner): void
    {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(), 'type' => 'workflow-test', 'notifiable_type' => User::class,
            'notifiable_id' => $owner, 'data' => json_encode(['viewData' => ['workflow_entry_id' => (string) $entry->id, 'workflow_stage_type' => 'completed']]),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
