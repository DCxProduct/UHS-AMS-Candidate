<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType;
use App\Models\AmsPaymentRequest;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Support\WorkflowStageMessages;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AmsOtherFeePaymentCallbackTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;
    private CustomForm $form;
    private WorkflowNotification $workflow;
    private CustomFormEntry $entry;
    private AmsPaymentRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.ams', [
            'enabled' => true, 'base_url' => 'https://ams.example.test', 'token' => 'outbound-secret',
            'callback_token' => 'callback-secret', 'timeout' => 5,
        ]);
        Http::fake();
        Mail::fake();
        $this->candidate = User::query()->forceCreate([
            'registration_type' => 'student', 'name' => 'AMS callback candidate', 'username' => 'ams_callback',
            'email' => 'ams_callback@example.test', 'date_of_birth' => '2000-01-01',
            'password' => 'password', 'is_active' => true,
        ]);
        $this->form = CustomForm::query()->create([
            'name' => 'AMS callback form', 'slug' => 'ams-callback-form', 'is_active' => true,
        ]);
        $this->workflow = WorkflowNotification::query()->create([
            'name' => 'AMS callback workflow',
            'stages' => [
                ['type' => 'stage', 'data' => [
                    'stage_name' => 'Review', 'stage_type' => 'review',
                    'review_actions' => ['accept' => ['status_message' => 'Checked']],
                ]],
                ['type' => 'stage', 'data' => [
                    'stage_name' => 'Payment', 'stage_type' => 'payment', 'payment_source' => 'ams',
                    'status_message' => 'Paid successfully',
                ]],
                ['type' => 'stage', 'data' => [
                    'stage_name' => 'Results', 'stage_type' => 'awaiting_results', 'status_message' => 'Passed',
                ]],
            ],
        ]);
        $this->workflow->forms()->attach($this->form);
        $this->entry = $this->entry();
        $this->request = $this->requestFor($this->entry, 'ams-request-callback');
    }

    public function test_verified_paid_callback_records_only_the_correlated_application_and_preserves_review_state(): void
    {
        $other = $this->entry();
        $other->forceFill(['candidate_id' => 'SHARED-CANDIDATE-ID'])->save();
        $this->entry->forceFill(['candidate_id' => 'SHARED-CANDIDATE-ID'])->save();
        $this->requestFor($other, 'different-ams-request');
        $body = $this->payload(['amount_usd' => 0, 'amount_khr' => 120000]);

        $this->postCallback($body)->assertOk();

        $payment = Payment::query()->sole();
        $this->assertSame((int) $this->entry->getKey(), (int) $payment->custom_form_entry_id);
        $this->assertSame((int) $this->candidate->getKey(), (int) $payment->users_id);
        $this->assertSame((int) $this->form->getKey(), (int) $payment->form_id);
        $this->assertSame('paid', $payment->status_payt);
        $this->assertSame('ams', $payment->integration_source);
        $this->assertSame('external-payment-1', $payment->integration_payment_id);
        $this->assertSame('AMS Cashier', $payment->integration_cashier_name);
        $this->assertNull($payment->received_by);
        $this->assertNull($payment->amount_usd);
        $this->assertSame('120000.00', $payment->amount_kh);
        $this->assertSame('4100.00', $payment->exchange_rate);
        $this->assertSame('AMS-RECEIPT-1', $payment->receipt_number);
        $this->assertSame('2026-10-29 10:15:00', $payment->datetime_pay->format('Y-m-d H:i:s'));
        $this->assertSame((int) $payment->getKey(), (int) $this->request->fresh()->local_payment_id);
        $this->assertSame('external-payment-1', $this->request->fresh()->external_payment_id);
        $this->assertSame(1, $this->request->fresh()->last_event_version);
        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageMessages::stageTypeFor($this->entry->fresh()));
        $this->assertSame('Paid successfully', WorkflowStageMessages::statusText($this->entry->fresh()));
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageMessages::stageTypeFor($other->fresh()));
        $this->assertDatabaseMissing('payments', ['custom_form_entry_id' => $other->getKey()]);
        $this->assertSame('approved', $this->entry->fresh()->review_status);
        $this->assertSame('approved', data_get($this->entry->fresh()->data, 'registration_status'));
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_duplicate_and_stale_events_are_idempotent_and_cannot_restore_an_old_payment_state(): void
    {
        $paid = $this->payload();
        $this->postCallback($paid)->assertOk();
        $paymentId = Payment::query()->sole()->getKey();
        $notifications = $this->candidate->notifications()->count();
        $this->postCallback($paid)->assertOk();
        $this->postCallback($this->payload(['event_version' => 2, 'status' => 'unpaid']))->assertOk();
        $this->postCallback($paid)->assertOk();

        $payment = Payment::query()->sole();
        $this->assertSame($paymentId, $payment->getKey());
        $this->assertSame('return', $payment->status_payt);
        $this->assertSame(2, $this->request->fresh()->last_event_version);
        $this->assertSame($notifications, $this->candidate->notifications()->count());
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageMessages::stageTypeFor($this->entry->fresh()));

        $this->postCallback($this->payload(['event_version' => 3]))->assertOk();
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame($paymentId, Payment::query()->sole()->getKey());
        $this->assertSame('paid', Payment::query()->sole()->status_payt);
        $this->assertSame(3, $this->request->fresh()->last_event_version);
    }

    public function test_an_unpaid_event_before_payment_does_not_create_a_paid_or_manual_record(): void
    {
        $body = $this->payload(['status' => 'unpaid']);
        unset($body['receipt_number'], $body['paid_at'], $body['amount_usd'], $body['amount_khr']);
        $this->postCallback($body)->assertOk();

        $this->assertDatabaseCount('payments', 0);
        $this->assertNull($this->request->fresh()->local_payment_id);
        $this->assertSame(1, $this->request->fresh()->last_event_version);
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageMessages::stageTypeFor($this->entry->fresh()));
        $this->assertDatabaseCount('notifications', 0);
    }

    public static function invalidTokens(): array
    {
        return [[null], ['wrong-secret'], ['outbound-secret']];
    }

    #[DataProvider('invalidTokens')]
    public function test_callback_requires_its_separate_bearer_secret(?string $token): void
    {
        $this->postCallback($this->payload(), $token)->assertUnauthorized();
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(0, $this->request->fresh()->last_event_version);
    }

    public function test_disabled_or_unconfigured_callback_fails_closed(): void
    {
        config()->set('services.ams.callback_token', null);
        $this->postCallback($this->payload())->assertStatus(503);
        config()->set('services.ams.callback_token', 'callback-secret');
        config()->set('services.ams.enabled', false);
        $this->postCallback($this->payload())->assertStatus(503);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_a_local_admin_session_cannot_replace_callback_authentication(): void
    {
        $admin = User::query()->forceCreate([
            'registration_type' => 'admin', 'name' => 'Local admin', 'username' => 'callback_admin',
            'email' => 'callback_admin@example.test', 'date_of_birth' => '2000-01-01', 'password' => 'password', 'is_active' => true,
        ]);
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
        $this->postCallback($this->payload(), null)->assertUnauthorized();
        $this->assertDatabaseCount('payments', 0);
    }

    public static function badAmounts(): array
    {
        return [
            [['amount_usd' => 0, 'amount_khr' => 0]],
            [['amount_usd' => -1, 'amount_khr' => null]],
            [['amount_usd' => null, 'amount_khr' => -100]],
            [['amount_usd' => 'not money', 'amount_khr' => null]],
            [['amount_usd' => null, 'amount_khr' => null]],
            [['amount_usd' => '10000000000.00', 'amount_khr' => null]],
            [['amount_usd' => null, 'amount_khr' => '1000000000000.00']],
        ];
    }

    #[DataProvider('badAmounts')]
    public function test_invalid_paid_amounts_never_create_or_advance_a_payment(array $amounts): void
    {
        $this->postCallback($this->payload($amounts))->assertUnprocessable();
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(0, $this->request->fresh()->last_event_version);
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageMessages::stageTypeFor($this->entry->fresh()));
    }

    public static function invalidPaidFields(): array
    {
        return [['receipt_number'], ['paid_at'], ['event_version'], ['payment_id'], ['ams_request_id'], ['application_id']];
    }

    #[DataProvider('invalidPaidFields')]
    public function test_required_payment_and_correlation_fields_are_validated(string $field): void
    {
        $body = $this->payload();
        unset($body[$field]);
        $this->postCallback($body)->assertUnprocessable();
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(0, $this->request->fresh()->last_event_version);
    }

    public function test_a_different_application_or_ams_request_cannot_take_over_an_outbox_record(): void
    {
        $other = $this->entry();
        $this->postCallback($this->payload(['application_id' => (string) $other->getKey()]))->assertStatus(409);
        $this->postCallback($this->payload(['ams_request_id' => 'another-ams-request']))->assertStatus(409);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(0, $this->request->fresh()->last_event_version);
        $this->assertNull($this->request->fresh()->local_payment_id);
    }

    public function test_template_source_change_preserves_existing_ams_applications_and_rejects_new_local_applications(): void
    {
        $stages = $this->workflow->stages;
        $stages[1]['data']['payment_source'] = 'system';
        $this->workflow->update(['stages' => $stages]);
        $this->postCallback($this->payload())->assertOk();
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(1, $this->request->fresh()->last_event_version);

        $new = $this->entry();
        $newRequest = $this->requestFor($new, 'new-local-application');
        $this->postCallback($this->payload([
            'application_id' => (string) $new->getKey(),
            'ams_request_id' => (string) $newRequest->ams_request_id,
            'payment_id' => 'another-external-payment',
        ]))->assertStatus(409);
        $this->assertDatabaseMissing('payments', ['custom_form_entry_id' => $new->getKey()]);
        $this->assertSame(0, $newRequest->fresh()->last_event_version);
    }

    public static function ineligibleStates(): array
    {
        return [['pending'], ['rejected'], ['failed']];
    }

    #[DataProvider('ineligibleStates')]
    public function test_non_checked_applications_cannot_be_marked_paid_even_with_a_bound_request(string $status): void
    {
        $this->entry->forceFill(['review_status' => $status, 'data' => ['registration_status' => $status]])->save();
        $this->postCallback($this->payload())->assertStatus(409);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame(0, $this->request->fresh()->last_event_version);
        $this->assertSame($status, $this->entry->fresh()->review_status);
    }

    public function test_return_callback_cannot_modify_a_payment_recorded_manually(): void
    {
        $manual = Payment::query()->create([
            'custom_form_entry_id' => $this->entry->getKey(), 'users_id' => $this->candidate->getKey(),
            'form_id' => $this->form->getKey(), 'receipt_number' => 'MANUAL-RECEIPT',
            'status_payt' => 'paid', 'amount_usd' => 15, 'datetime_pay' => now(),
        ]);
        $before = $manual->fresh()->getRawOriginal();
        $this->postCallback($this->payload())->assertStatus(409);
        $this->postCallback($this->payload(['status' => 'unpaid']))->assertStatus(409);

        $this->assertSame($before, $manual->fresh()->getRawOriginal());
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(0, $this->request->fresh()->last_event_version);
        $this->assertNull($this->request->fresh()->local_payment_id);
    }

    public function test_a_bound_payment_identity_cannot_be_replaced_by_a_new_external_payment_id(): void
    {
        $this->postCallback($this->payload())->assertOk();
        $this->postCallback($this->payload(['event_version' => 2, 'payment_id' => 'different-payment']))->assertStatus(409);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('external-payment-1', Payment::query()->sole()->integration_payment_id);
        $this->assertSame(1, $this->request->fresh()->last_event_version);
    }

    public function test_numeric_ams_ids_are_normalized_without_losing_exact_application_binding(): void
    {
        $this->request->update(['ams_request_id' => '42']);
        $this->postCallback($this->payload([
            'application_id' => $this->entry->getKey(), 'ams_request_id' => 42, 'payment_id' => 99,
        ]))->assertOk();

        $payment = Payment::query()->sole();
        $this->assertSame('99', $payment->integration_payment_id);
        $this->assertSame((int) $this->entry->getKey(), (int) $payment->custom_form_entry_id);
        $this->assertSame('99', $this->request->fresh()->external_payment_id);
    }

    public function test_repayment_after_a_return_can_bind_a_new_receipt_but_an_old_receipt_cannot_reverse_it(): void
    {
        $this->postCallback($this->payload())->assertOk();
        $paymentId = Payment::query()->sole()->getKey();
        $this->postCallback($this->payload(['event_version' => 2, 'status' => 'unpaid']))->assertOk();
        $this->postCallback($this->payload([
            'event_version' => 3, 'payment_id' => 'replacement-payment', 'receipt_number' => 'AMS-RECEIPT-2',
        ]))->assertOk();
        $this->postCallback($this->payload(['event_version' => 4, 'status' => 'unpaid']))->assertStatus(409);

        $payment = Payment::query()->sole();
        $this->assertSame($paymentId, $payment->getKey());
        $this->assertSame('paid', $payment->status_payt);
        $this->assertSame('replacement-payment', $payment->integration_payment_id);
        $this->assertSame('AMS-RECEIPT-2', $payment->receipt_number);
        $this->assertSame(3, $this->request->fresh()->last_event_version);
    }

    private function postCallback(array $payload, ?string $token = 'callback-secret')
    {
        $headers = $token === null ? [] : ['Authorization' => 'Bearer '.$token];

        return $this->postJson('/api/external/ams/other-fee-payments', $payload, $headers);
    }

    private function payload(array $changes = []): array
    {
        return array_replace([
            'application_id' => (string) $this->entry->getKey(), 'ams_request_id' => 'ams-request-callback',
            'payment_id' => 'external-payment-1', 'event_version' => 1, 'status' => 'paid',
            'receipt_number' => 'AMS-RECEIPT-1', 'paid_at' => '2026-10-29 10:15:00',
            'amount_usd' => 30, 'amount_khr' => null, 'exchange_rate' => 4100, 'cashier_name' => 'AMS Cashier',
        ], $changes);
    }

    private function entry(): CustomFormEntry
    {
        return CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $this->form->getKey(), 'created_by' => $this->candidate->getKey(),
            'review_status' => 'approved', 'data' => ['registration_status' => 'approved', 'candidate_status' => 'pending'],
        ]);
    }

    private function requestFor(CustomFormEntry $entry, string $amsId): AmsPaymentRequest
    {
        return AmsPaymentRequest::query()->create([
            'custom_form_entry_id' => $entry->getKey(), 'ams_request_id' => $amsId, 'sync_status' => 'synced',
            'payload' => ['application_id' => (string) $entry->getKey()], 'attempts' => 1, 'synced_at' => now(),
        ]);
    }
}
