<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType as StageBehavior;
use App\Filament\Admin\Resources\CandidateEntranceStatistics\Tables\CandidateEntranceStatisticsTable;
use App\Models\Major;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowButton;
use App\Models\WorkflowNotification;
use App\Models\WorkflowStageType;
use App\Services\CandidateIdGenerator;
use App\Services\CandidatePaymentRecorder;
use App\Services\WorkflowCandidateIdGenerator;
use App\Services\WorkflowCandidateVerifier;
use App\Services\WorkflowCustomButtonService;
use App\Support\WorkflowStageMessages;
use App\Support\WorkflowStageProgression;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\ListCustomFormEntries;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class WorkflowCandidateIdGenerationTest extends TestCase
{
    use RefreshDatabase;

    private User $handler;

    private User $candidate;

    private User $recipient;

    private Major $major;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        $this->handler = $this->staff('generator', 'id_handler');
        $this->candidate = $this->user('generation-candidate', 'student');
        $this->recipient = $this->staff('generation-recipient', 'id_receiver');
        $this->major = Major::query()->create(['key' => 'generation-nurse', 'abbreviation' => 'BNM', 'name_en' => 'Nurse Midwifery', 'is_active' => true]);
        $this->actingAs($this->handler);
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public function test_requested_flow_issues_id_only_after_payment_then_receipt_only_records_its_status_and_messages(): void
    {
        [$generation, $button] = $this->generationStage();
        [$receipt, $receiptButton] = $this->receiptStage();
        [$entry, $workflow] = $this->entry([$this->submissionStage(), $this->reviewStage(), $this->paymentStage(), $generation, $receipt]);
        $snapshot = $entry->getRawOriginal('workflow_snapshot');
        $this->assertSame(StageBehavior::Review, WorkflowStageProgression::currentType($entry));
        $this->assertNull($entry->candidate_id);
        $this->assertFalse(app(WorkflowCandidateIdGenerator::class)->canGenerate($entry));
        $this->assertBlocked(fn () => app(WorkflowCandidateIdGenerator::class)->generate($entry), 409);

        Livewire::test(ListCustomFormEntries::class)->assertTableActionHidden('generate_candidate_id', $entry)
            ->callTableAction('accepted', $entry)->assertHasNoTableActionErrors();
        $entry->refresh();
        $this->assertSame(StageBehavior::Payment, WorkflowStageProgression::currentType($entry));
        $this->assertNull($entry->candidate_id);
        $this->pay($entry);
        $this->assertSame(StageBehavior::CandidateIdGeneration, WorkflowStageProgression::currentType($entry));
        $this->assertNull($entry->candidate_id);
        $this->assertFalse(data_get($entry->data, 'workflow_progress.candidate_id_generated'));
        $this->assertDatabaseCount('candidate_id_sequences', 0);
        $saved = $workflow->stages;
        $saved[3]['data']['status_message'] = 'Changed live status';
        $saved[3]['data']['notification_message'] = 'Changed live notification';
        $saved[3]['data']['alert_message'] = 'Changed live alert';
        $saved[3]['data']['responsible_role'] = ['id_receiver'];
        $workflow->update(['stages' => $saved]);
        $button->update(['label_en' => 'Changed catalog label', 'color' => 'danger']);

        $component = Livewire::test(ListCustomFormEntries::class)->assertTableActionVisible('generate_candidate_id', $entry);
        $action = $component->instance()->getTable()->getAction('generate_candidate_id')->record($entry);
        $this->assertSame('Issue candidate number', $action->getLabel());
        $this->assertSame('info', $action->getColor());
        $component->callTableAction('generate_candidate_id', $entry)->assertHasNoTableActionErrors()->assertNotified('Frozen generation alert');

        $entry->refresh();
        $this->assertSame('BNM-0001', $entry->candidate_id);
        $this->assertTrue(data_get($entry->data, 'workflow_progress.candidate_id_generated'));
        $this->assertSame(StageBehavior::Custom, WorkflowStageProgression::currentType($entry));
        $this->assertNotSame('passed', $entry->review_status);
        $this->assertSame('pending', data_get($entry->data, 'candidate_status'));
        $this->assertSame('Frozen generation candidate', $this->candidate->notifications()->sole()->data['body']);
        $this->assertSame('Frozen generation staff', $this->recipient->notifications()->sole()->data['body']);
        $this->assertTrue(WorkflowStageMessages::hasSentNotification($entry, StageBehavior::CandidateIdGeneration));
        $this->assertSame('Frozen generation status', WorkflowStageMessages::statusText($entry));
        $this->assertSame($snapshot, $entry->getRawOriginal('workflow_snapshot'));
        $beforeReceipt = $this->canonicalState($entry);
        $this->assertFalse(app(WorkflowCandidateVerifier::class)->canVerify($entry));

        $this->assertTrue(app(WorkflowCustomButtonService::class)->execute($entry, 4, $receiptButton->id));

        $this->assertSame($beforeReceipt, $this->canonicalState($entry->fresh()));
        $this->assertSame('Receipt recorded separately', WorkflowStageMessages::statusText($entry));
        $this->assertTrue(WorkflowStageProgression::finished($entry));
        $this->assertFalse(WorkflowStageProgression::readyForFinal($entry));
        $this->assertDatabaseHas('candidate_id_sequences', ['custom_form_id' => $entry->custom_form_id, 'last_sequence' => 1]);
        $this->assertDatabaseCount('candidate_account_ids', 1);
        $this->assertSame(1, DB::table('payments')->where('custom_form_entry_id', $entry->id)->count());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_native_generation_notifications_render_the_assigned_id_without_rewriting_templates_or_snapshots(): void
    {
        [$stage] = $this->generationStage();
        $message = ':candidate | :candidate_id | :id | :form';
        $stage['data']['notification_message'] = $message;
        $stage['data']['audience_messages']['staff_notification_message'] = $message;
        [$entry, $workflow, $form] = $this->entry([$stage], ['full_name_en' => 'Sok Dara']);
        $stages = $workflow->getRawOriginal('stages');
        $snapshot = $entry->getRawOriginal('workflow_snapshot');
        $this->assertNull($entry->candidate_id);
        $this->assertDatabaseCount('candidate_id_sequences', 0);
        $this->assertDatabaseCount('notifications', 0);

        Livewire::test(ListCustomFormEntries::class)->assertTableActionVisible('generate_candidate_id', $entry)
            ->callTableAction('generate_candidate_id', $entry)->assertHasNoTableActionErrors();

        $entry->refresh();
        $expected = 'Sok Dara | BNM-0001 | BNM-0001 | '.$form->display_name;
        $this->assertSame('BNM-0001', $entry->candidate_id);
        $this->assertSame($expected, $this->candidate->notifications()->sole()->data['body']);
        $this->assertSame($expected, $this->recipient->notifications()->sole()->data['body']);
        $this->assertSame($message, $workflow->fresh()->stages[0]['data']['notification_message']);
        $this->assertSame($stages, $workflow->fresh()->getRawOriginal('stages'));
        $this->assertSame($snapshot, $entry->getRawOriginal('workflow_snapshot'));
        $beforeRetry = $entry->getRawOriginal();

        $this->assertSame('BNM-0001', app(WorkflowCandidateIdGenerator::class)->generate($entry));

        $this->assertSame($beforeRetry, $entry->fresh()->getRawOriginal());
        $this->assertSame(1, $this->candidate->notifications()->count());
        $this->assertSame(1, $this->recipient->notifications()->count());
        $this->assertDatabaseCount('candidate_account_ids', 1);
        $this->assertDatabaseCount('candidate_id_sequences', 1);
        $this->assertDatabaseHas('candidate_id_sequences', ['custom_form_id' => $form->id, 'last_sequence' => 1]);
        $this->assertDatabaseCount('payments', 0);
    }

    public static function flexibleStageOrders(): array
    {
        return [
            'A review before payment and generation before receipt' => [['form_submission', 'review', 'payment', 'candidate_id_generation', 'custom', 'awaiting_results', 'completed']],
            'B payment before review and receipt before generation' => [['form_submission', 'payment', 'review', 'custom', 'candidate_id_generation', 'awaiting_results', 'completed']],
            'C payment and generation before receipt and review' => [['form_submission', 'payment', 'candidate_id_generation', 'custom', 'review', 'awaiting_results', 'completed']],
            'A built-in receipt after generation' => [['form_submission', 'review', 'payment', 'candidate_id_generation', 'verification', 'awaiting_results', 'completed'], true],
            'B built-in receipt before generation' => [['form_submission', 'payment', 'review', 'verification', 'candidate_id_generation', 'awaiting_results', 'completed'], true],
            'C built-in receipt before review' => [['form_submission', 'payment', 'candidate_id_generation', 'verification', 'review', 'awaiting_results', 'completed'], true],
        ];
    }

    #[DataProvider('flexibleStageOrders')]
    public function test_requested_stage_orders_follow_each_gate_without_early_ids_or_final_notifications(array $order, bool $builtinReceipt = false): void
    {
        [$generation] = $this->generationStage();
        [$receipt, $receiptButton] = $builtinReceipt ? $this->verificationReceiptStage() : $this->receiptStage();
        $receiptType = $builtinReceipt ? 'verification' : 'custom';
        $stages = [
            'form_submission' => $this->submissionStage(),
            'review' => $this->reviewStage(),
            'payment' => $this->paymentStage(),
            'candidate_id_generation' => $generation,
            $receiptType => $receipt,
            'awaiting_results' => ['type' => 'stage', 'data' => ['stage_name' => 'Exam results', 'stage_type' => 'awaiting_results',
                'responsible_role' => ['id_handler'], 'notification_channels' => ['system'], 'status_message' => 'Exam passed explicitly',
                'notification_message' => 'Configured exam result notice', 'result_statuses' => ['pending_status_message' => 'Waiting for exam result']]],
            'completed' => ['type' => 'stage', 'data' => ['stage_name' => 'Final release', 'stage_type' => 'completed',
                'responsible_role' => ['id_handler'], 'notification_channels' => ['system'], 'status_message' => 'Configured final released status',
                'notification_message' => 'Configured final release notice']],
        ];
        [$entry] = $this->entry(array_map(fn (string $type): array => $stages[$type], $order));
        $snapshot = $entry->getRawOriginal('workflow_snapshot');
        $generator = app(WorkflowCandidateIdGenerator::class);
        $custom = app(WorkflowCustomButtonService::class);
        $generationIndex = array_search('candidate_id_generation', $order, true);
        $receiptIndex = array_search($receiptType, $order, true);
        $paymentIndex = array_search('payment', $order, true);
        $this->assertNotNull(WorkflowStageProgression::completedAt($entry, 0));
        $this->assertNull($entry->candidate_id);

        for ($index = 1; $index < count($order); $index++) {
            $type = $order[$index];
            $entry->refresh();
            $this->assertSame(StageBehavior::from($type), WorkflowStageProgression::currentType($entry));
            $this->assertSame($index, WorkflowStageProgression::currentStage($entry)['_index']);
            $this->assertNull(WorkflowStageProgression::completedAt($entry, $index));
            $component = Livewire::test(ListCustomFormEntries::class)->assertCanSeeTableRecords([$entry]);
            if ($type !== 'review') {
                $component->assertTableActionHidden('accepted', $entry);
            }
            if ($type !== 'candidate_id_generation') {
                $component->assertTableActionHidden('generate_candidate_id', $entry);
                $this->assertFalse($generator->canGenerate($entry));
                if ($index < $generationIndex) {
                    $this->assertBlocked(fn () => $generator->generate($entry), 409);
                }
            }
            if ($type !== 'custom') {
                $component->assertTableActionHidden('custom_workflow_stage', $entry);
                $this->assertFalse($custom->canExecute($entry, $receiptIndex, $receiptButton->id));
                $this->assertFalse($custom->execute($entry, $receiptIndex, $receiptButton->id));
            }
            if ($type !== 'verification') {
                $component->assertTableActionHidden('verify', $entry);
                $this->assertFalse(app(WorkflowCandidateVerifier::class)->canVerify($entry));
                if ($builtinReceipt && $index < $receiptIndex) {
                    $this->assertBlocked(fn () => app(WorkflowCandidateVerifier::class)->verify($entry), 409);
                }
            }
            if ($index < $paymentIndex) {
                $this->assertBlocked(fn () => $this->pay($entry), 403);
            }
            if ($type !== 'awaiting_results' && $type !== 'completed') {
                $this->assertFalse(WorkflowStageProgression::passed($entry));
                $this->assertNotSame('passed', $entry->review_status);
                $this->assertSame('pending', data_get($entry->data, 'candidate_status'));
            }
            if ($type !== 'completed') {
                $this->assertFalse(CandidateEntranceStatisticsTable::notifyStudentReviewResult($entry, 'passed', notificationStage: StageBehavior::Completed));
                $this->assertFalse(WorkflowStageMessages::hasSentNotification($entry, StageBehavior::Completed));
                $this->assertNotSame('Configured final released status', WorkflowStageMessages::statusText($entry));
            }
            if ($index <= $generationIndex) {
                $this->assertNull($entry->candidate_id);
                $this->assertDatabaseCount('candidate_id_sequences', 0);
            }

            if ($type === 'review') {
                $component->assertTableActionVisible('accepted', $entry)->callTableAction('accepted', $entry)->assertHasNoTableActionErrors();
            } elseif ($type === 'payment') {
                $this->pay($entry);
            } elseif ($type === 'candidate_id_generation') {
                $component->assertTableActionVisible('generate_candidate_id', $entry)
                    ->callTableAction('generate_candidate_id', $entry)->assertHasNoTableActionErrors();
                $this->assertSame('Frozen generation candidate', $this->candidate->notifications()->where('data->viewData->workflow_stage_type', 'candidate_id_generation')->sole()->data['body']);
            } elseif ($type === 'custom') {
                $beforeReceipt = $this->canonicalState($entry);
                $component->assertTableActionVisible('custom_workflow_stage', $entry)
                    ->callTableAction('custom_workflow_stage', $entry, arguments: ['stage_index' => $receiptIndex, 'button_id' => $receiptButton->id])
                    ->assertHasNoTableActionErrors();
                $entry->refresh();
                $this->assertSame($beforeReceipt, $this->canonicalState($entry));
                $this->assertSame('Receipt recorded separately', WorkflowStageMessages::statusText($entry));
                $this->assertSame('Receipt candidate', $this->candidate->notifications()->where('data->viewData->workflow_stage_type', 'custom')->sole()->data['body']);
            } elseif ($type === 'verification') {
                $beforeReceipt = $this->canonicalState($entry);
                $reviewed = data_get($entry->data, 'workflow_progress.reviewed');
                $component->assertTableActionVisible('verify', $entry)->callTableAction('verify', $entry)
                    ->assertHasNoTableActionErrors()->assertNotified('Builtin receipt alert');
                $entry->refresh();
                $this->assertSame($beforeReceipt['candidate_id'], $entry->candidate_id);
                $this->assertSame($reviewed, data_get($entry->data, 'workflow_progress.reviewed'));
                $this->assertSame('pending', data_get($entry->data, 'candidate_status'));
                $this->assertNotSame('passed', $entry->review_status);
                $this->assertSame('Receipt candidate', $this->candidate->notifications()->where('data->viewData->workflow_stage_type', 'verification')->sole()->data['body']);
                $this->assertSame('Receipt staff', $this->recipient->notifications()->where('data->viewData->workflow_stage_type', 'verification')->sole()->data['body']);
            } elseif ($type === 'awaiting_results') {
                $this->assertSame('pending', data_get($entry->data, 'candidate_status'));
                $this->assertTrue(WorkflowStageProgression::passed($entry));
                $this->assertTrue(WorkflowStageMessages::notifyResultStage($entry));
                $this->assertFalse(WorkflowStageMessages::notifyResultStage($entry));
                $this->assertSame(StageBehavior::Completed, WorkflowStageProgression::currentType($entry));
                $this->assertTrue(WorkflowStageProgression::readyForFinal($entry));
                $this->assertFalse(WorkflowStageProgression::finished($entry));
                $this->assertSame('Exam passed explicitly', WorkflowStageMessages::statusText($entry));
            } else {
                $this->assertTrue(CandidateEntranceStatisticsTable::notifyStudentReviewResult($entry, 'passed', notificationStage: StageBehavior::Completed));
                $this->assertFalse(CandidateEntranceStatisticsTable::notifyStudentReviewResult($entry, 'passed', notificationStage: StageBehavior::Completed));
                $this->assertTrue(WorkflowStageProgression::finished($entry));
                $this->assertSame('Configured final released status', WorkflowStageMessages::statusText($entry));
            }

            $entry->refresh();
            $this->assertNotNull(WorkflowStageProgression::completedAt($entry, $index));
            $this->assertSame($index + 1 < count($order) ? StageBehavior::from($order[$index + 1]) : null, WorkflowStageProgression::currentType($entry));
            $this->assertSame($snapshot, $entry->getRawOriginal('workflow_snapshot'));
            $this->assertSame('Keep', data_get($entry->data, 'retained_answer'));
            $this->assertSame($index >= $generationIndex ? 'BNM-0001' : null, $entry->candidate_id);
            $this->assertDatabaseCount('candidate_id_sequences', $index >= $generationIndex ? 1 : 0);
            $this->assertDatabaseCount('payments', $index >= $paymentIndex ? 1 : 0);
        }

        $this->assertDatabaseCount('candidate_account_ids', 1);
        $this->assertDatabaseHas('candidate_id_sequences', ['custom_form_id' => $entry->custom_form_id, 'last_sequence' => 1]);
        $this->assertEqualsCanonicalizing(['Frozen generation candidate', 'Receipt candidate', 'Configured exam result notice', 'Configured final release notice'],
            $this->candidate->notifications()->get()->pluck('data.body')->all());
        $this->assertSame(1, $this->candidate->notifications()->where('data->viewData->workflow_stage_type', 'completed')->count());
        $this->assertEqualsCanonicalizing($builtinReceipt ? ['Frozen generation staff', 'Receipt staff'] : ['Frozen generation staff'],
            $this->recipient->notifications()->get()->pluck('data.body')->all());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_missing_major_returns_warning_and_leaves_the_stage_and_sequence_untouched(): void
    {
        [$stage] = $this->generationStage();
        [$entry] = $this->entry([$stage], ['major' => null]);
        $before = $entry->getRawOriginal();
        $this->assertTrue(app(WorkflowCandidateIdGenerator::class)->canGenerate($entry));
        $this->assertNull(app(WorkflowCandidateIdGenerator::class)->generate($entry));
        Livewire::test(ListCustomFormEntries::class)->callTableAction('generate_candidate_id', $entry)
            ->assertHasNoTableActionErrors()->assertNotified(__('workflow_notifications.notifications.candidate_id_generation_missing_major_title'));
        $this->assertSame($before, $entry->fresh()->getRawOriginal());
        $this->assertDatabaseCount('candidate_id_sequences', 0);
        $this->assertDatabaseCount('candidate_account_ids', 0);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertSame(StageBehavior::CandidateIdGeneration, WorkflowStageProgression::currentType($entry));
    }

    public static function workflowsWithoutGeneration(): array
    {
        return [
            'static workflow' => ['static', null],
            'review-only workflow' => ['review', null],
            'custom-only workflow' => ['custom', null],
            'review preserves an existing ID' => ['review', 'KEEP-0007'],
        ];
    }

    #[DataProvider('workflowsWithoutGeneration')]
    public function test_workflows_without_the_generation_stage_never_issue_ids_through_review_custom_actions_or_direct_calls(string $mode, ?string $existingId): void
    {
        if ($mode === 'static') {
            $entry = $this->createEntry($this->form());
            $button = null;
        } elseif ($mode === 'review') {
            [$entry] = $this->entry([$this->reviewStage()]);
            $button = null;
        } else {
            [$receipt, $button] = $this->receiptStage();
            [$entry] = $this->entry([$receipt]);
        }
        if ($existingId !== null) {
            DB::table('custom_form_entries')->where('id', $entry->id)->update(['candidate_id' => $existingId, 'candidate_id_year' => 2024]);
            $entry->refresh();
        }
        $snapshot = $entry->getRawOriginal('workflow_snapshot');
        $beforeDenied = $entry->getRawOriginal();
        $this->assertFalse(app(WorkflowCandidateIdGenerator::class)->canGenerate($entry));
        $this->assertNull(app(CandidateIdGenerator::class)->assignToEntry($entry));
        $this->assertBlocked(fn () => app(WorkflowCandidateIdGenerator::class)->generate($entry), 409);
        $this->assertSame($beforeDenied, $entry->fresh()->getRawOriginal());
        $component = Livewire::test(ListCustomFormEntries::class)->assertTableActionHidden('generate_candidate_id', $entry);
        if ($mode === 'custom') {
            $component->callTableAction('custom_workflow_stage', $entry, arguments: ['stage_index' => 0, 'button_id' => $button->id])->assertHasNoTableActionErrors();
        } else {
            $component->callTableAction('accepted', $entry)->assertHasNoTableActionErrors();
        }

        $entry->refresh();
        $this->assertSame($existingId, $entry->candidate_id);
        $this->assertSame($snapshot, $entry->getRawOriginal('workflow_snapshot'));
        $this->assertDatabaseCount('candidate_id_sequences', 0);
        $this->assertDatabaseCount('candidate_account_ids', 0);
        $this->assertSame('Keep', data_get($entry->data, 'retained_answer'));
    }

    public function test_generation_does_not_propagate_an_id_to_a_checked_review_only_sibling(): void
    {
        [$old, $workflow, $form] = $this->entry([$this->reviewStage()]);
        Livewire::test(ListCustomFormEntries::class)->callTableAction('accepted', $old)->assertHasNoTableActionErrors();
        $old->refresh();
        $this->assertNull($old->candidate_id);
        $oldBefore = $old->getRawOriginal();
        [$generation] = $this->generationStage();
        $workflow->update(['stages' => [$generation]]);
        $new = $this->createEntry($form);

        $this->assertSame('BNM-0001', app(WorkflowCandidateIdGenerator::class)->generate($new));

        $this->assertSame($oldBefore, $old->fresh()->getRawOriginal());
        $this->assertNull($old->candidate_id);
        $this->assertTrue(data_get($new->fresh()->data, 'workflow_progress.candidate_id_generated'));
        $this->assertDatabaseCount('candidate_account_ids', 1);
        $this->assertDatabaseHas('candidate_id_sequences', ['custom_form_id' => $form->id, 'last_sequence' => 1]);
    }

    public function test_generation_is_idempotent_and_cannot_issue_an_id_to_an_unconfirmed_sibling(): void
    {
        [$stage] = $this->generationStage();
        [$receipt] = $this->receiptStage();
        [$entry, , $form] = $this->entry([$stage, $receipt]);
        $sibling = $this->createEntry($form);
        $service = app(WorkflowCandidateIdGenerator::class);
        $this->assertSame('BNM-0001', $service->generate($entry));
        $this->assertNull($sibling->fresh()->candidate_id);
        $this->assertFalse(data_get($sibling->data, 'workflow_progress.candidate_id_generated'));
        $before = $entry->getRawOriginal();
        $count = DB::table('notifications')->count();

        $this->assertSame('BNM-0001', $service->generate($entry));

        $this->assertSame($before, $entry->fresh()->getRawOriginal());
        $this->assertSame($count, DB::table('notifications')->count());
        $this->assertSame('BNM-0001', $service->generate($sibling));
        $this->assertTrue(data_get($sibling->fresh()->data, 'workflow_progress.candidate_id_generated'));
        $this->assertDatabaseHas('candidate_id_sequences', ['custom_form_id' => $form->id, 'last_sequence' => 1]);
        $this->assertDatabaseCount('candidate_account_ids', 1);
    }

    public function test_a_final_generation_stage_never_marks_the_candidate_as_passed(): void
    {
        [$stage] = $this->generationStage();
        [$entry] = $this->entry([$stage]);
        $before = $this->canonicalState($entry);

        $this->assertSame('BNM-0001', app(WorkflowCandidateIdGenerator::class)->generate($entry));

        $after = $this->canonicalState($entry->fresh());
        $this->assertSame($before['review_status'], $after['review_status']);
        $this->assertSame($before['data'], $after['data']);
        $this->assertTrue(WorkflowStageProgression::finished($entry));
        $this->assertFalse(WorkflowStageProgression::readyForFinal($entry));
        $this->assertSame('Frozen generation status', WorkflowStageMessages::statusText($entry));
    }

    public function test_exact_frozen_role_view_and_form_permissions_are_checked_again_when_executing(): void
    {
        [$stage] = $this->generationStage();
        [$entry] = $this->entry([$stage]);
        $service = app(WorkflowCandidateIdGenerator::class);
        $before = $entry->getRawOriginal();
        $wrongRole = $this->staff('wrong-role', 'different_handler');
        $noView = $this->user('no-view', 'admin');
        $noView->assignRole(Role::findOrCreate('id_handler', 'web'));
        $inactive = $this->staff('inactive', 'id_handler');
        $inactive->update(['is_active' => false]);
        foreach ([$wrongRole, $noView, $inactive, $this->candidate] as $user) {
            $this->actingAs($user);
            $this->assertFalse($service->canGenerate($entry));
            $this->assertBlocked(fn () => $service->generate($entry), 403);
            $this->assertSame($before, $entry->fresh()->getRawOriginal());
        }
        $this->actingAs($this->handler);
        Livewire::test(ListCustomFormEntries::class)->assertTableActionVisible('generate_candidate_id', $entry)->mountTableAction('generate_candidate_id', $entry);
        $role = Role::findByName('id_handler', 'web');
        $role->forceFill(['restrict_custom_form_access' => true, 'allowed_custom_form_ids' => []])->save();
        $this->actingAs($this->handler->fresh());
        $this->assertFalse($service->canGenerate($entry));
        $this->assertBlocked(fn () => $service->generate($entry), 403);
        $this->assertSame($before, $entry->fresh()->getRawOriginal());
        $this->assertDatabaseCount('candidate_id_sequences', 0);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_payment_reversal_requires_the_generation_checkpoint_again_and_reuses_its_id(): void
    {
        [$stage] = $this->generationStage();
        [$receipt, $receiptButton] = $this->receiptStage();
        [$entry, , $form] = $this->entry([$this->submissionStage(), $this->reviewStage(), $this->paymentStage(), $stage, $receipt]);
        $unrelated = $this->createEntry($form);
        Livewire::test(ListCustomFormEntries::class)->callTableAction('accepted', $entry)->assertHasNoTableActionErrors();
        $payment = $this->pay($entry);
        $service = app(WorkflowCandidateIdGenerator::class);
        $this->assertSame('BNM-0001', $service->generate($entry));
        $custom = app(WorkflowCustomButtonService::class);
        $this->assertTrue($custom->execute($entry, 4, $receiptButton->id));
        $this->assertTrue(WorkflowStageProgression::finished($entry));
        $snapshot = $entry->getRawOriginal('workflow_snapshot');
        $unrelatedBefore = $unrelated->fresh()->getRawOriginal();
        $receiptEvent = 'custom_stage:4:button:'.$receiptButton->id.':candidate';
        $receiptNotification = $this->candidate->notifications()->where('data->viewData->workflow_event', $receiptEvent)->sole();
        $receiptDelivery = DB::table('workflow_notification_deliveries')->where('custom_form_entry_id', $entry->id)->where('workflow_event', $receiptEvent)->sole();

        $payment->update(['status_payt' => 'return']);
        $this->assertTrue(WorkflowStageProgression::rollbackUnpaidAutoCompletion($entry));
        $this->assertSame(StageBehavior::Payment, WorkflowStageProgression::currentType($entry));
        $this->assertFalse(data_get($entry->data, 'workflow_progress.candidate_id_generated'));
        $this->assertNull(WorkflowStageProgression::completedAt($entry, 3));
        $this->assertNull(WorkflowStageProgression::completedAt($entry, 4));
        $this->assertArrayNotHasKey('4:'.$receiptButton->id, data_get($entry->data, 'workflow_custom_button_events', []));
        $this->assertNull(data_get($entry->data, 'workflow_custom_status'));
        $this->assertSame('BNM-0001', $entry->candidate_id);
        $this->assertBlocked(fn () => $service->generate($entry), 409);
        $payment->update(['status_payt' => 'paid']);
        $this->assertTrue(WorkflowStageProgression::paid($entry));
        $this->assertSame('BNM-0001', $service->generate($entry));
        $this->assertTrue($custom->canExecute($entry, 4, $receiptButton->id));
        $this->assertTrue($custom->execute($entry, 4, $receiptButton->id));
        $this->assertSame('Receipt recorded separately', WorkflowStageMessages::statusText($entry));
        $this->assertTrue(WorkflowStageProgression::finished($entry));
        $this->assertSame(1, $this->candidate->notifications()->where('data->viewData->workflow_event', $receiptEvent)->count());
        $this->assertDatabaseHas('notifications', ['id' => $receiptNotification->id]);
        $this->assertSame(1, DB::table('workflow_notification_deliveries')->where('custom_form_entry_id', $entry->id)->where('workflow_event', $receiptEvent)->count());
        $this->assertDatabaseHas('workflow_notification_deliveries', ['id' => $receiptDelivery->id]);
        $this->assertSame($snapshot, $entry->getRawOriginal('workflow_snapshot'));
        $this->assertSame($unrelatedBefore, $unrelated->fresh()->getRawOriginal());
        $this->assertSame('Keep', data_get($entry->data, 'retained_answer'));
        $this->assertDatabaseHas('candidate_id_sequences', ['custom_form_id' => $entry->custom_form_id, 'last_sequence' => 1]);
    }

    public static function receiptOnlyModes(): array
    {
        return [
            'configured receipt without an ID' => [true, null],
            'static receipt without an ID' => [false, null],
            'configured receipt retains an existing ID' => [true, 'EXISTING-0042'],
            'static receipt retains an existing ID' => [false, 'EXISTING-0042'],
        ];
    }

    #[DataProvider('receiptOnlyModes')]
    public function test_receipt_without_a_major_never_issues_an_id_and_duplicate_clicks_preserve_existing_ids(bool $configured, ?string $existingId): void
    {
        if ($configured) {
            [$receipt] = $this->verificationReceiptStage();
            [$entry] = $this->entry([$this->reviewStage(), $receipt], ['major' => null]);
        } else {
            $entry = $this->createEntry($this->form(), ['major' => null]);
        }
        if ($existingId !== null) {
            DB::table('custom_form_entries')->where('id', $entry->id)->update(['candidate_id' => $existingId, 'candidate_id_year' => 2024]);
            $entry->refresh();
        }
        $snapshot = $entry->getRawOriginal('workflow_snapshot');
        Livewire::test(ListCustomFormEntries::class)->callTableAction('accepted', $entry)->assertHasNoTableActionErrors();
        $entry->refresh();
        $this->assertSame($existingId, $entry->candidate_id);
        $this->assertTrue(app(WorkflowCandidateVerifier::class)->canVerify($entry));

        Livewire::test(ListCustomFormEntries::class)->assertTableActionVisible('verify', $entry)->callTableAction('verify', $entry)
            ->assertHasNoTableActionErrors()->assertNotified($configured ? 'Builtin receipt alert' : __('workflow_notifications.notifications.verification_success'));

        $entry->refresh();
        $this->assertSame($existingId, $entry->candidate_id);
        $this->assertSame($existingId === null ? null : 2024, $entry->candidate_id_year);
        $this->assertSame('approved', $entry->review_status);
        $this->assertSame('pending', data_get($entry->data, 'candidate_status'));
        $this->assertFalse(app(WorkflowCandidateIdGenerator::class)->canGenerate($entry));
        $this->assertFalse(app(WorkflowCandidateVerifier::class)->canVerify($entry));
        if ($configured) {
            $this->assertTrue(data_get($entry->data, 'workflow_progress.verification_completed'));
            $this->assertTrue(WorkflowStageProgression::finished($entry));
            $this->assertFalse(WorkflowStageProgression::readyForFinal($entry));
            $this->assertSame('Receipt recorded separately', WorkflowStageMessages::statusText($entry));
            $this->assertSame('Receipt candidate', $this->candidate->notifications()->sole()->data['body']);
            $this->assertSame('Receipt staff', $this->recipient->notifications()->sole()->data['body']);
        } else {
            $this->assertNotNull(data_get($entry->data, 'application_received_at'));
            $this->assertSame($this->handler->id, data_get($entry->data, 'application_received_by'));
        }
        $beforeDuplicate = $entry->getRawOriginal();
        $notifications = DB::table('notifications')->count();

        $this->assertTrue(app(WorkflowCandidateVerifier::class)->verify($entry));

        $this->assertSame($beforeDuplicate, $entry->fresh()->getRawOriginal());
        $this->assertSame($notifications, DB::table('notifications')->count());
        $this->assertSame($snapshot, $entry->getRawOriginal('workflow_snapshot'));
        $this->assertDatabaseCount('candidate_id_sequences', 0);
        $this->assertDatabaseCount('candidate_account_ids', 0);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public static function receivedSiblingModes(): array
    {
        return ['configured receipt-only sibling' => [true], 'static received sibling' => [false]];
    }

    #[DataProvider('receivedSiblingModes')]
    public function test_receipt_markers_do_not_authorize_id_propagation_from_a_different_application(bool $configured): void
    {
        [$generation] = $this->generationStage();
        [$receipt] = $this->verificationReceiptStage();
        if ($configured) {
            [$received, $workflow, $form] = $this->entry([$this->reviewStage(), $receipt]);
        } else {
            $form = $this->form();
            $received = $this->createEntry($form);
            $workflow = null;
        }
        Livewire::test(ListCustomFormEntries::class)->callTableAction('accepted', $received)->assertHasNoTableActionErrors();
        Livewire::test(ListCustomFormEntries::class)->callTableAction('verify', $received)->assertHasNoTableActionErrors();
        $received->refresh();
        $this->assertNull($received->candidate_id);
        $receivedBefore = $received->getRawOriginal();
        if ($workflow !== null) {
            $workflow->update(['stages' => [$generation, $receipt]]);
        } else {
            $workflow = WorkflowNotification::query()->create(['name' => 'Generation after static receipt', 'stages' => [$generation, $receipt]]);
            $workflow->forms()->attach($form);
        }
        $issued = $this->createEntry($form);

        $this->assertSame('BNM-0001', app(WorkflowCandidateIdGenerator::class)->generate($issued));

        $this->assertSame($receivedBefore, $received->fresh()->getRawOriginal());
        $this->assertNull($received->candidate_id);
        $this->assertTrue(data_get($issued->fresh()->data, 'workflow_progress.candidate_id_generated'));
        $this->assertDatabaseHas('candidate_id_sequences', ['custom_form_id' => $form->id, 'last_sequence' => 1]);
        $this->assertDatabaseCount('candidate_account_ids', 1);
    }

    private function generationStage(): array
    {
        $button = WorkflowButton::query()->create(['label_en' => 'Issue candidate number', 'action_key' => 'generate_candidate_id', 'color' => 'info']);
        $type = WorkflowStageType::query()->create(['label_en' => 'Explicit ID generation', 'label_km' => 'បង្កើតលេខបេក្ខជន សម្រាប់សាកល្បង', 'workflow_button_ids' => [$button->id]]);

        return [['type' => 'stage', 'data' => [
            'stage_name' => 'Generate Candidate ID', 'stage_type_id' => $type->id, 'stage_type' => 'candidate_id_generation',
            'responsible_role' => ['id_handler'], 'notification_channels' => ['system'],
            'status_message' => 'Frozen generation status', 'alert_message' => 'Frozen generation alert', 'notification_message' => 'Frozen generation candidate',
            'audience_messages' => ['staff_notification_message' => 'Frozen generation staff', 'staff_recipient_roles' => ['id_receiver']],
        ]], $button];
    }

    private function receiptStage(): array
    {
        $button = WorkflowButton::query()->create(['label_en' => 'Application Received', 'action_key' => null]);
        $type = WorkflowStageType::query()->create(['label_en' => 'Separate receipt', 'label_km' => 'ទទួលពាក្យ', 'workflow_button_ids' => [$button->id]]);

        return [['type' => 'stage', 'data' => [
            'stage_name' => 'Application Received', 'stage_type_id' => $type->id, 'stage_type' => 'custom', 'responsible_role' => ['id_handler'],
            'notification_channels' => ['system'], 'submission_messages' => ['candidate_status_message' => 'Receipt recorded separately',
                'candidate_notification_message' => 'Receipt candidate', 'staff_notification_message' => null, 'staff_recipient_roles' => []],
        ]], $button];
    }

    private function verificationReceiptStage(): array
    {
        $button = WorkflowButton::query()->create(['label_en' => 'Acknowledge application receipt', 'action_key' => 'verify']);
        $type = WorkflowStageType::query()->create(['label_en' => 'Receipt without ID generation', 'label_km' => 'ទទួលពាក្យដោយឡែក សម្រាប់សាកល្បង', 'workflow_button_ids' => [$button->id]]);

        return [['type' => 'stage', 'data' => [
            'stage_name' => 'Application Received', 'stage_type_id' => $type->id, 'stage_type' => 'verification',
            'responsible_role' => ['id_handler'], 'notification_channels' => ['system'], 'alert_message' => 'Builtin receipt alert',
            'status_message' => 'Receipt recorded separately', 'notification_message' => 'Receipt candidate',
            'audience_messages' => ['staff_notification_message' => 'Receipt staff', 'staff_recipient_roles' => ['id_receiver']],
        ]], $button];
    }

    private function submissionStage(): array
    {
        return ['type' => 'stage', 'data' => ['stage_name' => 'Submit', 'stage_type' => 'form_submission', 'notification_channels' => []]];
    }

    private function reviewStage(): array
    {
        return ['type' => 'stage', 'data' => ['stage_name' => 'Check', 'stage_type' => 'review', 'responsible_role' => ['id_handler'], 'enabled_actions' => ['accept'], 'notification_channels' => []]];
    }

    private function paymentStage(): array
    {
        return ['type' => 'stage', 'data' => ['stage_name' => 'Paid', 'stage_type' => 'payment', 'payment_source' => 'system', 'responsible_role' => ['id_handler'], 'notification_channels' => []]];
    }

    private function pay(CustomFormEntry $entry): Payment
    {
        $payment = app(CandidatePaymentRecorder::class)->record(['custom_form_entry_id' => $entry->id, 'receipt_number' => 'GENERATION-'.$entry->id, 'amount_usd' => 10, 'datetime_pay' => now()]);
        $entry->refresh();

        return $payment;
    }

    private function entry(array $stages, array $data = []): array
    {
        $workflow = WorkflowNotification::query()->create(['name' => 'Generation workflow '.uniqid(), 'stages' => $stages]);
        $form = $this->form();
        $workflow->forms()->attach($form);

        return [$this->createEntry($form, $data), $workflow, $form];
    }

    private function form(): CustomForm
    {
        $form = CustomForm::query()->create(['name' => 'Generation application', 'slug' => 'generation-'.uniqid(), 'is_active' => true]);
        DocumentTemplate::query()->where('type', 'custom_form_'.$form->id)->delete();

        return $form;
    }

    private function createEntry(CustomForm $form, array $data = []): CustomFormEntry
    {
        $entry = CustomFormEntry::query()->forceCreate(['custom_form_id' => $form->id, 'created_by' => $this->candidate->id, 'review_status' => 'pending',
            'data' => array_replace(['registration_status' => 'pending', 'candidate_status' => 'pending', 'major' => (string) $this->major->id, 'retained_answer' => 'Keep'], $data)]);
        WorkflowStageProgression::submitted($entry);

        return $entry->fresh();
    }

    private function canonicalState(CustomFormEntry $entry): array
    {
        return ['candidate_id' => $entry->candidate_id, 'review_status' => $entry->review_status,
            'data' => array_diff_key($entry->data, array_flip(['workflow_progress', 'workflow_custom_button_events', 'workflow_custom_status']))];
    }

    private function assertBlocked(callable $callback, int $status): void
    {
        try {
            $callback();
            $this->fail('ID generation must enforce the authorized stage and actor.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame($status, $exception->getStatusCode());
        }
    }

    private function staff(string $name, string $role): User
    {
        $user = $this->user($name, 'admin');
        $user->assignRole(Role::findOrCreate($role, 'web'));
        foreach (['ViewAny', 'View', 'Accepted', 'Verify'] as $action) {
            $user->givePermissionTo(Permission::findOrCreate($action.':CustomFormEntry', 'web'));
        }
        $user->givePermissionTo(Permission::findOrCreate('ViewAny:PaymentStatusList', 'web'));

        return $user->fresh();
    }

    private function user(string $name, string $type): User
    {
        return User::query()->create(['name' => $name, 'username' => $name, 'email' => $name.'@example.test', 'registration_type' => $type,
            'password' => 'password', 'is_active' => true, 'date_of_birth' => '2000-01-01']);
    }
}
