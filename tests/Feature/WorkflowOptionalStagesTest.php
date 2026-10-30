<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\ExamResults\ExamResultResource;
use App\Filament\Admin\Resources\ExamResults\Pages\ListExamResults;
use App\Filament\Admin\Resources\ExitExamResults\ExitExamResultResource;
use App\Filament\Admin\Resources\ExitExamResults\Pages\ListExitExamResults;
use App\Models\AmsPaymentRequest;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Services\CandidatePaymentRecorder;
use App\Support\PassedResultMenuOptions;
use App\Support\StatisticsMenuOptions;
use App\Support\WorkflowEntrySnapshot;
use App\Support\WorkflowStageMessages;
use App\Support\WorkflowStageProgression;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\ListCustomFormEntries;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkflowOptionalStagesTest extends TestCase
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
        foreach (['ViewAny:CustomFormEntry', 'Accepted:CustomFormEntry', 'Reject:CustomFormEntry', 'Rejected:CustomFormEntry', 'ViewPdf:CustomFormEntry'] as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $this->admin->assignRole($role);
        $this->candidate = $this->user('student');
        $this->actingAs($this->admin);
    }

    public static function directReviewRoutes(): array
    {
        return [
            'entrance table' => [PassedResultMenuOptions::EXAM_RESULTS, false],
            'entrance PDF' => [PassedResultMenuOptions::EXAM_RESULTS, true],
            'exit table' => [PassedResultMenuOptions::EXIT_EXAM_RESULTS, false],
            'exit PDF' => [PassedResultMenuOptions::EXIT_EXAM_RESULTS, true],
        ];
    }

    #[DataProvider('directReviewRoutes')]
    public function test_checked_skips_absent_payment_and_results_and_waits_for_notify_before_releasing_completion(string $resultMenu, bool $pdf): void
    {
        [$form] = $this->workflow(false, false, $resultMenu);
        $entry = $this->entry($form);
        $this->check($entry, $pdf);

        $entry->refresh();
        $this->assertPassed($entry);
        $this->assertSame(WorkflowStageType::Completed, WorkflowStageMessages::stageTypeFor($entry));
        $this->assertResultRouting($entry, $resultMenu);
        $this->assertFalse(WorkflowStageMessages::hasSentNotification($entry, WorkflowStageType::Completed));
        $this->assertSame(__('candidate_entrance_statistics.statuses.passed'), $this->label($entry));
        $this->assertSame(__('candidate_entrance_statistics.statuses.passed'), $this->label($entry, true));
        $this->assertNotContains('Final success notification', $this->candidate->notifications()->get()->pluck('data.body')->all());
        $this->assertDatabaseCount('payments', 0);

        $page = $resultMenu === PassedResultMenuOptions::EXIT_EXAM_RESULTS ? ListExitExamResults::class : ListExamResults::class;
        $this->actingAs($this->admin);
        Livewire::test($page)->assertCanSeeTableRecords([$entry])
            ->callTableAction('notify_student', $entry)->assertHasNoTableActionErrors();

        $this->assertTrue(WorkflowStageMessages::hasSentNotification($entry->fresh(), WorkflowStageType::Completed));
        $this->assertSame('ជោគជ័យ', $this->label($entry));
        $this->assertSame('ជោគជ័យ', $this->label($entry, true));
        $this->assertPassed($entry->fresh());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public static function waitingStages(): array
    {
        return [
            'no payment, waiting for results' => [false, true],
            'payment, waiting for payment' => [true, false],
            'payment and results' => [true, true],
        ];
    }

    #[DataProvider('waitingStages')]
    public function test_optional_stage_matrix_preserves_each_configured_gate(bool $payment, bool $results): void
    {
        [$form] = $this->workflow($payment, $results);
        $entry = $this->entry($form);
        $this->check($entry);

        $this->assertSame('approved', $entry->fresh()->review_status);
        $this->assertSame('pending', data_get($entry->fresh()->data, 'candidate_status'));
        $this->assertSame($payment ? WorkflowStageType::Payment : WorkflowStageType::AwaitingResults, WorkflowStageMessages::stageTypeFor($entry->fresh()));
        $this->assertFalse(ExamResultResource::getEloquentQuery()->whereKey($entry->id)->exists());
        $this->assertFalse(WorkflowStageMessages::hasSentNotification($entry, WorkflowStageType::Completed));

        if ($payment) {
            $this->payment($entry);
            WorkflowStageProgression::advance($entry->fresh());
            if ($results) {
                $this->assertSame('approved', $entry->fresh()->review_status);
                $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageMessages::stageTypeFor($entry->fresh()));
                $this->assertFalse(ExamResultResource::getEloquentQuery()->whereKey($entry->id)->exists());
            } else {
                $this->assertPassed($entry->fresh());
                $this->assertSame(WorkflowStageType::Completed, WorkflowStageMessages::stageTypeFor($entry->fresh()));
                $this->assertResultRouting($entry, PassedResultMenuOptions::EXAM_RESULTS);
            }
        }

        $this->assertFalse(WorkflowStageMessages::hasSentNotification($entry->fresh(), WorkflowStageType::Completed));
    }

    public function test_payment_gate_requires_the_exact_paid_application_not_a_synced_ams_request(): void
    {
        [$form] = $this->workflow(true, false);
        $entry = $this->entry($form, 'approved');
        $other = $this->entry($form, 'approved');
        $this->payment($other, 'ams');
        AmsPaymentRequest::query()->create([
            'custom_form_entry_id' => $entry->id, 'ams_request_id' => 'request-'.$entry->id,
            'sync_status' => 'synced', 'synced_at' => now(),
        ]);

        $this->assertFalse(WorkflowStageProgression::advance($entry));
        $this->assertSame('approved', $entry->fresh()->review_status);
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageMessages::stageTypeFor($entry->fresh()));
        $this->payment($entry, 'ams');
        $this->assertTrue(WorkflowStageProgression::advance($entry->fresh()));
        $this->assertPassed($entry->fresh());
        $this->assertFalse(WorkflowStageProgression::advance($entry->fresh()));
        $this->assertFalse(WorkflowStageMessages::hasSentNotification($entry->fresh(), WorkflowStageType::Completed));
    }

    public function test_payment_reversal_rolls_back_only_automatic_completion(): void
    {
        [$form] = $this->workflow(true, false);
        $entry = $this->entry($form, 'approved');
        $payment = $this->payment($entry, 'ams');
        WorkflowStageProgression::advance($entry);
        $this->assertPassed($entry->fresh());
        $this->assertTrue(WorkflowStageMessages::notifyConfiguredStage($entry->fresh(), WorkflowStageType::Completed));
        $this->assertTrue(WorkflowStageMessages::hasSentNotification($entry->fresh(), WorkflowStageType::Completed));

        $payment->update(['status_payt' => 'return']);
        $this->assertTrue(WorkflowStageProgression::rollbackUnpaidAutoCompletion($entry->fresh()));
        $this->assertSame('approved', $entry->fresh()->review_status);
        $this->assertSame('pending', data_get($entry->fresh()->data, 'candidate_status'));
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageMessages::stageTypeFor($entry->fresh()));
        $this->assertFalse(ExamResultResource::getEloquentQuery()->whereKey($entry->id)->exists());
        $this->assertFalse(WorkflowStageMessages::hasSentNotification($entry->fresh(), WorkflowStageType::Completed));
        $payment->update(['status_payt' => 'paid']);
        $this->assertTrue(WorkflowStageProgression::advance($entry->fresh()));
        $this->assertFalse(WorkflowStageMessages::hasSentNotification($entry->fresh(), WorkflowStageType::Completed));
        $this->assertSame(__('candidate_entrance_statistics.statuses.passed'), $this->label($entry));

        $manual = $this->entry($form, 'passed');
        $this->assertFalse(WorkflowStageProgression::rollbackUnpaidAutoCompletion($manual));
        $this->assertSame('passed', $manual->fresh()->review_status);
    }

    public function test_template_edits_do_not_change_optional_gates_for_existing_applications(): void
    {
        [$form, $workflow] = $this->workflow(false, false);
        $direct = $this->entry($form);
        $workflow->update(['stages' => $this->stages(true, true)]);
        $gated = $this->entry($form);
        $this->check($direct);
        $this->check($gated);

        $this->assertPassed($direct->fresh());
        $this->assertSame('approved', $gated->fresh()->review_status);
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageMessages::stageTypeFor($gated->fresh()));

        $workflow->update(['stages' => $this->stages(false, false)]);
        $this->assertFalse(WorkflowStageProgression::advance($gated->fresh()));
        $this->assertSame('approved', $gated->fresh()->review_status);
        $newDirect = $this->entry($form);
        $this->check($newDirect);
        $this->assertPassed($newDirect->fresh());
    }

    public function test_local_payment_recorder_automatically_completes_only_a_workflow_without_results(): void
    {
        [$form] = $this->workflow(true, false);
        $entry = $this->entry($form, 'approved');
        app(CandidatePaymentRecorder::class)->record([
            'custom_form_entry_id' => $entry->id, 'receipt_number' => 'LOCAL-OPTIONAL',
            'amount_usd' => 10, 'datetime_pay' => now(),
        ]);

        $this->assertPassed($entry->fresh());
        $this->assertSame(WorkflowStageType::Completed, WorkflowStageMessages::stageTypeFor($entry->fresh()));
        $this->assertFalse(WorkflowStageMessages::hasSentNotification($entry->fresh(), WorkflowStageType::Completed));
        $this->assertResultRouting($entry, PassedResultMenuOptions::EXAM_RESULTS);
        $this->assertDatabaseHas('payments', ['custom_form_entry_id' => $entry->id, 'status_payt' => 'paid']);
    }

    public function test_verified_ams_paid_and_reversal_callbacks_advance_and_undo_automatic_completion(): void
    {
        config()->set('services.ams', [
            'enabled' => true, 'base_url' => 'https://ams.example.test', 'token' => 'outbound-secret',
            'callback_token' => 'callback-secret', 'timeout' => 5,
        ]);
        [$form, $workflow] = $this->workflow(true, false, PassedResultMenuOptions::EXIT_EXAM_RESULTS);
        $stages = $workflow->stages;
        $stages[1]['data']['payment_source'] = 'ams';
        $workflow->update(['stages' => $stages]);
        $entry = $this->entry($form, 'approved');
        AmsPaymentRequest::query()->create([
            'custom_form_entry_id' => $entry->id, 'ams_request_id' => 'optional-ams-request',
            'sync_status' => 'synced', 'synced_at' => now(),
        ]);
        $payload = [
            'application_id' => (string) $entry->id, 'ams_request_id' => 'optional-ams-request',
            'payment_id' => 'optional-ams-payment', 'event_version' => 1, 'status' => 'paid',
            'receipt_number' => 'AMS-OPTIONAL', 'paid_at' => '2026-10-30 10:15:00',
            'amount_usd' => 10, 'amount_khr' => null, 'exchange_rate' => 4100, 'cashier_name' => 'AMS Cashier',
        ];
        $headers = ['Authorization' => 'Bearer callback-secret'];
        $this->postJson('/api/external/ams/other-fee-payments', $payload, $headers)->assertOk();
        $this->assertPassed($entry->fresh());
        $this->assertResultRouting($entry, PassedResultMenuOptions::EXIT_EXAM_RESULTS);
        $this->assertFalse(WorkflowStageMessages::hasSentNotification($entry->fresh(), WorkflowStageType::Completed));
        $this->postJson('/api/external/ams/other-fee-payments', $payload, $headers)->assertOk();
        $this->assertDatabaseCount('payments', 1);

        $this->postJson('/api/external/ams/other-fee-payments', array_replace($payload, ['event_version' => 2, 'status' => 'unpaid']), $headers)->assertOk();
        $this->assertSame('approved', $entry->fresh()->review_status);
        $this->assertSame('pending', data_get($entry->fresh()->data, 'candidate_status'));
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageMessages::stageTypeFor($entry->fresh()));
        $this->assertFalse(ExitExamResultResource::getEloquentQuery()->whereKey($entry->id)->exists());
        $this->assertDatabaseHas('payments', ['custom_form_entry_id' => $entry->id, 'status_payt' => 'return']);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public static function negativeReviewActions(): array
    {
        return [['send_back', 'rejected'], ['reject', 'failed']];
    }

    #[DataProvider('negativeReviewActions')]
    public function test_send_back_and_reject_do_not_auto_complete(string $action, string $expected): void
    {
        [$form] = $this->workflow(false, false);
        $entry = $this->entry($form);
        if ($action === 'send_back') {
            $this->post(route('admin.custom-form-entries.reject', $entry), ['review_note' => 'Please update'])->assertRedirect();
        } else {
            DocumentTemplate::query()->where('type', 'custom_form_'.$entry->custom_form_id)->delete();
            Livewire::test(ListCustomFormEntries::class)
                ->callTableAction('reject', $entry, data: ['review_note' => 'Cannot accept'])
                ->assertHasNoTableActionErrors();
        }

        $this->assertSame($expected, $entry->fresh()->review_status);
        $this->assertFalse(WorkflowStageProgression::advance($entry->fresh()));
        $this->assertFalse(ExamResultResource::getEloquentQuery()->whereKey($entry->id)->exists());
        $this->assertFalse(WorkflowStageMessages::hasSentNotification($entry->fresh(), WorkflowStageType::Completed));
    }

    public function test_static_forms_and_workflows_without_completed_are_not_automatically_passed(): void
    {
        [$form, $workflow] = $this->workflow(false, false);
        $workflow->update(['stages' => [$this->stages(false, false)[0]]]);
        $configured = $this->entry($form, 'approved');
        $workflow->forms()->detach($form);
        $static = $this->entry($form, 'approved');

        foreach ([$configured, $static] as $entry) {
            $this->assertFalse(WorkflowStageProgression::advance($entry));
            $this->assertSame('approved', $entry->fresh()->review_status);
            $this->assertSame('pending', data_get($entry->fresh()->data, 'candidate_status'));
        }
        $this->assertFalse(WorkflowStageMessages::hasConfiguredWorkflow($static));
    }

    private function workflow(bool $payment, bool $results, string $resultMenu = PassedResultMenuOptions::EXAM_RESULTS): array
    {
        $form = CustomForm::query()->create([
            'name' => 'Optional stages', 'slug' => 'optional-stages-'.uniqid(), 'is_active' => true,
            'allowed_roles' => ['candidate', 'admin'], 'requires_payment' => true,
            'statistics_menu' => $resultMenu === PassedResultMenuOptions::EXIT_EXAM_RESULTS ? StatisticsMenuOptions::EXIT_EXAM_STATISTICS : StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS,
            'passed_result_menu' => $resultMenu,
        ]);
        $workflow = WorkflowNotification::query()->create(['name' => 'Optional gates', 'stages' => $this->stages($payment, $results)]);
        $workflow->forms()->attach($form);

        return [$form, $workflow];
    }

    private function stages(bool $payment, bool $results): array
    {
        $stages = [['type' => 'stage', 'data' => [
            'stage_name' => 'Review', 'stage_type' => 'review', 'responsible_role' => ['admin'],
            'notification_channels' => ['system'], 'review_actions' => [
                'accept' => ['status_message' => 'Checked', 'notification_message' => 'Review checked'],
                'send_back' => ['status_message' => 'Return for edits', 'notification_message' => 'Please update'],
                'reject' => ['status_message' => 'Rejected', 'notification_message' => 'Application rejected'],
            ],
        ]]];
        if ($payment) {
            $stages[] = ['type' => 'stage', 'data' => ['stage_name' => 'Payment', 'stage_type' => 'payment', 'payment_source' => 'system', 'responsible_role' => ['admin']]];
        }
        if ($results) {
            $stages[] = ['type' => 'stage', 'data' => ['stage_name' => 'Results', 'stage_type' => 'awaiting_results', 'status_message' => 'Passed result']];
        }
        $stages[] = ['type' => 'stage', 'data' => [
            'stage_name' => 'Completed', 'stage_type' => 'completed', 'status_message' => 'ជោគជ័យ',
            'notification_message' => 'Final success notification', 'notification_channels' => ['system'],
        ]];

        return $stages;
    }

    private function entry(CustomForm $form, string $status = 'pending'): CustomFormEntry
    {
        // These regressions exercise existing applications, before ordered snapshots.
        $snapshot = array_replace(WorkflowEntrySnapshot::forForm($form->id), ['schema_version' => 1]);

        return CustomFormEntry::withoutEvents(fn (): CustomFormEntry => CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $form->id, 'created_by' => $this->candidate->id, 'review_status' => $status,
            'workflow_snapshot' => $snapshot,
            'data' => ['candidate_status' => $status === 'passed' ? 'passed' : 'pending', 'registration_status' => $status],
        ]));
    }

    private function check(CustomFormEntry $entry, bool $pdf = false): void
    {
        $this->actingAs($this->admin);
        if ($pdf) {
            DocumentTemplate::query()->create(['name' => 'Review PDF', 'type' => 'custom_form_'.$entry->custom_form_id, 'content' => '<p>Review</p>']);
            $this->post(route('admin.custom-form-entries.approve', $entry))->assertRedirect();
        } else {
            DocumentTemplate::query()->where('type', 'custom_form_'.$entry->custom_form_id)->delete();
            Livewire::test(ListCustomFormEntries::class)
                ->callTableAction('accepted', $entry)->assertHasNoTableActionErrors();
        }
    }

    private function payment(CustomFormEntry $entry, ?string $source = null): Payment
    {
        return Payment::query()->forceCreate([
            'users_id' => $entry->created_by, 'form_id' => $entry->custom_form_id, 'custom_form_entry_id' => $entry->id,
            'receipt_number' => 'OPTIONAL-'.$entry->id, 'status_payt' => 'paid', 'amount_usd' => 10,
            'datetime_pay' => now(), 'integration_source' => $source, 'integration_payment_id' => $source === 'ams' ? 'ams-'.$entry->id : null,
        ]);
    }

    private function assertPassed(CustomFormEntry $entry): void
    {
        $this->assertSame('passed', $entry->review_status);
        foreach (['candidate_status', 'registration_status', 'exam_result', 'result_status'] as $field) {
            $this->assertSame('passed', data_get($entry->data, $field), $field);
        }
    }

    private function assertResultRouting(CustomFormEntry $entry, string $resultMenu): void
    {
        $this->assertSame($resultMenu === PassedResultMenuOptions::EXAM_RESULTS, ExamResultResource::getEloquentQuery()->whereKey($entry->id)->exists());
        $this->assertSame($resultMenu === PassedResultMenuOptions::EXIT_EXAM_RESULTS, ExitExamResultResource::getEloquentQuery()->whereKey($entry->id)->exists());
    }

    private function label(CustomFormEntry $entry, bool $candidate = false): string
    {
        $this->actingAs($this->admin);
        $column = Livewire::test(ListCustomFormEntries::class)->instance()->getTable()->getColumn('review_status')->record($entry->fresh());
        if ($candidate) {
            $this->actingAs($this->candidate);
        }

        return $column->formatState($column->getState());
    }

    private function user(string $type): User
    {
        $name = 'optional_'.$type.'_'.uniqid();

        return User::query()->forceCreate([
            'registration_type' => $type, 'name' => $name, 'username' => $name,
            'email' => $name.'@example.test', 'password' => 'password', 'is_active' => true, 'date_of_birth' => '2000-01-01',
        ]);
    }
}
