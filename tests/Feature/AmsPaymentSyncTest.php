<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\PaymentStatusLists\Pages\ListPaymentStatusLists;
use App\Models\AmsPaymentRequest;
use App\Models\MefInvoiceCode;
use App\Models\Payment;
use App\Models\PaymentStatusList;
use App\Models\PaymentType;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Services\AmsPaymentSync;
use App\Services\CandidatePaymentRecorder;
use App\Support\WorkflowStageMessages;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AmsPaymentSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;
    private User $staff;
    private CustomForm $form;
    private WorkflowNotification $workflow;
    private bool $remoteFails = false;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        config()->set('services.ams', [
            'enabled' => true, 'base_url' => 'https://ams.example.test',
            'token' => 'candidate-to-ams-secret', 'callback_token' => 'ams-to-candidate-secret', 'timeout' => 5,
        ]);
        Http::fake(fn (Request $request) => $this->remoteFails
            ? Http::response(['message' => 'Temporarily unavailable'], 503)
            : Http::response([
                'application_id' => $request['application_id'], 'ams_request_id' => 'ams-request-1', 'payment_status' => 'unpaid',
            ], 201));
        Mail::fake();
        $this->candidate = $this->user('ams_candidate', 'student');
        $this->staff = $this->user('ams_staff', 'admin');
        $this->staff->assignRole(Role::findOrCreate('admin', 'web'));
        $this->form = CustomForm::query()->create([
            'name' => 'AMS fee application', 'slug' => 'ams-fee-application', 'is_active' => true,
            'allowed_roles' => ['candidate', 'admin'],
        ]);
        $this->workflow = WorkflowNotification::query()->create([
            'name' => 'AMS fees',
            'stages' => [
                ['type' => 'stage', 'data' => [
                    'stage_name' => 'Review', 'stage_type' => 'review', 'responsible_role' => ['admin'],
                    'review_actions' => ['accept' => ['status_message' => 'Checked']],
                ]],
                ['type' => 'stage', 'data' => [
                    'stage_name' => 'Payment', 'stage_type' => 'payment', 'payment_source' => 'ams',
                    'responsible_role' => ['admin'], 'status_message' => 'Paid successfully',
                ]],
                ['type' => 'stage', 'data' => ['stage_name' => 'Results', 'stage_type' => 'awaiting_results']],
            ],
        ]);
        $this->workflow->forms()->attach($this->form);
    }

    public function test_actual_checked_action_pushes_the_exact_application_once_even_without_a_review_notification_body(): void
    {
        $entry = $this->entry('pending', [
            'first_name_kh' => 'សុខ', 'last_name_kh' => 'ដារា',
            'first_name_en' => 'Sok', 'last_name_en' => 'Dara',
            'gender' => 'male', 'date_of_birth' => '2000-02-03', 'phone_number' => '012345678',
        ]);
        $this->actingAs($this->staff);
        $this->post(route('admin.custom-form-entries.approve', $entry))->assertRedirect();

        $entry->refresh();
        $request = AmsPaymentRequest::query()->sole();
        $this->assertSame((int) $entry->getKey(), (int) $request->custom_form_entry_id);
        $this->assertSame('synced', $request->sync_status);
        $this->assertSame('ams-request-1', $request->ams_request_id);
        $this->assertSame('approved', $entry->review_status);
        $this->assertSame('approved', data_get($entry->data, 'registration_status'));
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageMessages::stageTypeFor($entry));
        $this->assertSame('Checked', WorkflowStageMessages::statusText($entry));
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('notifications', 0);
        Http::assertSent(function (Request $http) use ($entry): bool {
            return $http->method() === 'POST'
                && $http->url() === 'https://ams.example.test/api/external/candidate/other-fees'
                && $http->hasHeader('Authorization', 'Bearer candidate-to-ams-secret')
                && (string) $http['application_id'] === (string) $entry->getKey()
                && $http['candidate_identifier'] === null
                && (int) $http['form_id'] === (int) $this->form->getKey()
                && $http['form_name'] === $this->form->name
                && array_key_exists('academic_profile', $http->data())
                && ! array_key_exists('profile', $http->data())
                && data_get($http->data(), 'candidate.name_kh') === 'សុខ ដារា'
                && data_get($http->data(), 'candidate.name_en') === 'Sok Dara'
                && data_get($http->data(), 'candidate.phone') === '012345678';
        });

        app(AmsPaymentSync::class)->sync($entry);
        WorkflowStageMessages::notifyReviewAction($entry, 'accept');
        Http::assertSentCount(1);
        $this->assertDatabaseCount('ams_payment_requests', 1);
        Mail::assertNothingSent();
    }

    public static function checkedAliases(): array
    {
        return [['approved'], ['accepted']];
    }

    public function test_a_newly_generated_candidate_number_is_stored_and_sent_to_ams(): void
    {
        $major = \App\Models\Major::query()->create([
            'key' => 'bnm', 'abbreviation' => 'BNM', 'name_en' => 'Nurse Midwifery', 'is_active' => true,
        ]);
        $entry = $this->entry('approved', ['major' => (string) $major->getKey()]);

        app(\App\Services\CandidateIdGenerator::class)->assignToEntry($entry);
        app(AmsPaymentSync::class)->sync($entry->fresh());

        $this->assertDatabaseHas('custom_form_entries', [
            'id' => $entry->getKey(), 'candidate_id' => 'BNM-0001', 'candidate_id_year' => now()->year,
        ]);
        Http::assertSent(fn (Request $request): bool =>
            $request['candidate_identifier'] === 'BNM-0001'
            && (string) $request['application_id'] === (string) $entry->getKey());
        Http::assertSentCount(1);
        $this->assertDatabaseCount('payments', 0);
    }

    #[DataProvider('checkedAliases')]
    public function test_approved_aliases_preserve_the_real_candidate_identifier_and_application_reference(string $status): void
    {
        $entry = $this->entry($status);
        $entry->forceFill(['candidate_id' => 'BNM260001'])->save();
        app(AmsPaymentSync::class)->sync($entry);

        Http::assertSent(fn (Request $request): bool =>
            $request['candidate_identifier'] === 'BNM260001'
            && (string) $request['application_id'] === (string) $entry->getKey());
        Http::assertSentCount(1);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame($status, $entry->fresh()->review_status);
    }

    public static function notChecked(): array
    {
        return [['pending', 'accept'], ['rejected', 'send_back'], ['failed', 'reject']];
    }

    #[DataProvider('notChecked')]
    public function test_pending_send_back_and_rejected_entries_never_push_a_fee(string $status, string $action): void
    {
        $entry = $this->entry($status);
        $this->assertNull(app(AmsPaymentSync::class)->sync($entry));
        WorkflowStageMessages::notifyReviewAction($entry, $action);

        Http::assertNothingSent();
        $this->assertDatabaseCount('ams_payment_requests', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame($status, $entry->fresh()->review_status);
    }

    public static function localWorkflows(): array
    {
        return [['system'], ['legacy'], ['unassigned'], ['no_payment']];
    }

    #[DataProvider('localWorkflows')]
    public function test_this_system_legacy_unassigned_and_no_payment_forms_keep_their_existing_workflow(string $mode): void
    {
        $stages = $this->workflow->stages;
        if ($mode === 'unassigned') {
            $this->workflow->forms()->detach($this->form);
        } elseif ($mode === 'no_payment') {
            unset($stages[1]);
            $this->workflow->update(['stages' => array_values($stages)]);
        } else {
            if ($mode === 'legacy') {
                unset($stages[1]['data']['payment_source']);
            } else {
                $stages[1]['data']['payment_source'] = 'system';
            }
            $this->workflow->update(['stages' => $stages]);
        }
        $entry = $this->entry('approved');
        $this->assertNull(app(AmsPaymentSync::class)->sync($entry));
        WorkflowStageMessages::notifyReviewAction($entry, 'accept');

        Http::assertNothingSent();
        $this->assertDatabaseCount('ams_payment_requests', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('approved', $entry->fresh()->review_status);
    }

    public function test_remote_failure_does_not_undo_checked_and_retry_reuses_the_same_outbox_record(): void
    {
        $this->remoteFails = true;
        $entry = $this->entry('pending');
        $this->actingAs($this->staff);
        $this->post(route('admin.custom-form-entries.approve', $entry))->assertRedirect();
        $request = AmsPaymentRequest::query()->sole();
        $requestId = $request->getKey();
        $this->assertSame('failed', $request->sync_status);
        $this->assertSame(1, $request->attempts);
        $this->assertNotEmpty($request->last_error);
        $entry->refresh();
        $this->assertSame('approved', $entry->review_status);
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageMessages::stageTypeFor($entry));

        $this->remoteFails = false;
        app(AmsPaymentSync::class)->sync($entry->fresh());
        app(AmsPaymentSync::class)->sync($entry->fresh());

        $this->assertDatabaseCount('ams_payment_requests', 1);
        $this->assertSame($requestId, AmsPaymentRequest::query()->sole()->getKey());
        $this->assertSame('synced', $request->fresh()->sync_status);
        $this->assertSame(2, $request->fresh()->attempts);
        Http::assertSentCount(2);
        $requests = Http::recorded()->map(fn ($pair) => $pair[0]->data())->all();
        $this->assertSame($requests[0], $requests[1]);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_normal_local_checked_action_keeps_its_notification_and_does_not_contact_ams(): void
    {
        $stages = $this->workflow->stages;
        $stages[0]['data']['review_actions']['accept']['notification_message'] = 'Your application has been checked.';
        $stages[1]['data']['payment_source'] = 'system';
        $this->workflow->update(['stages' => $stages]);
        $entry = $this->entry('pending');
        $this->actingAs($this->staff);
        $this->post(route('admin.custom-form-entries.approve', $entry))->assertRedirect();

        $this->assertSame('approved', $entry->fresh()->review_status);
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageMessages::stageTypeFor($entry->fresh()));
        $this->assertSame('Checked', WorkflowStageMessages::statusText($entry->fresh()));
        $this->assertSame('Your application has been checked.', $this->candidate->notifications()->sole()->data['body']);
        $this->assertDatabaseCount('ams_payment_requests', 0);
        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_disabled_integration_never_sends_or_marks_a_payment(): void
    {
        config()->set('services.ams.enabled', false);
        $entry = $this->entry('approved');
        $request = app(AmsPaymentSync::class)->sync($entry);
        $this->assertSame('failed', $request?->sync_status);
        Http::assertNothingSent();
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageMessages::stageTypeFor($entry));
    }

    public function test_retrying_an_already_collected_local_application_does_not_create_a_second_collection_request(): void
    {
        $entry = $this->entry('approved');
        \App\Models\Payment::query()->create([
            'users_id' => $this->candidate->getKey(), 'form_id' => $this->form->getKey(),
            'custom_form_entry_id' => $entry->getKey(), 'receipt_number' => 'ALREADY-COLLECTED',
            'status_payt' => 'paid', 'amount_usd' => 30, 'datetime_pay' => now(),
        ]);
        app(AmsPaymentSync::class)->sync($entry);

        Http::assertNothingSent();
        $this->assertDatabaseCount('ams_payment_requests', 0);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageMessages::stageTypeFor($entry->fresh()));
    }

    public function test_ams_payment_action_is_hidden_and_local_server_recording_is_blocked(): void
    {
        app()->setLocale('km');
        $this->authorizePayments();
        $entry = $this->entry('approved');
        $record = PaymentStatusList::query()->findOrFail($entry->getKey());
        Livewire::test(ListPaymentStatusLists::class)->assertTableActionHidden('pay', $record);

        try {
            app(CandidatePaymentRecorder::class)->record([
                'custom_form_entry_id' => $entry->getKey(), 'users_id' => $this->candidate->getKey(),
                'form_id' => $this->form->getKey(), 'receipt_number' => 'FORGED-LOCAL-PAYMENT',
                'amount_usd' => 30, 'datetime_pay' => now(),
            ]);
            $this->fail('AMS applications must be paid through the verified callback.');
        } catch (ValidationException $exception) {
            $this->assertSame([__('workflow_notifications.validation.ams_payment_only')], $exception->errors()['receipt_number']);
        }

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('ams_payment_requests', 0);
        $this->assertSame('approved', $entry->fresh()->review_status);
        Http::assertNothingSent();
    }

    public function test_this_system_payment_action_remains_visible_and_records_the_payment_normally(): void
    {
        $this->authorizePayments();
        $stages = $this->workflow->stages;
        $stages[1]['data']['payment_source'] = 'system';
        $this->workflow->update(['stages' => $stages]);
        $entry = $this->entry('approved');
        $record = PaymentStatusList::query()->findOrFail($entry->getKey());
        MefInvoiceCode::query()->create(['mef_code' => 'MEF1', 'invoice_code' => '120--99001']);
        PaymentType::query()->create([
            'key' => 'cash', 'name_en' => 'Cash', 'name_kh' => 'សាច់ប្រាក់', 'method' => 'cash', 'is_active' => true,
        ]);

        Livewire::test(ListPaymentStatusLists::class)->assertTableActionVisible('pay', $record)
            ->callTableAction('pay', $record, data: [
                'receipt_number' => 'MEF1', 'payment_method' => 'cash', 'amount_usd' => '30',
                'amount_kh' => null, 'datetime_pay' => now()->toDateString(),
            ])->assertHasNoTableActionErrors();

        $payment = Payment::query()->sole();
        $this->assertSame((int) $entry->getKey(), (int) $payment->custom_form_entry_id);
        $this->assertSame('120--99001', $payment->receipt_number);
        $this->assertSame('paid', $payment->status_payt);
        $this->assertSame((int) $this->staff->getKey(), (int) $payment->received_by);
        $this->assertNull($payment->integration_source);
        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageMessages::stageTypeFor($entry->fresh()));
        $this->assertDatabaseCount('ams_payment_requests', 0);
        Http::assertNothingSent();
    }

    private function authorizePayments(): void
    {
        foreach (['ViewAny', 'Pay'] as $action) {
            $this->staff->givePermissionTo(Permission::findOrCreate($action.':PaymentStatusList', 'web'));
        }
        $this->actingAs($this->staff);
    }

    private function entry(string $status, array $data = []): CustomFormEntry
    {
        return CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $this->form->getKey(), 'created_by' => $this->candidate->getKey(),
            'review_status' => $status, 'data' => array_merge(['registration_status' => $status], $data),
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
