<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\CandidateEntranceStatistics\Pages\ListCandidateEntranceStatistics;
use App\Filament\Admin\Resources\ExamResults\ExamResultResource;
use App\Filament\Admin\Resources\ExamResults\Pages\ListExamResults;
use App\Models\AmsPaymentRequest;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Services\CandidatePaymentRecorder;
use App\Support\PassedResultMenuOptions;
use App\Support\StatisticsMenuOptions;
use App\Support\WorkflowStageMessages;
use App\Support\WorkflowStageProgression;
use App\Support\WorkflowSubmissionMessages;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\EditCustomFormEntry;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\ListCustomFormEntries;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkflowOrderedStagesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $candidate;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        $this->admin = $this->user('admin');
        $role = Role::findOrCreate('admin', 'web');
        foreach ([
            'ViewAny:CustomFormEntry', 'Accepted:CustomFormEntry', 'Rejected:CustomFormEntry', 'ViewPdf:CustomFormEntry',
            'ViewAny:CandidateEntranceStatistic', 'Passed:CandidateEntranceStatistic',
            'ViewAny:ExamResult', 'NotifyStudent:ExamResult',
        ] as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $this->admin->assignRole($role);
        $this->candidate = $this->user('student');
        $this->actingAs($this->admin);
    }

    public function test_all_120_unique_stage_orders_only_advance_the_current_gate_and_finish_at_the_end(): void
    {
        $types = [
            WorkflowStageType::FormSubmission, WorkflowStageType::Review, WorkflowStageType::Payment,
            WorkflowStageType::AwaitingResults, WorkflowStageType::Completed,
        ];
        $orders = $this->permutations($types);
        $this->assertCount(120, $orders);

        foreach ($orders as $order) {
            [$form] = $this->workflow($order);
            $entry = $this->entry($form);
            WorkflowStageProgression::submitted($entry);
            $context = implode(' -> ', array_map(fn (WorkflowStageType $type): string => $type->value, $order));
            $gates = array_values(array_filter($order, fn (WorkflowStageType $type): bool => ! in_array($type, [WorkflowStageType::FormSubmission, WorkflowStageType::Completed], true)));

            foreach ($gates as $index => $gate) {
                $this->assertSame($gate, WorkflowStageProgression::currentType($entry->fresh()), $context);
                $this->assertTrue(WorkflowStageProgression::isCurrent($entry->fresh(), $gate), $context);
                $this->assertFalse(WorkflowStageProgression::readyForFinal($entry->fresh()), $context);
                $this->assertFalse(WorkflowStageProgression::finished($entry->fresh()), $context);
                $this->assertFalse(ExamResultResource::getEloquentQuery()->whereKey($entry->id)->exists(), $context);

                foreach ([WorkflowStageType::Review, WorkflowStageType::Payment, WorkflowStageType::AwaitingResults] as $other) {
                    if ($other !== $gate && ! in_array($other, array_slice($gates, 0, $index), true)) {
                        $before = data_get($entry->fresh()->data, 'workflow_progress');
                        $this->performEngineEvent($entry, $other, prepare: false);
                        $this->assertSame($before, data_get($entry->fresh()->data, 'workflow_progress'), $context.' blocks '.$other->value);
                    }
                }

                $this->performEngineEvent($entry, $gate);
                $this->assertNotNull($this->completedAt($entry->fresh(), $gate), $context);
                $after = data_get($entry->fresh()->data, 'workflow_progress');
                $this->performEngineEvent($entry, $gate, prepare: false);
                $this->assertSame($after, data_get($entry->fresh()->data, 'workflow_progress'), $context.' duplicate '.$gate->value);
            }

            if ($order[array_key_last($order)] === WorkflowStageType::Completed) {
                $this->assertSame(WorkflowStageType::Completed, WorkflowStageProgression::currentType($entry->fresh()), $context);
                $this->assertTrue(WorkflowStageProgression::readyForFinal($entry->fresh()), $context);
                $this->assertFalse(WorkflowStageProgression::finished($entry->fresh()), $context);
                $this->assertFalse(WorkflowStageMessages::hasSentNotification($entry->fresh(), WorkflowStageType::Completed), $context);
                WorkflowStageProgression::notified($entry, WorkflowStageType::Completed);
            }

            $this->assertTrue(WorkflowStageProgression::finished($entry->fresh()), $context);
            $this->assertNull(WorkflowStageProgression::currentType($entry->fresh()), $context);
            $this->assertTrue(WorkflowStageProgression::readyForFinal($entry->fresh()), $context);
            $this->assertSame('passed', data_get($entry->fresh()->data, 'candidate_status'), $context);
            $this->assertSame($entry->workflow_snapshot['stages'], $entry->fresh()->workflow_snapshot['stages'], $context);
        }
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_results_before_review_then_payment_uses_the_real_result_review_and_local_payment_actions(): void
    {
        [$form] = $this->workflow([
            WorkflowStageType::FormSubmission, WorkflowStageType::AwaitingResults,
            WorkflowStageType::Review, WorkflowStageType::Payment,
        ]);
        $entry = $this->entry($form);
        WorkflowStageProgression::submitted($entry);
        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageMessages::stageTypeFor($entry->fresh()));
        $this->assertFalse(WorkflowStageMessages::canCurrentUserHandleStage($entry, WorkflowStageType::Review));
        $this->assertFalse(WorkflowStageMessages::canCurrentUserHandleStage($entry, WorkflowStageType::Payment));

        Livewire::test(ListCandidateEntranceStatistics::class)->assertCanSeeTableRecords([$entry])
            ->callTableAction('passed', $entry)->assertHasNoTableActionErrors();
        $this->assertSame(WorkflowStageType::Review, WorkflowStageMessages::stageTypeFor($entry->fresh()));
        $this->assertFalse(ExamResultResource::getEloquentQuery()->whereKey($entry->id)->exists());

        DocumentTemplate::query()->where('type', 'custom_form_'.$form->id)->delete();
        Livewire::test(ListCustomFormEntries::class)
            ->callTableAction('accepted', $entry)->assertHasNoTableActionErrors();
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageMessages::stageTypeFor($entry->fresh()));
        $this->assertFalse(WorkflowStageProgression::finished($entry->fresh()));
        $this->assertFalse(ExamResultResource::getEloquentQuery()->whereKey($entry->id)->exists());

        app(CandidatePaymentRecorder::class)->record([
            'custom_form_entry_id' => $entry->id, 'receipt_number' => 'ORDERED-LOCAL-'.$entry->id,
            'amount_usd' => 10, 'datetime_pay' => now(),
        ]);
        $this->assertTrue(WorkflowStageProgression::finished($entry->fresh()));
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageProgression::finalStageType($entry->fresh()));
        $this->assertTrue(ExamResultResource::getEloquentQuery()->whereKey($entry->id)->exists());
        $this->assertDatabaseCount('payments', 1);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_submission_after_review_notifies_candidate_and_staff_once_only_when_its_checkpoint_is_reached(): void
    {
        [$form, $workflow] = $this->workflow([
            WorkflowStageType::Review, WorkflowStageType::FormSubmission,
            WorkflowStageType::AwaitingResults, WorkflowStageType::Completed,
        ]);
        $stages = $workflow->stages;
        $stages[1]['data']['submission_messages'] = [
            'candidate_status_message' => 'Application submitted',
            'candidate_notification_message' => 'Deferred candidate submission notification',
            'staff_notification_message' => 'Deferred staff submission notification',
            'staff_recipient_roles' => ['admin'],
        ];
        $workflow->update(['stages' => $stages]);
        $entry = $this->entry($form);
        $entry->update(['data' => [...$entry->data, 'submitted_at' => now()->toIso8601String()]]);

        $this->assertSame(0, WorkflowSubmissionMessages::notify($entry));
        $this->assertSame(0, WorkflowSubmissionMessages::notifyCheckpoint($entry));
        $this->assertSame(WorkflowStageType::Review, WorkflowStageProgression::currentType($entry->fresh()));
        $this->assertNull($this->completedAt($entry->fresh(), WorkflowStageType::FormSubmission));
        $this->assertFalse(WorkflowStageMessages::hasSentNotification($entry->fresh(), WorkflowStageType::FormSubmission));
        $this->assertSame(0, $this->admin->notifications()->where('data->viewData->workflow_event', 'submission_staff')->count());

        $this->post(route('admin.custom-form-entries.approve', $entry))->assertRedirect();

        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageProgression::currentType($entry->fresh()));
        $this->assertNotNull($this->completedAt($entry->fresh(), WorkflowStageType::FormSubmission));
        $this->assertTrue(WorkflowStageMessages::hasSentNotification($entry->fresh(), WorkflowStageType::FormSubmission));
        $this->assertSame(1, $this->candidate->notifications()
            ->where('data->viewData->workflow_stage_type', 'form_submission')
            ->where('data->body', 'Deferred candidate submission notification')->count());
        $this->assertSame(1, $this->admin->notifications()
            ->where('data->viewData->workflow_event', 'submission_staff')
            ->where('data->body', 'Deferred staff submission notification')->count());

        WorkflowStageProgression::initialize($entry);
        WorkflowStageProgression::submitted($entry);
        $this->assertFalse(WorkflowStageProgression::reviewed($entry));
        $this->assertSame(0, WorkflowSubmissionMessages::notify($entry));
        $this->assertSame(0, WorkflowSubmissionMessages::notifyCheckpoint($entry));
        $this->assertSame(1, $this->candidate->notifications()->where('data->viewData->workflow_stage_type', 'form_submission')->count());
        $this->assertSame(1, $this->admin->notifications()->where('data->viewData->workflow_event', 'submission_staff')->count());
        $this->assertFalse(WorkflowStageProgression::readyForFinal($entry->fresh()));
        $this->assertFalse(ExamResultResource::getEloquentQuery()->whereKey($entry->id)->exists());
    }

    public function test_a_nonterminal_completed_checkpoint_notifies_once_without_publishing_passed_before_later_results(): void
    {
        [$form, $workflow] = $this->workflow([
            WorkflowStageType::FormSubmission, WorkflowStageType::Review,
            WorkflowStageType::Completed, WorkflowStageType::AwaitingResults,
        ]);
        $stages = $workflow->stages;
        $stages[2]['data']['audience_messages'] = [
            'staff_notification_message' => 'Completed checkpoint staff notification',
            'staff_recipient_roles' => ['admin'],
        ];
        $workflow->update(['stages' => $stages]);
        $entry = $this->entry($form);
        WorkflowStageProgression::submitted($entry);
        $this->assertFalse(WorkflowStageMessages::notifyCheckpoint($entry, WorkflowStageType::Completed));
        $this->post(route('admin.custom-form-entries.approve', $entry))->assertRedirect();

        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageMessages::stageTypeFor($entry->fresh()));
        $this->assertNotNull($this->completedAt($entry->fresh(), WorkflowStageType::Completed));
        $this->assertFalse(WorkflowStageProgression::readyForFinal($entry->fresh()));
        $this->assertFalse(ExamResultResource::getEloquentQuery()->whereKey($entry->id)->exists());
        $this->assertSame('pending', data_get($entry->fresh()->data, 'candidate_status'));
        $this->assertNull(data_get($entry->fresh()->data, 'workflow_progress.final_notified_at'));
        $this->assertNotSame('completed status', WorkflowStageMessages::statusText($entry->fresh()));
        $this->assertTrue(WorkflowStageMessages::hasSentNotification($entry->fresh(), WorkflowStageType::Completed));
        $this->assertSame(1, $this->candidate->notifications()
            ->where('data->viewData->workflow_stage_type', 'completed')
            ->where('data->body', 'Completed notification')->count());
        $this->assertSame(1, $this->admin->notifications()
            ->where('data->viewData->workflow_event', 'stage_staff:completed')
            ->where('data->body', 'Completed checkpoint staff notification')->count());

        WorkflowStageProgression::initialize($entry);
        WorkflowStageProgression::submitted($entry);
        $this->assertFalse(WorkflowStageMessages::notifyCheckpoint($entry, WorkflowStageType::Completed));
        $this->assertSame(1, $this->candidate->notifications()->where('data->viewData->workflow_stage_type', 'completed')->count());
        $this->assertSame(1, $this->admin->notifications()->where('data->viewData->workflow_event', 'stage_staff:completed')->count());

        $this->performEngineEvent($entry, WorkflowStageType::AwaitingResults);
        $this->assertTrue(WorkflowStageProgression::finished($entry->fresh()));
        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageProgression::finalStageType($entry->fresh()));
        $this->assertTrue(ExamResultResource::getEloquentQuery()->whereKey($entry->id)->exists());
    }

    public function test_terminal_completed_label_and_notification_are_released_only_after_the_last_gate(): void
    {
        [$form] = $this->workflow([
            WorkflowStageType::FormSubmission, WorkflowStageType::Review, WorkflowStageType::Completed,
        ]);
        $entry = $this->entry($form);
        WorkflowStageProgression::submitted($entry);
        $this->assertFalse(WorkflowStageMessages::notifyConfiguredStage($entry, WorkflowStageType::Completed));
        $this->assertFalse(WorkflowStageMessages::hasSentNotification($entry->fresh(), WorkflowStageType::Completed));
        $this->post(route('admin.custom-form-entries.approve', $entry))->assertRedirect();

        $this->assertSame(WorkflowStageType::Completed, WorkflowStageProgression::currentType($entry->fresh()));
        $this->assertTrue(WorkflowStageProgression::readyForFinal($entry->fresh()));
        $this->assertFalse(WorkflowStageProgression::finished($entry->fresh()));
        $this->assertSame(__('candidate_entrance_statistics.statuses.passed'), WorkflowStageMessages::statusText($entry->fresh()));
        $component = Livewire::test(ListExamResults::class)->assertCanSeeTableRecords([$entry])
            ->callTableAction('notify_student', $entry)->assertHasNoTableActionErrors();
        $this->assertTrue(WorkflowStageMessages::hasSentNotification($entry->fresh(), WorkflowStageType::Completed));
        $this->assertTrue(WorkflowStageProgression::finished($entry->fresh()));
        $this->assertSame('completed status', WorkflowStageMessages::statusText($entry->fresh()));
        $component->call('$refresh')->assertTableActionHidden('notify_student', $entry);
        $this->assertSame(1, $this->candidate->notifications()->where('data->viewData->workflow_stage_type', 'completed')->count());
    }

    public function test_a_workflow_without_review_can_start_with_payment_and_keeps_its_payment_gate(): void
    {
        [$form] = $this->workflow([
            WorkflowStageType::FormSubmission, WorkflowStageType::Payment, WorkflowStageType::Completed,
        ]);
        $entry = $this->entry($form);
        WorkflowStageProgression::submitted($entry);

        $this->assertSame(WorkflowStageType::Payment, WorkflowStageMessages::stageTypeFor($entry->fresh()));
        $this->assertFalse(WorkflowStageProgression::paid($entry));
        $this->assertFalse(WorkflowStageProgression::readyForFinal($entry->fresh()));
        app(CandidatePaymentRecorder::class)->record([
            'custom_form_entry_id' => $entry->id, 'receipt_number' => 'PAYMENT-FIRST-'.$entry->id,
            'amount_usd' => 10, 'datetime_pay' => now(),
        ]);

        $this->assertSame(WorkflowStageType::Completed, WorkflowStageProgression::currentType($entry->fresh()));
        $this->assertTrue(WorkflowStageProgression::readyForFinal($entry->fresh()));
        $this->assertFalse(WorkflowStageProgression::finished($entry->fresh()));
        $this->assertFalse(WorkflowStageMessages::hasSentNotification($entry->fresh(), WorkflowStageType::Completed));
    }

    public function test_authenticated_ams_payment_before_review_advances_to_review_without_finishing(): void
    {
        config()->set('services.ams', [
            'enabled' => true, 'base_url' => 'https://ams.example.test', 'token' => 'outbound-secret',
            'callback_token' => 'callback-secret', 'timeout' => 5,
        ]);
        Http::swap(new HttpFactory);
        Http::fake(fn ($request) => Http::response([
            'application_id' => $request['application_id'], 'ams_request_id' => 'ordered-ams-request', 'payment_status' => 'unpaid',
        ], 201));
        [$form, $workflow] = $this->workflow([
            WorkflowStageType::FormSubmission, WorkflowStageType::Payment,
            WorkflowStageType::Review, WorkflowStageType::Completed,
        ]);
        $stages = $workflow->stages;
        $stages[1]['data']['payment_source'] = 'ams';
        $workflow->update(['stages' => $stages]);
        $entry = $this->entry($form);
        WorkflowStageProgression::submitted($entry);
        $this->assertDatabaseHas('ams_payment_requests', [
            'custom_form_entry_id' => $entry->id, 'ams_request_id' => 'ordered-ams-request', 'sync_status' => 'synced',
        ]);
        $payload = [
            'application_id' => (string) $entry->id, 'ams_request_id' => 'ordered-ams-request',
            'payment_id' => 'ordered-ams-payment', 'event_version' => 1, 'status' => 'paid',
            'receipt_number' => 'AMS-ORDERED', 'paid_at' => '2026-10-30 10:15:00',
            'amount_usd' => 10, 'amount_khr' => null, 'exchange_rate' => 4100, 'cashier_name' => 'AMS Cashier',
        ];

        $this->postJson('/api/external/ams/other-fee-payments', $payload, ['Authorization' => 'Bearer callback-secret'])->assertOk();
        $this->assertSame(WorkflowStageType::Review, WorkflowStageProgression::currentType($entry->fresh()));
        $this->assertFalse(WorkflowStageProgression::readyForFinal($entry->fresh()));
        $this->assertFalse(WorkflowStageProgression::finished($entry->fresh()));
        $this->assertFalse(ExamResultResource::getEloquentQuery()->whereKey($entry->id)->exists());
        $this->assertDatabaseHas('payments', ['custom_form_entry_id' => $entry->id, 'integration_source' => 'ams', 'status_payt' => 'paid']);
        $this->post(route('admin.custom-form-entries.approve', $entry))->assertRedirect();
        $this->assertSame(WorkflowStageType::Completed, WorkflowStageProgression::currentType($entry->fresh()));
        $this->assertTrue(WorkflowStageProgression::readyForFinal($entry->fresh()));
        $this->assertFalse(WorkflowStageMessages::hasSentNotification($entry->fresh(), WorkflowStageType::Completed));
        Http::assertSentCount(1);
        Mail::assertNothingSent();
    }

    public function test_results_action_hands_off_to_ams_once_when_payment_is_the_next_configured_stage(): void
    {
        config()->set('services.ams', [
            'enabled' => true, 'base_url' => 'https://ams.example.test', 'token' => 'outbound-secret',
            'callback_token' => 'callback-secret', 'timeout' => 5,
        ]);
        Http::swap(new HttpFactory);
        Http::fake(fn ($request) => Http::response([
            'application_id' => $request['application_id'], 'ams_request_id' => 'ams-after-results', 'payment_status' => 'unpaid',
        ], 201));
        [$form, $workflow] = $this->workflow([
            WorkflowStageType::FormSubmission, WorkflowStageType::AwaitingResults,
            WorkflowStageType::Payment, WorkflowStageType::Review, WorkflowStageType::Completed,
        ]);
        $stages = $workflow->stages;
        $stages[2]['data']['payment_source'] = 'ams';
        $workflow->update(['stages' => $stages]);
        $entry = $this->entry($form);
        WorkflowStageProgression::submitted($entry);
        $this->assertDatabaseMissing('ams_payment_requests', ['custom_form_entry_id' => $entry->id]);
        Http::assertNothingSent();

        Livewire::test(ListCandidateEntranceStatistics::class)->assertCanSeeTableRecords([$entry])
            ->callTableAction('passed', $entry)->assertHasNoTableActionErrors();

        $this->assertSame(WorkflowStageType::Payment, WorkflowStageProgression::currentType($entry->fresh()));
        $this->assertDatabaseHas('ams_payment_requests', [
            'custom_form_entry_id' => $entry->id, 'ams_request_id' => 'ams-after-results', 'sync_status' => 'synced',
        ]);
        $this->assertSame(1, AmsPaymentRequest::query()->where('custom_form_entry_id', $entry->id)->count());
        Http::assertSent(fn ($request): bool => $request->url() === 'https://ams.example.test/api/external/candidate/other-fees'
            && (string) $request['application_id'] === (string) $entry->id);
        Http::assertSentCount(1);
        WorkflowStageProgression::initialize($entry);
        WorkflowStageProgression::submitted($entry);
        Http::assertSentCount(1);
        $this->assertDatabaseCount('payments', 0);
        Mail::assertNothingSent();
    }

    public function test_rejected_or_failed_review_does_not_allow_future_result_or_payment_events(): void
    {
        [$form] = $this->workflow([
            WorkflowStageType::FormSubmission, WorkflowStageType::Review,
            WorkflowStageType::AwaitingResults, WorkflowStageType::Payment, WorkflowStageType::Completed,
        ]);
        foreach (['rejected', 'failed'] as $status) {
            $entry = $this->entry($form);
            WorkflowStageProgression::submitted($entry);
            $data = $entry->fresh()->data;
            $data['registration_status'] = $status;
            DB::table('custom_form_entries')->where('id', $entry->id)->update(['review_status' => $status, 'data' => json_encode($data)]);
            $entry->refresh();
            $before = data_get($entry->data, 'workflow_progress');

            WorkflowStageProgression::reviewed($entry);
            WorkflowStageProgression::passed($entry);
            WorkflowStageProgression::paid($entry);
            $this->assertSame($status, $entry->fresh()->review_status);
            $this->assertSame($before, data_get($entry->fresh()->data, 'workflow_progress'));
            $this->assertFalse(WorkflowStageProgression::readyForFinal($entry->fresh()));
            $this->assertFalse(ExamResultResource::getEloquentQuery()->whereKey($entry->id)->exists());
        }
    }

    public static function gatesBeforeReturnedReview(): array
    {
        return [
            'results before review' => [WorkflowStageType::AwaitingResults],
            'payment before review' => [WorkflowStageType::Payment],
        ];
    }

    #[DataProvider('gatesBeforeReturnedReview')]
    public function test_candidate_resubmission_preserves_completed_earlier_gates_and_returns_to_review(WorkflowStageType $preceding): void
    {
        $this->candidate->assignRole(Role::findOrCreate('candidate', 'web'));
        foreach (['ViewAny', 'View', 'Update'] as $action) {
            $this->candidate->givePermissionTo(Permission::findOrCreate($action.':CustomFormEntry', 'web'));
        }
        $profile = CustomForm::query()->firstOrCreate(['slug' => 'profile'], ['name' => 'Profile', 'is_active' => true]);
        CustomFormEntry::query()->create([
            'custom_form_id' => $profile->id, 'created_by' => $this->candidate->id, 'data' => ['registration_status' => 'pending'],
        ]);
        $order = [WorkflowStageType::FormSubmission, $preceding, WorkflowStageType::Review];
        if ($preceding === WorkflowStageType::AwaitingResults) {
            $order[] = WorkflowStageType::Payment;
        }
        $order[] = WorkflowStageType::Completed;
        [$form] = $this->workflow($order);
        $form->fields()->create(['name' => 'correction', 'label' => 'Correction', 'type' => 'text_input', 'required' => true, 'sort' => 1]);
        $entry = $this->entry($form);
        WorkflowStageProgression::submitted($entry);
        if ($preceding === WorkflowStageType::AwaitingResults) {
            Livewire::test(ListCandidateEntranceStatistics::class)
                ->callTableAction('passed', $entry)->assertHasNoTableActionErrors();
        } else {
            app(CandidatePaymentRecorder::class)->record([
                'custom_form_entry_id' => $entry->id, 'receipt_number' => 'BEFORE-RETURN-'.$entry->id,
                'amount_usd' => 10, 'datetime_pay' => now(),
            ]);
        }
        $entry->refresh();
        $snapshot = $entry->workflow_snapshot;
        $completedAt = $this->completedAt($entry, $preceding);
        $flag = $preceding === WorkflowStageType::Payment ? 'payment_completed' : 'result_passed';
        $this->assertSame(WorkflowStageType::Review, WorkflowStageProgression::currentType($entry));
        $this->assertTrue(data_get($entry->data, 'workflow_progress.'.$flag));
        $resultNotifications = $this->candidate->notifications()->where('data->viewData->workflow_stage_type', 'awaiting_results')->count();
        $this->post(route('admin.custom-form-entries.reject', $entry), ['review_note' => 'Please correct this field'])->assertRedirect();
        $this->assertSame('rejected', $entry->fresh()->review_status);

        Livewire::actingAs($this->candidate)->test(EditCustomFormEntry::class, ['record' => $entry->id])
            ->assertSuccessful()
            ->set('data.data', ['correction' => 'Corrected candidate answer', 'workflow_progress' => [
                'version' => 1, 'finished' => true, 'ready_for_final' => true, 'current_type' => 'completed',
            ]])
            ->callAction(TestAction::make('save')->schemaComponent('form-actions', schema: 'content'))
            ->assertHasNoFormErrors();

        $entry->refresh();
        $this->assertSame('Corrected candidate answer', data_get($entry->data, 'correction'));
        $this->assertSame($snapshot, $entry->workflow_snapshot);
        $this->assertSame(WorkflowStageType::Review, WorkflowStageProgression::currentType($entry));
        $this->assertSame(WorkflowStageType::Review, WorkflowStageMessages::stageTypeFor($entry));
        $this->assertSame('pending', $entry->review_status);
        $this->assertSame('pending', data_get($entry->data, 'registration_status'));
        $this->assertSame($preceding === WorkflowStageType::AwaitingResults ? 'passed' : 'pending', data_get($entry->data, 'candidate_status'));
        $this->assertTrue(data_get($entry->data, 'workflow_progress.'.$flag));
        $this->assertSame($completedAt, $this->completedAt($entry, $preceding));
        $this->assertFalse(WorkflowStageProgression::readyForFinal($entry));
        $this->assertFalse(WorkflowStageProgression::finished($entry));
        $this->assertSame($resultNotifications, $this->candidate->notifications()->where('data->viewData->workflow_stage_type', 'awaiting_results')->count());
        $this->assertDatabaseCount('payments', $preceding === WorkflowStageType::Payment ? 1 : 0);

        $this->actingAs($this->admin)->post(route('admin.custom-form-entries.approve', $entry))->assertRedirect();
        $this->assertSame($preceding === WorkflowStageType::Payment ? WorkflowStageType::Completed : WorkflowStageType::Payment, WorkflowStageProgression::currentType($entry->fresh()));
        $this->assertSame($completedAt, $this->completedAt($entry->fresh(), $preceding));
        $this->assertFalse(WorkflowStageMessages::hasSentNotification($entry->fresh(), WorkflowStageType::Completed));
        $this->assertDatabaseCount('payments', $preceding === WorkflowStageType::Payment ? 1 : 0);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_payment_reversal_rewinds_later_stages_without_erasing_earlier_result_and_review_gates(): void
    {
        [$form] = $this->workflow([
            WorkflowStageType::FormSubmission, WorkflowStageType::AwaitingResults,
            WorkflowStageType::Review, WorkflowStageType::Payment, WorkflowStageType::Completed,
        ]);
        $entry = $this->entry($form);
        WorkflowStageProgression::submitted($entry);
        $this->performEngineEvent($entry, WorkflowStageType::AwaitingResults);
        $this->performEngineEvent($entry, WorkflowStageType::Review);
        $payment = $this->payment($entry);
        WorkflowStageProgression::paid($entry);
        $this->assertTrue(WorkflowStageProgression::readyForFinal($entry->fresh()));

        $payment->update(['status_payt' => 'return']);
        WorkflowStageProgression::rollbackUnpaidAutoCompletion($entry);
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageProgression::currentType($entry->fresh()));
        $this->assertNotNull($this->completedAt($entry->fresh(), WorkflowStageType::Review));
        $this->assertNotNull($this->completedAt($entry->fresh(), WorkflowStageType::AwaitingResults));
        $this->assertFalse(WorkflowStageProgression::readyForFinal($entry->fresh()));
        $this->assertFalse(ExamResultResource::getEloquentQuery()->whereKey($entry->id)->exists());

        $payment->update(['status_payt' => 'paid']);
        WorkflowStageProgression::paid($entry);
        $this->assertSame(WorkflowStageType::Completed, WorkflowStageProgression::currentType($entry->fresh()));
        $this->assertTrue(WorkflowStageProgression::readyForFinal($entry->fresh()));
        $this->assertFalse(WorkflowStageMessages::hasSentNotification($entry->fresh(), WorkflowStageType::Completed));
    }

    public function test_only_the_selected_role_for_the_actual_current_stage_can_handle_it(): void
    {
        [$form, $workflow] = $this->workflow([
            WorkflowStageType::FormSubmission, WorkflowStageType::AwaitingResults, WorkflowStageType::Review,
        ]);
        $stages = $workflow->stages;
        $stages[1]['data']['responsible_role'] = ['registrar_officer'];
        $stages[2]['data']['responsible_role'] = ['registrar_manager'];
        $workflow->update(['stages' => $stages]);
        $entry = $this->entry($form);
        WorkflowStageProgression::submitted($entry);
        $officer = $this->user('staff');
        $officer->assignRole(Role::findOrCreate('registrar_officer', 'web'));
        $manager = $this->user('staff');
        $manager->assignRole(Role::findOrCreate('registrar_manager', 'web'));

        $this->actingAs($officer);
        $this->assertTrue(WorkflowStageMessages::canCurrentUserHandleStage($entry, WorkflowStageType::AwaitingResults));
        $this->assertFalse(WorkflowStageMessages::canCurrentUserHandleStage($entry, WorkflowStageType::Review));
        $this->actingAs($manager);
        $this->assertFalse(WorkflowStageMessages::canCurrentUserHandleStage($entry, WorkflowStageType::AwaitingResults));
        $this->assertFalse(WorkflowStageMessages::canCurrentUserHandleStage($entry, WorkflowStageType::Review));

        $this->performEngineEvent($entry, WorkflowStageType::AwaitingResults);
        $this->assertTrue(WorkflowStageMessages::canCurrentUserHandleStage($entry->fresh(), WorkflowStageType::Review));
        $this->actingAs($officer);
        $this->assertFalse(WorkflowStageMessages::canCurrentUserHandleStage($entry->fresh(), WorkflowStageType::Review));
    }

    public function test_frozen_order_and_version_one_or_static_entries_are_not_reinterpreted_by_template_edits(): void
    {
        [$form, $workflow] = $this->workflow([
            WorkflowStageType::FormSubmission, WorkflowStageType::AwaitingResults, WorkflowStageType::Review, WorkflowStageType::Payment,
        ]);
        $frozen = $this->entry($form);
        $legacy = $this->entry($form, version: 1);
        $workflow->update(['stages' => $this->stages([
            WorkflowStageType::FormSubmission, WorkflowStageType::Review, WorkflowStageType::Completed,
        ])]);
        $updated = $this->entry($form);
        $workflow->forms()->detach($form);
        $static = $this->entry($form);

        WorkflowStageProgression::submitted($frozen);
        WorkflowStageProgression::submitted($updated);
        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageProgression::currentType($frozen->fresh()));
        $this->assertSame(WorkflowStageType::Review, WorkflowStageProgression::currentType($updated->fresh()));
        foreach ([$legacy, $static] as $entry) {
            $before = $entry->fresh()->getAttributes();
            $this->assertFalse(WorkflowStageProgression::enabled($entry));
            WorkflowStageProgression::initialize($entry);
            WorkflowStageProgression::submitted($entry);
            WorkflowStageProgression::reviewed($entry);
            $this->assertSame($before['data'], $entry->fresh()->getRawOriginal('data'));
            $this->assertSame($before['review_status'], $entry->fresh()->review_status);
            $this->assertNull(data_get($entry->fresh()->data, 'workflow_progress'));
        }
        $this->assertSame(1, $legacy->fresh()->workflow_snapshot['schema_version']);
    }

    private function performEngineEvent(CustomFormEntry $entry, WorkflowStageType $type, bool $prepare = true): void
    {
        if ($prepare) {
            $data = $entry->fresh()->data;
            if ($type === WorkflowStageType::Review) {
                $data['registration_status'] = 'approved';
                DB::table('custom_form_entries')->where('id', $entry->id)->update(['review_status' => 'approved', 'data' => json_encode($data)]);
            } elseif ($type === WorkflowStageType::AwaitingResults) {
                $data['candidate_status'] = 'passed';
                DB::table('custom_form_entries')->where('id', $entry->id)->update(['review_status' => 'passed', 'data' => json_encode($data)]);
            } elseif ($type === WorkflowStageType::Payment) {
                $this->payment($entry);
            }
            $entry->refresh();
        }
        match ($type) {
            WorkflowStageType::Review => WorkflowStageProgression::reviewed($entry),
            WorkflowStageType::Payment => WorkflowStageProgression::paid($entry),
            WorkflowStageType::AwaitingResults => WorkflowStageProgression::passed($entry),
            default => null,
        };
    }

    private function completedAt(CustomFormEntry $entry, WorkflowStageType $type): ?string
    {
        foreach ($entry->workflow_snapshot['stages'] as $index => $stage) {
            if (data_get($stage, 'data.stage_type') === $type->value) {
                return WorkflowStageProgression::completedAt($entry, $index);
            }
        }

        return null;
    }

    private function workflow(array $order): array
    {
        $form = CustomForm::query()->create([
            'name' => 'Ordered workflow', 'slug' => 'ordered-'.uniqid(), 'is_active' => true,
            'allowed_roles' => ['candidate', 'admin'], 'requires_payment' => true,
            'statistics_menu' => StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS,
            'passed_result_menu' => PassedResultMenuOptions::EXAM_RESULTS,
        ]);
        $workflow = WorkflowNotification::query()->create(['name' => 'Ordered stages', 'stages' => $this->stages($order)]);
        $workflow->forms()->attach($form);

        return [$form, $workflow];
    }

    private function stages(array $order): array
    {
        return array_map(fn (WorkflowStageType $type): array => ['type' => 'stage', 'data' => [
            'stage_type' => $type->value, 'stage_name' => $type->label(), 'responsible_role' => ['admin'],
            'payment_source' => 'system', 'status_message' => $type->value.' status',
            'notification_message' => $type === WorkflowStageType::Completed ? 'Completed notification' : $type->value.' notification',
            'notification_channels' => ['system'], 'review_actions' => ['accept' => ['status_message' => 'Checked', 'notification_message' => 'Checked notification']],
        ]], $order);
    }

    private function entry(CustomForm $form, ?int $version = null): CustomFormEntry
    {
        $entry = CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $form->id, 'created_by' => $this->candidate->id,
            'review_status' => 'pending', 'data' => ['candidate_status' => 'pending', 'registration_status' => 'pending'],
        ]);
        if ($version !== null) {
            $snapshot = $entry->workflow_snapshot;
            $snapshot['schema_version'] = $version;
            $data = $entry->data;
            unset($data['workflow_progress']);
            DB::table('custom_form_entries')->where('id', $entry->id)->update([
                'workflow_snapshot' => json_encode($snapshot), 'data' => json_encode($data),
            ]);
            $entry->refresh();
        }

        return $entry;
    }

    private function payment(CustomFormEntry $entry): Payment
    {
        return Payment::query()->forceCreate([
            'users_id' => $entry->created_by, 'form_id' => $entry->custom_form_id, 'custom_form_entry_id' => $entry->id,
            'receipt_number' => 'ORDERED-'.$entry->id, 'status_payt' => 'paid', 'amount_usd' => 10, 'datetime_pay' => now(),
        ]);
    }

    private function user(string $type): User
    {
        $name = 'ordered_'.$type.'_'.uniqid();

        return User::query()->forceCreate([
            'registration_type' => $type, 'name' => $name, 'username' => $name, 'email' => $name.'@example.test',
            'password' => 'password', 'is_active' => true, 'date_of_birth' => '2000-01-01',
        ]);
    }

    private function permutations(array $types): array
    {
        if ($types === []) {
            return [[]];
        }
        $orders = [];
        foreach ($types as $index => $type) {
            $remaining = $types;
            unset($remaining[$index]);
            foreach ($this->permutations(array_values($remaining)) as $tail) {
                $orders[] = array_merge([$type], $tail);
            }
        }

        return $orders;
    }
}
