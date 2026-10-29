<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Support\WorkflowStageMessages;
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
use Tests\TestCase;

class WorkflowReviewSharedStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;
    private User $staff;
    private CustomForm $form;
    private WorkflowNotification $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        $this->candidate = $this->user('review_candidate', 'student');
        $this->staff = $this->user('review_staff', 'admin');
        $this->staff->assignRole(Role::findOrCreate('admin', 'web'));
        $this->form = CustomForm::query()->create([
            'name' => 'Review form', 'slug' => 'review-form', 'is_active' => true,
            'allowed_roles' => ['candidate', 'admin'],
        ]);
        $this->workflow = WorkflowNotification::query()->create([
            'name' => 'Shared review statuses',
            'stages' => [
                ['type' => 'stage', 'data' => [
                    'stage_name' => 'Review', 'stage_type' => 'review',
                    'responsible_role' => ['admin'], 'status_message' => 'Under review',
                    'review_actions' => [
                        'accept' => ['status_message' => 'Checked'],
                        'send_back' => ['status_message' => 'Please update your application'],
                        'reject' => ['status_message' => 'Not approved'],
                    ],
                ]],
                ['type' => 'stage', 'data' => [
                    'stage_name' => 'Payment', 'stage_type' => 'payment',
                    'status_message' => 'Waiting for payment',
                ]],
            ],
        ]);
        $this->workflow->forms()->attach($this->form);
    }

    public static function reviewStatuses(): array
    {
        return [
            ['rejected', 'Please update your application', WorkflowStageType::Rejected],
            ['failed', 'Not approved', WorkflowStageType::Rejected],
        ];
    }

    #[DataProvider('reviewStatuses')]
    public function test_explicit_review_status_is_shared_without_changing_the_stored_state(string $status, string $expected, WorkflowStageType $stage): void
    {
        $entry = $this->entry($status);
        $column = $this->column($entry);

        $this->assertTrue(WorkflowStageMessages::requiresPayment($entry));
        $this->assertSame($expected, $column->formatState($column->getState()));
        $this->actingAs($this->candidate);
        $this->assertSame($expected, $column->formatState($column->getState()));
        $this->assertSame($status, $entry->fresh()->review_status);
        $this->assertSame($status, data_get($entry->fresh()->data, 'registration_status'));
        $this->assertSame($stage, WorkflowStageMessages::stageTypeFor($entry));
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_legacy_generic_review_status_keeps_the_original_staff_label(): void
    {
        $stages = $this->workflow->stages;
        unset($stages[0]['data']['review_actions']);
        $this->workflow->update(['stages' => $stages]);
        $entry = $this->entry('pending');
        $column = $this->column($entry);

        $this->assertNull(WorkflowStageMessages::sharedReviewStatusText($entry));
        $this->assertSame(__('candidate_entrance_statistics.statuses.pending'), $column->formatState($column->getState()));
        $this->actingAs($this->candidate);
        $this->assertSame('Under review', $column->formatState($column->getState()));
    }

    public function test_pending_entry_does_not_show_the_checked_action_status_before_review(): void
    {
        $entry = $this->entry('pending');
        $column = $this->column($entry);

        $this->assertNull(WorkflowStageMessages::sharedReviewStatusText($entry));
        $this->assertSame('Under review', WorkflowStageMessages::statusText($entry));
        $this->assertSame(__('candidate_entrance_statistics.statuses.pending'), $column->formatState($column->getState()));
        $this->actingAs($this->candidate);
        $this->assertSame('Under review', $column->formatState($column->getState()));
        $this->assertSame('pending', $entry->fresh()->review_status);
        $this->assertSame(WorkflowStageType::Review, WorkflowStageMessages::stageTypeFor($entry));
        $this->assertDatabaseCount('notifications', 0);
    }

    public static function approvedStates(): array
    {
        return [
            ['approved', true],
            ['accepted', true],
            ['approved', false],
            ['accepted', false],
        ];
    }

    #[DataProvider('approvedStates')]
    public function test_reviewed_unpaid_entries_show_the_checked_text_without_changing_payment_or_no_payment_routing(string $status, bool $requiresPayment): void
    {
        if (! $requiresPayment) {
            $stages = [$this->workflow->stages[0], $this->resultsStage()];
            $this->workflow->update(['stages' => $stages]);
        }
        $entry = $this->entry($status);
        $column = $this->column($entry);
        $expected = $requiresPayment ? 'Checked' : 'Waiting for results';

        $this->assertSame($requiresPayment, WorkflowStageMessages::requiresPayment($entry));
        $this->assertSame($requiresPayment ? 'Checked' : null, WorkflowStageMessages::sharedReviewStatusText($entry));
        $this->assertSame($requiresPayment ? WorkflowStageType::Payment : WorkflowStageType::AwaitingResults, WorkflowStageMessages::stageTypeFor($entry));
        $this->assertSame($expected, WorkflowStageMessages::statusText($entry));
        $this->assertSame($expected, $column->formatState($column->getState()));
        $this->actingAs($this->candidate);
        $this->assertSame($expected, $column->formatState($column->getState()));
        $this->assertSame($status, $entry->fresh()->review_status);
        $this->assertSame($status, data_get($entry->fresh()->data, 'registration_status'));
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_reviewed_entry_without_an_explicit_accept_status_keeps_the_payment_status_fallback(): void
    {
        $stages = $this->workflow->stages;
        unset($stages[0]['data']['review_actions']['accept']['status_message']);
        $this->workflow->update(['stages' => $stages]);
        $entry = $this->entry('approved');
        $column = $this->column($entry);

        $this->assertNull(WorkflowStageMessages::sharedReviewStatusText($entry));
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageMessages::stageTypeFor($entry));
        $this->assertSame('Waiting for payment', WorkflowStageMessages::statusText($entry));
        $this->assertSame('Waiting for payment', $column->formatState($column->getState()));
        $this->actingAs($this->candidate);
        $this->assertSame('Waiting for payment', $column->formatState($column->getState()));
    }

    public static function configuredUnpaidLabels(): array
    {
        return [
            ['approved', 'en', 'Unpaid'],
            ['accepted', 'km', 'មិនទាន់បានបង់លុយ'],
        ];
    }

    #[DataProvider('configuredUnpaidLabels')]
    public function test_approved_and_accepted_entries_keep_the_review_action_label_when_unpaid_text_is_configured(string $status, string $locale, string $label): void
    {
        app()->setLocale($locale);
        $stages = $this->workflow->stages;
        $paidLabel = $locale === 'km' ? 'បានបង់ប្រាក់រួចរាល់' : 'Paid successfully';
        $reviewLabel = $locale === 'km' ? 'បានពិនិត្យពាក្យ' : 'Checked application';
        $stages[0]['data']['review_actions']['accept']['status_message'] = $reviewLabel;
        $stages[1]['data']['status_message'] = $paidLabel;
        $stages[1]['data']['payment_statuses']['unpaid_status_message'] = $label;
        $this->workflow->update(['stages' => $stages]);
        $entry = $this->entry($status);
        $otherApplication = $this->entry($status);
        Payment::query()->create([
            'users_id' => $this->candidate->getKey(), 'form_id' => $this->form->getKey(),
            'custom_form_entry_id' => $otherApplication->getKey(), 'receipt_number' => 'OTHER-APPLICATION-'.$otherApplication->getKey(),
            'status_payt' => 'paid', 'amount_usd' => 5, 'datetime_pay' => now(),
        ]);

        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageMessages::stageTypeFor($otherApplication));
        $this->assertSame($paidLabel, WorkflowStageMessages::statusText($otherApplication));
        $paidColumn = $this->column($otherApplication);
        $this->assertSame($paidLabel, $paidColumn->formatState($paidColumn->getState()));
        $this->actingAs($this->candidate);
        $this->assertSame($paidLabel, $paidColumn->formatState($paidColumn->getState()));
        $this->assertSame($status, $otherApplication->fresh()->review_status);
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageMessages::stageTypeFor($entry));
        $this->assertSame($label, WorkflowStageMessages::paymentStatusLabel($entry, 'unpaid'));
        $this->assertSame($reviewLabel, WorkflowStageMessages::statusText($entry));
        $column = $this->column($entry);
        $this->assertSame($reviewLabel, $column->formatState($column->getState()));
        $this->actingAs($this->candidate);
        $this->assertSame($reviewLabel, $column->formatState($column->getState()));
        $this->assertSame($status, $entry->fresh()->review_status);
        $this->assertSame($status, data_get($entry->fresh()->data, 'registration_status'));
        $this->assertDatabaseMissing('payments', ['custom_form_entry_id' => $entry->getKey()]);
        $this->assertDatabaseCount('notifications', 0);
    }

    public static function blankUnpaidLabels(): array
    {
        return [['approved', null], ['accepted', ' ']];
    }

    #[DataProvider('blankUnpaidLabels')]
    public function test_blank_unpaid_configuration_keeps_the_existing_checked_fallback(string $status, ?string $label): void
    {
        $stages = $this->workflow->stages;
        $stages[1]['data']['payment_statuses']['unpaid_status_message'] = $label;
        $this->workflow->update(['stages' => $stages]);
        $entry = $this->entry($status);
        $column = $this->column($entry);

        $this->assertSame('Checked', WorkflowStageMessages::statusText($entry));
        $this->assertSame('Checked', $column->formatState($column->getState()));
        $this->actingAs($this->candidate);
        $this->assertSame('Checked', $column->formatState($column->getState()));
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageMessages::stageTypeFor($entry));
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_pdf_review_shows_checked_then_actual_payment_shows_the_configured_paid_text(): void
    {
        $checked = 'បានពិនិត្យ';
        $reviewBody = 'ពាក្យស្នើសុំរបស់អ្នកត្រូវបានពិនិត្យរួចរាល់។';
        $paymentStatus = 'បានបង់ប្រាក់រួចរាល់';
        $unpaidStatus = 'មិនទាន់បានបង់លុយ';
        $paymentBody = 'ការបង់ប្រាក់របស់អ្នកត្រូវបានបញ្ចប់។';
        $stages = $this->workflow->stages;
        $stages[0]['data']['review_actions']['accept'] = [
            'status_message' => $checked,
            'notification_message' => $reviewBody,
        ];
        $stages[1]['data']['status_message'] = $paymentStatus;
        $stages[1]['data']['payment_statuses']['unpaid_status_message'] = $unpaidStatus;
        $stages[1]['data']['notification_message'] = $paymentBody;
        $stages[] = $this->resultsStage();
        $this->workflow->update(['stages' => $stages]);
        $entry = $this->entry('pending');
        $this->actingAs($this->staff);

        $this->post(route('admin.custom-form-entries.approve', $entry))->assertRedirect();

        $entry->refresh();
        $this->assertSame('approved', $entry->review_status);
        $this->assertSame('approved', data_get($entry->data, 'registration_status'));
        $this->assertSame($this->staff->getKey(), $entry->reviewed_by);
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageMessages::stageTypeFor($entry));
        $this->assertTrue(WorkflowStageMessages::requiresPayment($entry));
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame($checked, WorkflowStageMessages::statusText($entry));
        $column = $this->column($entry);
        $this->assertSame($checked, $column->formatState($column->getState()));
        $this->actingAs($this->candidate);
        $this->assertSame($checked, $column->formatState($column->getState()));
        $this->assertSame([$reviewBody], $this->candidate->notifications()->get()->pluck('data.body')->all());
        $this->assertNotContains($paymentBody, $this->candidate->notifications()->get()->pluck('data.body')->all());
        Http::assertNothingSent();
        Mail::assertNothingSent();

        Payment::query()->forceCreate([
            'users_id' => $this->candidate->getKey(),
            'form_id' => $this->form->getKey(),
            'custom_form_entry_id' => $entry->getKey(),
            'receipt_number' => 'REVIEW-PAID-'.$entry->getKey(),
            'status_payt' => 'paid',
            'amount_usd' => 10,
            'amount_kh' => null,
            'exchange_rate' => 4100,
            'datetime_pay' => now(),
        ]);

        $entry->refresh();
        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageMessages::stageTypeFor($entry));
        $this->assertNull(WorkflowStageMessages::sharedReviewStatusText($entry));
        $this->assertSame($paymentStatus, WorkflowStageMessages::statusText($entry));
        $column = $this->column($entry);
        $this->assertSame($paymentStatus, $column->formatState($column->getState()));
        $this->actingAs($this->candidate);
        $this->assertSame($paymentStatus, $column->formatState($column->getState()));
        $this->assertSame('approved', $entry->review_status);
        $this->assertDatabaseCount('payments', 1);
    }

    public static function actualReviewActions(): array
    {
        return [
            ['accept', 'approved', WorkflowStageType::Payment, 'បានពិនិត្យពាក្យ'],
            ['send_back', 'rejected', WorkflowStageType::Rejected, 'បញ្ជូនត្រឡប់កែពាក្យ'],
            ['reject', 'failed', WorkflowStageType::Rejected, 'បានបដិសេធ'],
        ];
    }

    #[DataProvider('actualReviewActions')]
    public function test_actual_review_actions_show_their_configured_text_to_candidate_and_staff(string $action, string $storedStatus, WorkflowStageType $stageType, string $label): void
    {
        $stages = $this->workflow->stages;
        $stages[0]['data']['review_actions'][$action] = [
            'status_message' => $label,
            'notification_message' => 'Notification for '.$action,
        ];
        $stages[1]['data']['payment_statuses']['unpaid_status_message'] = 'Distinct unpaid label';
        $this->workflow->update(['stages' => $stages]);
        $entry = $this->entry('pending');
        $this->actingAs($this->staff);
        $note = 'Please review the attached document.';

        if ($action === 'accept') {
            $this->post(route('admin.custom-form-entries.approve', $entry))->assertRedirect();
        } elseif ($action === 'send_back') {
            $this->post(route('admin.custom-form-entries.reject', $entry), ['review_note' => $note])->assertRedirect();
        } else {
            // Exercise the existing non-PDF rejection action for this fixture.
            DocumentTemplate::query()->where('type', 'custom_form_'.$this->form->getKey())->delete();
            Livewire::test(ListCustomFormEntries::class)
                ->callTableAction('reject', $entry, data: ['review_note' => $note])
                ->assertHasNoTableActionErrors();
        }

        $entry->refresh();
        $this->assertSame($storedStatus, $entry->review_status);
        $this->assertSame($storedStatus, data_get($entry->data, 'registration_status'));
        $this->assertSame($stageType, WorkflowStageMessages::stageTypeFor($entry));
        $this->assertSame($label, WorkflowStageMessages::statusText($entry));
        $column = $this->column($entry);
        $this->assertSame($label, $column->formatState($column->getState()));
        $this->actingAs($this->candidate);
        $this->assertSame($label, $column->formatState($column->getState()));
        $notification = $this->candidate->notifications()->sole();
        $this->assertStringContainsString('Notification for '.$action, $notification->data['body']);
        $this->assertSame('review_action:'.$action, data_get($notification->data, 'viewData.workflow_event'));
        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public static function missingAcceptLabels(): array
    {
        return [['approved', null], ['accepted', ' ']];
    }

    #[DataProvider('missingAcceptLabels')]
    public function test_missing_or_blank_review_accept_status_falls_back_to_configured_unpaid_text(string $status, ?string $label): void
    {
        $stages = $this->workflow->stages;
        if ($label === null) {
            unset($stages[0]['data']['review_actions']['accept']['status_message']);
        } else {
            $stages[0]['data']['review_actions']['accept']['status_message'] = $label;
        }
        $stages[1]['data']['payment_statuses']['unpaid_status_message'] = 'មិនទាន់បានបង់លុយ';
        $this->workflow->update(['stages' => $stages]);
        $entry = $this->entry($status);
        $column = $this->column($entry);

        $this->assertSame('មិនទាន់បានបង់លុយ', WorkflowStageMessages::statusText($entry));
        $this->assertSame('មិនទាន់បានបង់លុយ', $column->formatState($column->getState()));
        $this->actingAs($this->candidate);
        $this->assertSame('មិនទាន់បានបង់លុយ', $column->formatState($column->getState()));
        $this->assertSame($status, $entry->fresh()->review_status);
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageMessages::stageTypeFor($entry));
        $this->assertDatabaseCount('payments', 0);
    }

    private function resultsStage(): array
    {
        return ['type' => 'stage', 'data' => [
            'stage_name' => 'Results',
            'stage_type' => 'awaiting_results',
            'status_message' => 'Waiting for results',
        ]];
    }

    private function column(CustomFormEntry $entry)
    {
        $this->actingAs($this->staff);

        return Livewire::test(ListCustomFormEntries::class)
            ->instance()->getTable()->getColumn('review_status')->record($entry);
    }

    private function entry(string $status): CustomFormEntry
    {
        return CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $this->form->getKey(),
            'created_by' => $this->candidate->getKey(),
            'review_status' => $status,
            'data' => ['registration_status' => $status],
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
