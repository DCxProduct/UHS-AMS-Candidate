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
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkflowStageAudienceMessagesTest extends TestCase
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
        $this->candidate = $this->user('audience_candidate', 'student');
        $this->staff = $this->user('audience_staff');
        $this->staff->assignRole(Role::findOrCreate('admin', 'web'));
        $this->form = CustomForm::query()->create(['name' => 'Audience form', 'slug' => 'audience-form', 'is_active' => true]);
        $this->workflow = WorkflowNotification::query()->create([
            'name' => 'Audience workflow',
            'stages' => collect(['payment', 'awaiting_results', 'completed'])->map(fn ($type) => ['type' => 'stage', 'data' => [
                'stage_name' => $type, 'stage_type' => $type,
                'status_message' => 'Status: '.$type,
                'notification_message' => 'Candidate: '.$type,
                'notification_channels' => ['system'],
            ]])->all(),
        ]);
        $this->workflow->forms()->attach($this->form);
    }

    public static function stageTypes(): array
    {
        return [[WorkflowStageType::Payment, 'approved'], [WorkflowStageType::AwaitingResults, 'passed'], [WorkflowStageType::Completed, 'completed']];
    }

    #[DataProvider('stageTypes')]
    public function test_each_stage_sends_independent_candidate_and_selected_staff_messages_and_shares_status(WorkflowStageType $type, string $status): void
    {
        $other = $this->user('unselected_staff');
        $this->configure($type);
        $entry = $this->entry($status);
        if ($type !== WorkflowStageType::Payment) {
            $this->pay($entry);
        }

        $this->assertTrue(WorkflowStageMessages::notify($entry, $type));
        $this->assertSame('Candidate: '.$type->value, $this->candidate->notifications()->sole()->data['body']);
        $notification = $this->staff->notifications()->sole();
        $this->assertSame('Staff: '.$type->value, $notification->data['body']);
        $this->assertSame('stage_staff:'.$type->value, data_get($notification->data, 'viewData.workflow_event'));
        $this->assertSame(0, $other->notifications()->count());
        $this->actingAs($this->staff);
        $column = Livewire::test(ListCustomFormEntries::class)->instance()->getTable()->getColumn('review_status')->record($entry);
        $this->assertSame('Status: '.$type->value, $column->formatState($column->getState()));
        $this->actingAs($this->candidate);
        $this->assertSame('Status: '.$type->value, $column->formatState($column->getState()));
        $this->assertSame($status, $entry->fresh()->review_status);
        $this->assertSame($type, WorkflowStageMessages::stageTypeFor($entry));
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    #[DataProvider('stageTypes')]
    public function test_staff_only_stage_configuration_keeps_the_candidate_return_false(WorkflowStageType $type, string $status): void
    {
        $this->configure($type, ['notification_message' => '', 'notification_channels' => []]);
        $entry = $this->entry($status);
        if ($type !== WorkflowStageType::Payment) {
            $this->pay($entry);
        }

        $this->assertFalse(WorkflowStageMessages::notifyConfiguredStage($entry, $type));
        $this->assertSame(1, $this->staff->notifications()->count());
        $this->assertSame(0, $this->candidate->notifications()->count());
        $this->assertFalse(WorkflowStageMessages::notify($entry, $type));
        $this->assertSame(1, $this->staff->notifications()->count());
    }

    public function test_required_payment_blocks_staff_and_candidate_result_messages_until_paid(): void
    {
        foreach ([WorkflowStageType::AwaitingResults, WorkflowStageType::Completed] as $type) {
            $this->configure($type, ['notification_message' => '']);
            $entry = $this->entry('passed');
            $this->assertFalse(WorkflowStageMessages::notifyConfiguredStage($entry, $type));
        }
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_editing_staff_recipients_only_applies_to_applications_started_after_the_edit(): void
    {
        $entry = $this->entry('passed');
        $this->pay($entry);
        $this->assertTrue(WorkflowStageMessages::notifyResultStage($entry));
        $this->assertSame(0, $this->staff->notifications()->count());
        $this->configure(WorkflowStageType::AwaitingResults);

        $this->assertFalse(WorkflowStageMessages::notifyResultStage($entry));
        $this->assertFalse(WorkflowStageMessages::notifyResultStage($entry));
        $this->assertSame(0, $this->staff->notifications()->count());
        $this->assertSame(1, $this->candidate->notifications()->count());

        $newEntry = $this->entry('passed');
        $this->pay($newEntry);
        $this->assertTrue(WorkflowStageMessages::notifyResultStage($newEntry));
        $this->assertSame(1, $this->staff->notifications()->count());
        $this->assertSame(2, $this->candidate->notifications()->count());
    }

    public function test_replacing_a_notification_keeps_the_started_applications_original_messages_after_a_template_edit(): void
    {
        $type = WorkflowStageType::Payment;
        $this->configure($type);
        $entry = $this->entry('approved');
        $this->assertTrue(WorkflowStageMessages::notifyConfiguredStage($entry, $type));
        $this->configure($type, ['notification_message' => ''], ['staff_notification_message' => 'Updated staff notice']);

        $this->assertTrue(WorkflowStageMessages::notifyConfiguredStage($entry, $type, replaceExisting: true));
        $this->assertSame('Staff: payment', $this->staff->notifications()->sole()->data['body']);
        $this->assertSame('Candidate: payment', $this->candidate->notifications()->sole()->data['body']);

        $newEntry = $this->entry('approved');
        $this->assertFalse(WorkflowStageMessages::notifyConfiguredStage($newEntry, $type, replaceExisting: true));
        $newStaffNotice = $this->staff->notifications()
            ->where('data->viewData->workflow_entry_id', (string) $newEntry->getKey())->sole();
        $this->assertSame('Updated staff notice', $newStaffNotice->data['body']);
        $this->assertSame('Candidate: payment', $this->candidate->notifications()->sole()->data['body']);
    }

    public function test_awaiting_results_removes_prior_completed_messages_for_both_audiences(): void
    {
        $this->configure(WorkflowStageType::Completed);
        $this->configure(WorkflowStageType::AwaitingResults);
        $entry = $this->entry('passed');
        $this->pay($entry);
        $this->assertTrue(WorkflowStageMessages::notifyConfiguredStage($entry, WorkflowStageType::Completed));
        $this->assertTrue(WorkflowStageMessages::notifyConfiguredStage($entry, WorkflowStageType::AwaitingResults));

        $this->assertSame('Staff: awaiting_results', $this->staff->notifications()->sole()->data['body']);
        $this->assertSame('Candidate: awaiting_results', $this->candidate->notifications()->sole()->data['body']);
    }

    public function test_shared_result_status_keeps_the_existing_passed_completed_text_fallback(): void
    {
        $entry = $this->entry('passed', ['candidate_status' => 'passed']);
        $this->pay($entry);
        $this->actingAs($this->staff);
        $column = Livewire::test(ListCustomFormEntries::class)->instance()->getTable()->getColumn('review_status')->record($entry);
        $this->assertSame('Status: completed', $column->formatState($column->getState()));
        $this->actingAs($this->candidate);
        $this->assertSame('Status: completed', $column->formatState($column->getState()));
        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageMessages::stageTypeFor($entry));
    }

    public function test_legacy_workflows_and_empty_recipient_lists_do_not_broadcast(): void
    {
        $entry = $this->entry('approved');
        $this->assertTrue(WorkflowStageMessages::notifyConfiguredStage($entry, WorkflowStageType::Payment));
        $this->configure(WorkflowStageType::Payment, [], ['staff_recipient_ids' => []]);
        $this->assertTrue(WorkflowStageMessages::notifyConfiguredStage($entry, WorkflowStageType::Payment));
        $this->assertSame(0, $this->staff->notifications()->count());
    }

    public function test_only_selected_active_staff_are_notified_and_failures_do_not_block_others(): void
    {
        $inactive = $this->user('inactive_staff', active: false);
        $deleted = $this->user('deleted_staff');
        $deleted->delete();
        $other = $this->user('other_staff');
        $this->configure(WorkflowStageType::Payment, [], ['staff_recipient_ids' => [
            $this->staff->getKey(), $this->staff->getKey(), $inactive->getKey(), $deleted->getKey(),
            $other->getKey(), $this->candidate->getKey(), 'invalid', -1,
        ]]);
        $failingId = (int) $this->staff->getKey();
        DB::unprepared("CREATE TRIGGER reject_audience_staff_notification BEFORE INSERT ON notifications WHEN NEW.notifiable_id = {$failingId} BEGIN SELECT RAISE(FAIL, 'Staff delivery unavailable'); END;");

        $this->assertTrue(WorkflowStageMessages::notifyConfiguredStage($this->entry('approved'), WorkflowStageType::Payment));
        $this->assertSame(0, $this->staff->notifications()->count());
        $this->assertSame(0, $inactive->notifications()->count());
        $this->assertSame(0, $deleted->notifications()->count());
        $this->assertSame(1, $other->notifications()->count());
        $this->assertSame(1, $this->candidate->notifications()->count());
    }

    private function configure(WorkflowStageType $type, array $stageOverrides = [], array $staffOverrides = []): void
    {
        $stages = $this->workflow->stages;
        foreach ($stages as &$item) {
            if ($item['data']['stage_type'] === $type->value) {
                $item['data'] = array_replace($item['data'], $stageOverrides);
                $item['data']['audience_messages'] = array_replace([
                    'staff_notification_message' => 'Staff: '.$type->value,
                    'staff_recipient_ids' => [$this->staff->getKey()],
                ], $staffOverrides);
            }
        }
        unset($item);
        $this->workflow->update(['stages' => $stages]);
    }

    private function entry(string $status, array $data = []): CustomFormEntry
    {
        return CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $this->form->getKey(), 'created_by' => $this->candidate->getKey(),
            'review_status' => $status,
            'data' => ['registration_status' => $status, 'candidate_status' => 'pending', ...$data],
        ]);
    }

    private function pay(CustomFormEntry $entry): void
    {
        Payment::query()->forceCreate([
            'users_id' => $this->candidate->getKey(), 'form_id' => $this->form->getKey(),
            'custom_form_entry_id' => $entry->getKey(), 'receipt_number' => 'R-'.$entry->getKey(),
            'status_payt' => 'paid', 'amount_usd' => 10, 'amount_kh' => 41000,
            'exchange_rate' => 4100, 'datetime_pay' => now(),
        ]);
    }

    private function user(string $username, string $type = 'admin', bool $active = true): User
    {
        return User::query()->forceCreate([
            'registration_type' => $type, 'name' => $username, 'username' => $username,
            'email' => $username.'@example.test', 'date_of_birth' => '2000-01-01',
            'password' => 'password', 'is_active' => $active,
        ]);
    }
}
