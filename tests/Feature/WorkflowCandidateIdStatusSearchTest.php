<?php

namespace Tests\Feature;

use App\Models\AmsPaymentRequest;
use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\CandidateEntranceStatistics\Pages\ListCandidateEntranceStatistics;
use App\Models\Major;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowButton;
use App\Models\WorkflowNotification;
use App\Services\WorkflowCandidateIdGenerator;
use App\Services\WorkflowCustomButtonService;
use App\Support\WorkflowStageMessages;
use App\Support\WorkflowEntrySnapshot;
use App\Support\WorkflowStageProgression;
use App\Support\StatisticsMenuOptions;
use App\Support\PassedResultMenuOptions;
use App\Support\WorkflowStatusSearch;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\ListCustomFormEntries;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkflowCandidateIdStatusSearchTest extends TestCase
{
    use RefreshDatabase;

    public static function locales(): array
    {
        return [['en'], ['km']];
    }

    #[DataProvider('locales')]
    public function test_generation_search_matches_pending_paid_and_finished_frozen_labels_without_mutation(string $locale): void
    {
        app()->setLocale($locale);
        DB::statement('PRAGMA case_sensitive_like = ON');
        $form = CustomForm::query()->create(['name' => 'Generate ID search', 'slug' => 'generate-id-search', 'is_active' => true]);
        $otherForm = CustomForm::query()->create(['name' => 'Other generation', 'slug' => 'other-generation-search', 'is_active' => true]);
        $paidLabel = $locale === 'km' ? 'បានបង់ប្រាក់មុនបង្កើតលេខ' : 'Frozen Payment Before ID';
        $generatedLabel = $locale === 'km' ? 'បង្កើតលេខបេក្ខជនរួចរាល់' : 'Frozen Candidate ID Issued';
        $stages = [
            ['stage_type' => 'form_submission'],
            ['stage_type' => 'payment', 'status_message' => $paidLabel],
            ['stage_type' => 'candidate_id_generation', 'status_message' => $generatedLabel],
        ];
        $pending = $this->entry($form, $stages);
        $paid = $this->entry($form, $stages);
        AmsPaymentRequest::query()->forceCreate([
            'custom_form_entry_id' => $paid->id, 'ams_request_id' => 'generation-search-paid', 'payment_status' => 'paid', 'sync_status' => 'synced',
        ]);
        $finished = $this->entry($form, $stages, true);
        $stages[2]['status_message'] = ' ';
        $fallback = $this->entry($form, $stages, true);
        $outside = $this->entry($otherForm, $stages, true);
        $before = collect([$pending, $paid, $finished, $fallback, $outside])
            ->mapWithKeys(fn (CustomFormEntry $entry): array => [$entry->id => $entry->fresh()->getRawOriginal()])->all();

        foreach ([
            [__('workflow_notifications.status_defaults.candidate_id_generation'), $pending],
            [$paidLabel, $paid],
            [$generatedLabel, $finished],
            [__('workflow_notifications.status_defaults.candidate_id_generated'), $fallback],
        ] as [$label, $entry]) {
            $this->assertSame($label, WorkflowStageMessages::statusText($entry));
            $query = CustomFormEntry::query()->where('custom_form_id', $form->id);
            WorkflowStatusSearch::applyReview($query, $locale === 'en' ? strtoupper($label) : $label);
            $this->assertSame([$entry->id], $query->pluck('id')->all());
        }
        foreach ([$pending, $paid, $finished, $fallback, $outside] as $entry) {
            $this->assertSame($before[$entry->id], $entry->fresh()->getRawOriginal());
        }
    }

    #[DataProvider('locales')]
    public function test_generation_status_remains_visible_and_searchable_until_the_separate_receipt_is_clicked(string $locale): void
    {
        Filament::setCurrentPanel('app');
        app()->setLocale($locale);
        $admin = User::query()->create(['registration_type' => 'admin', 'name' => 'generation-status-admin',
            'username' => 'generation-status-admin', 'email' => 'generation-status-admin@example.test',
            'date_of_birth' => '2000-01-01', 'password' => 'password', 'is_active' => true]);
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
        $form = CustomForm::query()->create(['name' => 'Separate receipt search', 'slug' => 'separate-receipt-search', 'is_active' => true]);
        $otherForm = CustomForm::query()->create(['name' => 'Outside receipt search', 'slug' => 'outside-receipt-search', 'is_active' => true]);
        $button = WorkflowButton::query()->create(['label_en' => 'Receipt only', 'action_key' => null]);
        $generated = $locale === 'km' ? 'បានបង្កើតលេខសម្រាប់ពាក្យនេះ' : 'Frozen ID Generated Before Receipt';
        $receipt = $locale === 'km' ? 'បានទទួលពាក្យដាច់ដោយឡែក' : 'Frozen Separate Receipt Recorded';
        $stages = [
            ['stage_type' => 'form_submission'],
            ['stage_type' => 'payment'],
            ['stage_type' => 'candidate_id_generation', 'status_message' => $generated],
            ['stage_type' => 'custom', 'responsible_role' => ['admin'], 'buttons' => [$button->toDefinition()],
                'notification_channels' => [], 'submission_messages' => ['candidate_status_message' => $receipt]],
        ];
        if ($locale === 'km') {
            $stages = array_map(fn (array $stage): array => ['type' => 'stage', 'data' => $stage], $stages);
        }
        $entry = $this->receiptEntry($form, $stages);
        $outside = $this->receiptEntry($otherForm, $stages);
        $notGenerated = $this->receiptEntry($form, $stages);
        $incomplete = $notGenerated->data;
        unset($incomplete['workflow_progress']['completed_at'][2]);
        $notGenerated->forceFill(['data' => $incomplete])->saveQuietly();
        $otherStages = $stages;
        $previousPath = $locale === 'km' ? '2.data.stage_type' : '2.stage_type';
        data_set($otherStages, $previousPath, 'review');
        $ordinaryCustom = $this->receiptEntry($form, $otherStages);
        $snapshot = $entry->getRawOriginal('workflow_snapshot');
        $beforeSearch = $entry->getRawOriginal();
        $this->assertSame($generated, WorkflowStageMessages::statusText($entry));
        $this->assertSame([$entry->id], $this->reviewMatches($form, $generated));
        $this->assertSame([], $this->reviewMatches($form, $receipt));
        $this->assertNull(WorkflowStageMessages::statusText($notGenerated));
        $this->assertNull(WorkflowStageMessages::statusText($ordinaryCustom));
        $this->assertSame($beforeSearch, $entry->fresh()->getRawOriginal());

        $this->assertTrue(app(WorkflowCustomButtonService::class)->execute($entry, 3, $button->id));

        $afterClick = $entry->fresh()->getRawOriginal();
        $this->assertSame($receipt, WorkflowStageMessages::statusText($entry));
        $this->assertSame([$entry->id], $this->reviewMatches($form, $receipt));
        $this->assertSame([], $this->reviewMatches($form, $generated));
        $this->assertSame($generated, WorkflowStageMessages::statusText($outside));
        $this->assertSame($snapshot, $entry->getRawOriginal('workflow_snapshot'));
        $this->assertSame('BNM-9000', $entry->candidate_id);
        $this->assertSame($afterClick, $entry->fresh()->getRawOriginal());
        Filament::setCurrentPanel(null);
    }

    public static function generationFollowups(): array
    {
        $orders = [
            'receipt after generation' => ['form_submission', 'review', 'payment', 'candidate_id_generation', 'verification', 'awaiting_results', 'completed'],
            'results after generation' => ['form_submission', 'review', 'payment', 'verification', 'candidate_id_generation', 'awaiting_results', 'completed'],
            'receipt then review' => ['form_submission', 'payment', 'candidate_id_generation', 'verification', 'review', 'awaiting_results', 'completed'],
        ];
        $cases = [];
        foreach (['en', 'km'] as $locale) {
            foreach ($orders as $name => $order) {
                $cases[$name.' in '.$locale] = [$locale, $order];
            }
        }

        return $cases;
    }

    #[DataProvider('generationFollowups')]
    public function test_completed_generation_replaces_paid_status_until_the_next_action_and_search_matches_the_table(string $locale, array $order): void
    {
        Filament::setCurrentPanel('app');
        app()->setLocale($locale);
        Http::fake();
        Mail::fake();
        $admin = User::query()->create(['registration_type' => 'admin', 'name' => 'generation-followup-admin',
            'username' => 'generation-followup-admin', 'email' => 'generation-followup-admin@example.test',
            'date_of_birth' => '2000-01-01', 'password' => 'password', 'is_active' => true]);
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
        $candidate = User::query()->create(['registration_type' => 'student', 'name' => 'generation-followup-candidate',
            'username' => 'generation-followup-candidate', 'email' => 'generation-followup-candidate@example.test',
            'date_of_birth' => '2000-01-01', 'password' => 'password', 'is_active' => true]);
        $major = Major::query()->create(['key' => 'generation-followup', 'abbreviation' => 'STID', 'name_en' => 'Generation followup major', 'is_active' => true]);
        $formValues = ['is_active' => true, 'statistics_menu' => StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS, 'passed_result_menu' => PassedResultMenuOptions::EXAM_RESULTS];
        $form = CustomForm::query()->create(['name' => 'Generation followed by receipt', 'slug' => 'generation-followup', ...$formValues]);
        $outsideForm = CustomForm::query()->create(['name' => 'Outside generation followup', 'slug' => 'outside-generation-followup', ...$formValues]);
        $labels = $locale === 'km'
            ? ['paid' => 'បានបង់ប្រាក់ជោគជ័យ', 'generated' => 'បានបង្កើត ID', 'receipt' => 'បានទទួលពាក្យដាច់ដោយឡែក', 'passed' => 'បានប្រឡងជាប់', 'submitted' => 'បានដាក់ពាក្យរួច']
            : ['paid' => 'Frozen payment successful', 'generated' => 'Frozen candidate ID generated', 'receipt' => 'Frozen application received', 'passed' => 'Frozen exam passed', 'submitted' => 'Frozen application submitted'];
        $stages = array_map(fn (string $type): array => ['type' => 'stage', 'data' => [
            'stage_type' => $type, 'stage_name' => 'Configured '.$type, 'responsible_role' => ['admin'], 'notification_channels' => [],
            'status_message' => match ($type) {
                'payment' => $labels['paid'], 'candidate_id_generation' => $labels['generated'],
                'verification' => $labels['receipt'], 'awaiting_results' => $labels['passed'], default => null,
            },
            'submission_messages' => ['candidate_status_message' => $labels['submitted']],
            'payment_source' => 'ams', 'statistics_menu' => StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS,
            'passed_result_menu' => PassedResultMenuOptions::EXAM_RESULTS,
        ]], $order);
        $workflow = WorkflowNotification::query()->create(['name' => 'Generation followup template', 'stages' => $stages]);
        $workflow->forms()->attach([$form->id, $outsideForm->id]);
        $entry = $this->generationEntry($form, $order, $major, $candidate);
        $outside = $this->generationEntry($outsideForm, $order, $major, $candidate);
        $snapshot = $entry->getRawOriginal('workflow_snapshot');
        $changed = $workflow->stages;
        data_set($changed, array_search('candidate_id_generation', $order, true).'.data.status_message', 'Changed live generation status');
        $workflow->update(['stages' => $changed]);
        $this->assertSame($labels['paid'], WorkflowStageMessages::statusText($entry));
        $this->assertSame('STID-0001', app(WorkflowCandidateIdGenerator::class)->generate($outside));

        $table = Livewire::test(ListCustomFormEntries::class)->assertCanSeeTableRecords([$entry])
            ->assertTableActionVisible('generate_candidate_id', $entry)
            ->callTableAction('generate_candidate_id', $entry)->assertHasNoTableActionErrors();

        $entry->refresh();
        $this->assertSame($labels['generated'], WorkflowStageMessages::statusText($entry));
        $column = $table->instance()->getTable()->getColumn('review_status')->record($entry);
        $column->clearCachedState();
        $this->assertSame($labels['generated'], $column->formatState($column->getState()));
        $this->assertSame([$entry->id], $this->reviewMatches($form, $labels['generated']));
        $this->assertSame([], $this->reviewMatches($form, $labels['paid']));
        $this->assertSame([], $this->reviewMatches($form, 'Changed live generation status'));
        if ($locale === 'en' && array_search('candidate_id_generation', $order, true) === 3) {
            $cursor = $entry->data;
            $cursor['workflow_progress']['current_index'] = (string) $cursor['workflow_progress']['current_index'];
            $entry->forceFill(['data' => $cursor])->saveQuietly();
            $entry->refresh();
            $this->assertSame($labels['generated'], WorkflowStageMessages::statusText($entry));
            $this->assertSame([$entry->id], $this->reviewMatches($form, $labels['generated']));
            foreach ([null, 'unsupported_stage'] as $invalidType) {
                $invalid = $cursor;
                $invalid['workflow_progress']['current_type'] = $invalidType;
                $entry->forceFill(['data' => $invalid])->saveQuietly();
                $entry->refresh();
                $beforeInvalidSearch = $entry->getRawOriginal();
                $this->assertNull(WorkflowStageMessages::statusText($entry));
                $this->assertSame([], $this->reviewMatches($form, $labels['generated']));
                $this->assertSame($beforeInvalidSearch, $entry->fresh()->getRawOriginal());
            }
            $entry->forceFill(['data' => $cursor])->saveQuietly();
            $entry->refresh();
        }
        $afterGeneration = $entry->getRawOriginal();
        $this->assertSame($snapshot, $entry->getRawOriginal('workflow_snapshot'));
        $this->assertSame($afterGeneration, $entry->fresh()->getRawOriginal());
        $id = $entry->candidate_id;
        $next = WorkflowStageProgression::currentType($entry);
        if ($next === WorkflowStageType::Verification) {
            $table->callTableAction('verify', $entry)->assertHasNoTableActionErrors();
            // The existing pending Review formatter retains the submission label in receipt-first flows.
            $expected = $order[1] === 'payment' ? $labels['submitted'] : $labels['receipt'];
        } else {
            $this->assertSame(WorkflowStageType::AwaitingResults, $next);
            Livewire::test(ListCandidateEntranceStatistics::class)->assertCanSeeTableRecords([$entry])
                ->callTableAction('passed', $entry)->assertHasNoTableActionErrors();
            $expected = $labels['passed'];
        }
        $entry->refresh();
        $this->assertSame($expected, WorkflowStageMessages::statusText($entry));
        $this->assertSame([$entry->id], $this->reviewMatches($form, $expected));
        $this->assertSame([], $this->reviewMatches($form, $labels['generated']));
        $this->assertSame($id, $entry->candidate_id);
        $this->assertSame($snapshot, $entry->getRawOriginal('workflow_snapshot'));
        $this->assertDatabaseHas('candidate_id_sequences', ['custom_form_id' => $form->id, 'last_sequence' => 1]);
        Http::assertNothingSent();
        Mail::assertNothingSent();
        Filament::setCurrentPanel(null);
    }

    private function generationEntry(CustomForm $form, array $order, Major $major, User $candidate): CustomFormEntry
    {
        $index = array_search('candidate_id_generation', $order, true);
        $reviewed = array_search('review', $order, true) < $index;
        $at = now()->toIso8601String();
        $entry = CustomFormEntry::withoutEvents(fn (): CustomFormEntry => CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $form->id, 'created_by' => $candidate->id, 'review_status' => $reviewed ? 'approved' : 'pending',
            'workflow_snapshot' => WorkflowEntrySnapshot::forForm($form->id),
            'data' => ['major' => (string) $major->id, 'registration_status' => $reviewed ? 'approved' : 'pending', 'candidate_status' => 'pending',
                'submitted_at' => $at, 'workflow_progress' => [
                    'version' => 1, 'submitted' => true, 'reviewed' => $reviewed, 'has_review' => true,
                    'payment_completed' => true, 'result_passed' => false, 'has_results' => true,
                    'candidate_id_generated' => false, 'verification_completed' => array_search('verification', $order, true) < $index,
                    'current_index' => $index, 'current_type' => 'candidate_id_generation', 'completed_at' => array_fill(0, $index, $at),
                    'finished' => false, 'ready_for_final' => false,
                ]],
        ]));
        AmsPaymentRequest::query()->forceCreate(['custom_form_entry_id' => $entry->id, 'ams_request_id' => 'generation-followup-'.$entry->id,
            'payment_status' => 'paid', 'sync_status' => 'synced']);

        return $entry->fresh();
    }

    private function reviewMatches(CustomForm $form, string $label): array
    {
        $query = CustomFormEntry::query()->where('custom_form_id', $form->id);
        WorkflowStatusSearch::applyReview($query, app()->getLocale() === 'en' ? strtoupper($label) : $label);

        return $query->pluck('id')->all();
    }

    private function receiptEntry(CustomForm $form, array $stages): CustomFormEntry
    {
        $entry = $this->entry($form, $stages, true);
        $data = $entry->data;
        $data['workflow_progress']['current_index'] = 3;
        $data['workflow_progress']['current_type'] = 'custom';
        $data['workflow_progress']['finished'] = false;
        $entry->forceFill(['candidate_id' => 'BNM-9000', 'data' => $data])->saveQuietly();

        return $entry->fresh();
    }

    private function entry(CustomForm $form, array $stages, bool $finished = false): CustomFormEntry
    {
        $at = now()->toIso8601String();

        return CustomFormEntry::withoutEvents(fn (): CustomFormEntry => CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $form->id,
            'review_status' => 'approved',
            'workflow_snapshot' => ['schema_version' => 2, 'has_workflow' => true, 'stages' => $stages],
            'data' => ['registration_status' => 'approved', 'workflow_progress' => [
                'version' => 1, 'submitted' => true, 'reviewed' => false, 'has_review' => false,
                'payment_completed' => true, 'result_passed' => false, 'has_results' => false,
                'candidate_id_generated' => $finished, 'current_index' => $finished ? null : 2,
                'current_type' => $finished ? null : 'candidate_id_generation',
                'completed_at' => $finished ? [$at, $at, $at] : [$at, $at],
                'finished' => $finished, 'ready_for_final' => false,
            ]],
        ]));
    }
}
