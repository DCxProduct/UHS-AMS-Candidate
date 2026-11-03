<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType;
use App\Models\AmsPaymentRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Services\AmsPaymentSync;
use App\Support\WorkflowStageMessages;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AmsPaymentWorkflowContractTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;
    private User $staff;
    private CustomForm $form;
    private WorkflowNotification $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        // These fixtures exercise the existing status-driven callback contract.
        CustomFormEntry::creating(static function (CustomFormEntry $entry): void {
            if (is_array($entry->workflow_snapshot)) {
                $entry->workflow_snapshot = [...$entry->workflow_snapshot, 'schema_version' => 1];
            }
        });
        app()->setLocale('en');
        config()->set('services.ams', [
            'enabled' => true, 'base_url' => 'https://ams.example.test',
            'token' => 'outbound-secret', 'callback_token' => 'callback-secret', 'timeout' => 5,
        ]);
        Http::preventStrayRequests();
        Http::fake(['https://ams.example.test/api/external/candidate/other-fees' => fn (Request $request) => Http::response([
            'application_id' => $request['application_id'],
            'ams_request_id' => 'ams-'.$request['application_id'],
            'payment_status' => 'unpaid',
        ], 201)]);
        Mail::fake();
        $this->candidate = $this->user('contract_candidate', 'student');
        $this->staff = $this->user('contract_staff', 'admin');
        $this->staff->assignRole(Role::findOrCreate('admin', 'web'));
        $this->form = CustomForm::query()->create([
            'name' => 'AMS contract form', 'slug' => 'ams-contract-form', 'is_active' => true,
        ]);
        $this->workflow = WorkflowNotification::query()->create([
            'name' => 'AMS contract workflow',
            'stages' => [
                ['type' => 'stage', 'data' => ['stage_name' => 'Review', 'stage_type' => 'review']],
                ['type' => 'stage', 'data' => [
                    'stage_name' => 'Collect in AMS', 'stage_type' => 'payment', 'payment_source' => 'ams',
                    'responsible_role' => ['ams_cashier'], 'alert_message' => 'Paid in AMS',
                    'status_message' => 'Payment received', 'notification_message' => 'Your AMS payment is complete.',
                    'notification_channels' => ['system'],
                    'audience_messages' => [
                        'staff_notification_message' => ':candidate paid for :form.',
                        // Deliberately shares a role name with Candidate to detect cross-system delivery.
                        'staff_recipient_roles' => ['admin'],
                    ],
                ]],
                ['type' => 'stage', 'data' => ['stage_name' => 'Results', 'stage_type' => 'awaiting_results']],
            ],
        ]);
        $this->workflow->forms()->attach($this->form);
    }

    public function test_ams_receives_the_frozen_payment_action_configuration_without_storing_it_in_the_candidate_outbox(): void
    {
        $started = $this->entry();
        $stages = $this->workflow->stages;
        $stages[1]['data']['alert_message'] = 'New payment alert';
        $stages[1]['data']['responsible_role'] = ['new_ams_cashier'];
        $stages[1]['data']['audience_messages']['staff_recipient_roles'] = ['new_ams_manager'];
        $this->workflow->update(['stages' => $stages]);
        $new = $this->entry();

        app(AmsPaymentSync::class)->sync($started);
        app(AmsPaymentSync::class)->sync($new);

        Http::assertSent(function (Request $request) use ($started): bool {
            return (string) $request['application_id'] === (string) $started->getKey()
                && $request->hasHeader('Authorization', 'Bearer outbound-secret')
                && data_get($request->data(), 'workflow_payment.stage_name') === 'Collect in AMS'
                && data_get($request->data(), 'workflow_payment.alert_message') === 'Paid in AMS'
                && data_get($request->data(), 'workflow_payment.responsible_roles') === ['ams_cashier']
                && data_get($request->data(), 'workflow_payment.staff_notification_message') === ':candidate paid for :form.'
                && data_get($request->data(), 'workflow_payment.staff_recipient_roles') === ['admin'];
        });
        Http::assertSent(fn (Request $request): bool => (string) $request['application_id'] === (string) $new->getKey()
            && data_get($request->data(), 'workflow_payment.alert_message') === 'New payment alert'
            && data_get($request->data(), 'workflow_payment.responsible_roles') === ['new_ams_cashier']
            && data_get($request->data(), 'workflow_payment.staff_recipient_roles') === ['new_ams_manager']);
        Http::assertSentCount(2);
        foreach ([$started, $new] as $entry) {
            $link = AmsPaymentRequest::query()->where('custom_form_entry_id', $entry->getKey())->sole();
            $this->assertSame(['application_id' => (string) $entry->getKey()], $link->payload);
            $this->assertSame('approved', $entry->fresh()->review_status);
        }
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_paid_ams_stage_notifies_the_candidate_without_broadcasting_to_candidate_staff_with_the_same_role_name(): void
    {
        $entry = $this->entry();
        AmsPaymentRequest::query()->forceCreate([
            'custom_form_entry_id' => $entry->getKey(), 'ams_request_id' => 'external-'.$entry->getKey(),
            'sync_status' => 'synced', 'payment_status' => 'paid', 'external_payment_id' => 'ams-payment-1',
        ]);

        $this->assertTrue(WorkflowStageMessages::notifyConfiguredStage($entry, WorkflowStageType::Payment));

        $this->assertSame('Your AMS payment is complete.', $this->candidate->notifications()->sole()->data['body']);
        $this->assertSame(0, $this->staff->notifications()->count());
        $this->assertSame('Payment received', WorkflowStageMessages::statusText($entry->fresh()));
        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_payment_in_candidate_keeps_local_staff_notification_delivery_and_never_sends_configuration_to_ams(): void
    {
        $stages = $this->workflow->stages;
        $stages[1]['data']['payment_source'] = 'system';
        $stages[1]['data']['responsible_role'] = ['admin'];
        $this->workflow->update(['stages' => $stages]);
        $entry = $this->entry();

        $this->assertNull(app(AmsPaymentSync::class)->sync($entry));
        $this->assertTrue(WorkflowStageMessages::notifyConfiguredStage($entry, WorkflowStageType::Payment));

        $this->assertSame(1, $this->staff->notifications()->count());
        $this->assertSame(1, $this->candidate->notifications()->count());
        $this->assertDatabaseCount('ams_payment_requests', 0);
        Http::assertNothingSent();
    }

    private function entry(): CustomFormEntry
    {
        return CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $this->form->getKey(), 'created_by' => $this->candidate->getKey(),
            'review_status' => 'approved', 'data' => ['registration_status' => 'approved'],
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
