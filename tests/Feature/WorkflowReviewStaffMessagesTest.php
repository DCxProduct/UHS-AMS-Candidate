<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkflowNotification;
use App\Support\StaticWorkflowMessages;
use App\Support\WorkflowStageMessages;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkflowReviewStaffMessagesTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;
    private User $staff;
    private CustomForm $form;
    private WorkflowNotification $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        $this->candidate = $this->user('review_candidate', 'student');
        $this->staff = $this->user('review_staff');
        $this->form = CustomForm::query()->create([
            'name' => 'Review form', 'slug' => 'review-form', 'is_active' => true,
        ]);
        $this->workflow = WorkflowNotification::query()->create([
            'name' => 'Review messages',
            'stages' => [['type' => 'stage', 'data' => [
                'stage_name' => 'Review', 'stage_type' => 'review',
                'responsible_role' => ['admin'], 'notification_channels' => ['system'],
                'review_actions' => [
                    'accept' => ['notification_message' => 'Your application was checked.'],
                    'send_back' => ['notification_message' => 'Please correct your application.'],
                    'reject' => ['notification_message' => 'Your application was rejected.'],
                ],
            ]]],
        ]);
        $this->workflow->forms()->attach($this->form);
    }

    public static function actions(): array
    {
        return [['accept'], ['send_back'], ['reject']];
    }

    #[DataProvider('actions')]
    public function test_each_review_action_notifies_only_explicitly_selected_staff(string $action): void
    {
        $other = $this->user('unselected_staff');
        $this->configure($action);
        $entry = $this->entry();

        $this->assertTrue(WorkflowStageMessages::notifyReviewAction($entry, $action));
        $notification = $this->staff->notifications()->sole();
        $this->assertSame('Staff notice: '.$action, $notification->data['body']);
        $this->assertSame('review_action_staff:'.$action, data_get($notification->data, 'viewData.workflow_event'));
        $this->assertSame((string) $entry->getKey(), data_get($notification->data, 'viewData.workflow_entry_id'));
        $this->assertSame(1, $this->candidate->notifications()->count());
        $this->assertSame(0, $other->notifications()->count());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_staff_only_delivery_keeps_the_candidate_return_false_and_allows_existing_fallbacks(): void
    {
        $this->configure('accept', ['notification_message' => '']);
        $entry = $this->entry();

        $deliveredToCandidate = WorkflowStageMessages::notifyReviewAction($entry, 'accept');

        $this->assertFalse($deliveredToCandidate);
        $this->assertSame(1, $this->staff->notifications()->count());
        $this->assertSame(0, $this->candidate->notifications()->count());
        if (! $deliveredToCandidate) {
            StaticWorkflowMessages::notifyApplicationResult($entry, 'approved');
        }
        $this->assertSame(1, $this->candidate->notifications()->count());
        $this->assertSame(1, $this->staff->notifications()->count());
    }

    public function test_candidate_channels_do_not_disable_explicit_staff_bell_messages(): void
    {
        $this->configure('send_back');
        $stages = $this->workflow->stages;
        $stages[0]['data']['notification_channels'] = [];
        $this->workflow->update(['stages' => $stages]);

        $this->assertFalse(WorkflowStageMessages::notifyReviewAction($this->entry(), 'send_back'));
        $this->assertSame(1, $this->staff->notifications()->count());
        $this->assertSame(0, $this->candidate->notifications()->count());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_legacy_workflows_and_incomplete_staff_settings_stay_quiet(): void
    {
        $this->assertTrue(WorkflowStageMessages::notifyReviewAction($this->entry(), 'accept'));
        $this->assertSame(0, $this->staff->notifications()->count());
        $this->configure('send_back', ['staff_recipient_ids' => []]);
        $this->assertTrue(WorkflowStageMessages::notifyReviewAction($this->entry(), 'send_back'));
        $this->configure('reject', ['staff_notification_message' => '']);
        $this->assertTrue(WorkflowStageMessages::notifyReviewAction($this->entry(), 'reject'));
        $this->assertSame(0, $this->staff->notifications()->count());
    }

    public function test_accept_is_deduplicated_while_send_back_and_reject_can_repeat(): void
    {
        foreach (['accept', 'send_back', 'reject'] as $action) {
            $this->configure($action);
            $entry = $this->entry();
            $this->assertTrue(WorkflowStageMessages::notifyReviewAction($entry, $action));
            $this->assertSame($action !== 'accept', WorkflowStageMessages::notifyReviewAction($entry, $action));
            $expected = $action === 'accept' ? 1 : 2;
            $this->assertSame($expected, $this->staff->notifications()
                ->where('data->viewData->workflow_event', 'review_action_staff:'.$action)->count());
            $this->assertSame($expected, $this->candidate->notifications()
                ->where('data->viewData->workflow_event', 'review_action:'.$action)->count());
        }
    }

    public function test_recipients_exclude_candidates_inactive_deleted_owner_and_invalid_ids(): void
    {
        $inactive = $this->user('inactive_staff', active: false);
        $deleted = $this->user('deleted_staff');
        $deleted->delete();
        $owner = $this->user('staff_owner');
        $this->configure('accept', ['staff_recipient_ids' => [
            $this->staff->getKey(), (string) $this->staff->getKey(), $inactive->getKey(),
            $deleted->getKey(), $owner->getKey(), $this->candidate->getKey(), 'invalid', -1,
        ]]);
        $entry = $this->entry();
        $entry->forceFill(['created_by' => $owner->getKey()])->saveQuietly();

        $this->assertFalse(WorkflowStageMessages::notifyReviewAction($entry, 'accept'));
        $this->assertSame(1, $this->staff->notifications()->count());
        $this->assertSame(0, $owner->notifications()->count());
        $this->assertSame(0, $inactive->notifications()->count());
        $this->assertSame(0, $deleted->notifications()->count());
        $this->assertSame(0, $this->candidate->notifications()->count());
    }

    public function test_failed_staff_delivery_does_not_block_other_staff_or_candidate_notifications(): void
    {
        $other = $this->user('another_selected_staff');
        $this->configure('reject', ['staff_recipient_ids' => [$this->staff->getKey(), $other->getKey()]]);
        $failingId = (int) $this->staff->getKey();
        DB::unprepared("CREATE TRIGGER reject_selected_staff_notification BEFORE INSERT ON notifications WHEN NEW.notifiable_id = {$failingId} BEGIN SELECT RAISE(FAIL, 'Staff delivery unavailable'); END;");

        $this->assertTrue(WorkflowStageMessages::notifyReviewAction($this->entry(), 'reject'));
        $this->assertSame(0, $this->staff->notifications()->count());
        $this->assertSame(1, $other->notifications()->count());
        $this->assertSame(1, $this->candidate->notifications()->count());
    }

    private function configure(string $action, array $overrides = []): void
    {
        $stages = $this->workflow->stages;
        $stages[0]['data']['review_actions'][$action] = array_replace(
            $stages[0]['data']['review_actions'][$action],
            ['staff_notification_message' => 'Staff notice: '.$action, 'staff_recipient_ids' => [$this->staff->getKey()]],
            $overrides,
        );
        $this->workflow->update(['stages' => $stages]);
    }

    private function entry(): CustomFormEntry
    {
        return CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $this->form->getKey(), 'created_by' => $this->candidate->getKey(),
            'review_status' => 'pending', 'data' => ['registration_status' => 'pending'],
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
