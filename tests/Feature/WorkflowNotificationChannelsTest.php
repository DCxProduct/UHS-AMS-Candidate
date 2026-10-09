<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\EditWorkflowNotification;
use App\Mail\WorkflowStageMail;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Support\WorkflowStageMessages;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class WorkflowNotificationChannelsTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private CustomForm $form;

    private WorkflowNotification $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.plasgate.private_key' => 'key',
            'services.plasgate.secret' => 'secret',
            'services.plasgate.test_phone' => null,
        ]);
        Http::fake(['cloudapi.plasgate.com/*' => Http::response(['batchId' => 1])]);
        Mail::fake();

        $this->student = User::query()->forceCreate([
            'registration_type' => 'student',
            'name' => 'Dara',
            'username' => 'dara',
            'email' => 'dara@example.test',
            'phone' => '012345678',
            'date_of_birth' => '2000-01-01',
            'password' => Hash::make('password'),
            'is_active' => true,
            'locale' => 'en',
        ]);

        $this->form = CustomForm::query()->create([
            'name' => 'Admission Form',
            'slug' => 'admission-form',
            'is_active' => true,
            'menu_placement' => 'sidebar',
        ]);

        $this->workflow = WorkflowNotification::query()->create([
            'name' => 'Admission',
            'stages' => [
                ['type' => 'stage', 'data' => ['stage_name' => 'Submit', 'stage_type' => 'form_submission']],
                ['type' => 'stage', 'data' => [
                    'stage_name' => 'Review',
                    'stage_type' => 'review',
                    'responsible_role' => 'admin',
                    'notification_message' => 'We are checking your documents.',
                ]],
                ['type' => 'stage', 'data' => [
                    'stage_name' => 'Awaiting Results',
                    'stage_type' => 'awaiting_results',
                    'responsible_role' => 'admin',
                    'notification_message' => 'Your exam is coming.',
                ]],
            ],
        ]);
        $this->workflow->forms()->attach($this->form);
    }

    public function test_stages_saved_before_the_tick_boxes_send_the_system_notification_only(): void
    {
        $this->assertTrue(WorkflowStageMessages::notify($this->entry('pending'), WorkflowStageType::Review));

        $this->assertSame(1, $this->student->notifications()->count());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_all_three_channels_send_the_same_message(): void
    {
        $this->setChannels(1, ['system', 'sms', 'email']);

        $this->assertTrue(WorkflowStageMessages::notify($this->entry('pending'), WorkflowStageType::Review));

        $this->assertSame('We are checking your documents.', $this->student->notifications()->sole()->data['body']);
        Http::assertSent(fn (HttpRequest $request): bool => $request['messages'][0]['to'][0] === '85512345678'
            && str_contains($request['messages'][0]['content'], 'We are checking your documents.'));
        Mail::assertSent(WorkflowStageMail::class, fn (WorkflowStageMail $mail): bool => $mail->hasTo('dara@example.test')
            && $mail->text === 'We are checking your documents.');
    }

    public function test_only_the_ticked_channels_are_used(): void
    {
        $this->setChannels(1, ['sms']);

        $this->assertTrue(WorkflowStageMessages::notify($this->entry('pending'), WorkflowStageType::Review));

        $this->assertSame(0, $this->student->notifications()->count());
        Http::assertSentCount(1);
        Mail::assertNothingSent();
    }

    public function test_no_ticked_channel_sends_nothing(): void
    {
        $this->setChannels(1, []);

        $this->assertFalse(WorkflowStageMessages::notify($this->entry('pending'), WorkflowStageType::Review));

        $this->assertSame(0, $this->student->notifications()->count());
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_send_once_still_works_without_the_system_notification(): void
    {
        $this->setChannels(2, ['sms', 'email']);
        $entry = $this->entry('passed', ['candidate_status' => 'passed']);
        $this->pay($entry);

        $this->assertTrue(WorkflowStageMessages::notifyResultStage($entry));
        $this->assertFalse(WorkflowStageMessages::notifyResultStage($entry));

        Http::assertSentCount(1);
        Mail::assertSentCount(1);
        $this->assertDatabaseHas('workflow_notification_deliveries', [
            'custom_form_entry_id' => $entry->id,
            'stage_type' => 'awaiting_results',
        ]);
    }

    public function test_a_failed_sms_does_not_stop_the_other_channels(): void
    {
        Http::fake(['cloudapi.plasgate.com/*' => Http::response('Server error', 500)]);
        $this->setChannels(1, ['system', 'sms', 'email']);

        $this->assertTrue(WorkflowStageMessages::notify($this->entry('pending'), WorkflowStageType::Review));

        $this->assertSame(1, $this->student->notifications()->count());
        Mail::assertSentCount(1);
    }

    public function test_the_general_sms_copy_skips_workflow_messages_but_not_other_notifications(): void
    {
        $this->assertTrue(WorkflowStageMessages::notify($this->entry('pending'), WorkflowStageType::Review));
        Http::assertNothingSent();

        Notification::make()->title('Payment received')->sendToDatabase($this->student);
        Http::assertSentCount(1);
    }

    public function test_admin_can_tick_the_channels_on_a_stage(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('app'));
        Role::query()->create(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::query()->forceCreate([
            'registration_type' => 'admin',
            'name' => 'admin_user',
            'username' => 'admin_user',
            'email' => 'admin_user@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $this->workflow->getRouteKey()]);
        $reviewKey = array_keys($component->get('data.stages'))[1];

        // Existing stages open with only "System" ticked.
        $component->assertSet("data.stages.{$reviewKey}.data.notification_channels", ['system'])
            ->set("data.stages.{$reviewKey}.data.notification_channels", ['system', 'email'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['system', 'email'], $this->workflow->refresh()->stages[1]['data']['notification_channels']);
    }

    private function setChannels(int $stageIndex, array $channels): void
    {
        $stages = $this->workflow->stages;
        $stages[$stageIndex]['data']['notification_channels'] = $channels;
        $this->workflow->update(['stages' => $stages]);
    }

    private function entry(string $reviewStatus, array $data = []): CustomFormEntry
    {
        $entry = CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $this->form->id,
            'data' => ['registration_status' => $reviewStatus, ...$data],
            'created_by' => $this->student->id,
        ]);

        DB::table('custom_form_entries')->where('id', $entry->id)->update(['review_status' => $reviewStatus]);

        return $entry->refresh();
    }

    private function pay(CustomFormEntry $entry): void
    {
        Payment::query()->forceCreate([
            'users_id' => $this->student->id,
            'form_id' => $entry->custom_form_id,
            'custom_form_entry_id' => $entry->id,
            'receipt_number' => 'R-'.$entry->id,
            'status_payt' => 'paid',
            'amount_usd' => 10,
            'amount_kh' => 41000,
            'exchange_rate' => 4100,
            'datetime_pay' => now(),
        ]);
    }
}
