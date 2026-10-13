<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\EditWorkflowNotification;
use App\Mail\WorkflowStageMail;
use App\Models\EmailTemplate;
use App\Models\Payment;
use App\Models\Role;
use App\Models\SmsTemplate;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Support\WorkflowStageMessages;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Mail\MailManager;
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

    public function test_a_stage_can_send_with_its_chosen_email_and_sms_templates(): void
    {
        EmailTemplate::query()->create([
            'key' => 'review_result',
            'name' => 'Review result',
            'header_title' => 'UHS-AMS',
            'subject' => '{{ form }}: {{ stage }}',
            'button' => 'Open',
            'body' => '<p>Dear {{ name }}, {{ message }} ({{ status }})</p>',
        ]);
        SmsTemplate::query()->create([
            'key' => 'review_sms',
            'name' => 'Review SMS',
            'app_name' => 'UHS-AMS',
            'body' => '{{ app }}: {{ name }}, {{ message }}',
        ]);

        $stages = $this->workflow->stages;
        $stages[1]['data']['notification_channels'] = ['sms', 'email'];
        $stages[1]['data']['status_message'] = 'Under review';
        $stages[1]['data']['email_template_key'] = 'review_result';
        $stages[1]['data']['sms_template_key'] = 'review_sms';
        $this->workflow->update(['stages' => $stages]);

        Mail::swap(new MailManager(app()));
        config(['mail.default' => 'array']);

        $this->assertTrue(WorkflowStageMessages::notify($this->entry('pending'), WorkflowStageType::Review));

        Http::assertSent(fn (HttpRequest $request): bool => $request['messages'][0]['content'] === 'UHS-AMS: Dara, We are checking your documents.');

        $sent = app('mailer')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $this->assertSame('dara@example.test', $sent->getTo()[0]->getAddress());
        $this->assertSame('Admission Form: Review', $sent->getSubject());
        $this->assertStringContainsString('Dear Dara, We are checking your documents. (Under review)', $sent->getHtmlBody());
    }

    public function test_a_deleted_template_falls_back_to_the_plain_message(): void
    {
        $stages = $this->workflow->stages;
        $stages[1]['data']['notification_channels'] = ['sms', 'email'];
        $stages[1]['data']['email_template_key'] = 'deleted_template';
        $stages[1]['data']['sms_template_key'] = 'deleted_template';
        $this->workflow->update(['stages' => $stages]);

        $this->assertTrue(WorkflowStageMessages::notify($this->entry('pending'), WorkflowStageType::Review));

        Http::assertSent(fn (HttpRequest $request): bool => str_contains($request['messages'][0]['content'], 'We are checking your documents.'));
        Mail::assertSent(WorkflowStageMail::class);
    }

    public function test_a_template_is_kept_only_for_a_ticked_channel(): void
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
        SmsTemplate::query()->create(['key' => 'review_sms', 'name' => 'Review SMS', 'app_name' => 'UHS-AMS', 'body' => 'Hi']);

        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $this->workflow->getRouteKey()]);
        $reviewKey = array_keys($component->get('data.stages'))[1];
        $path = "stages.{$reviewKey}.data";

        // SMS ticked: the chosen SMS template is saved; Email not ticked: no email template is kept.
        $component->set("data.{$path}.notification_channels", ['system', 'sms'])
            ->set("data.{$path}.sms_template_key", 'review_sms')
            ->set("data.{$path}.email_template_key", 'anything')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertArrayNotHasKey('email_template_key', $this->workflow->refresh()->stages[1]['data']);
        $this->assertSame('review_sms', $this->workflow->refresh()->stages[1]['data']['sms_template_key']);
    }

    public function test_the_email_template_set_for_an_action_is_used_automatically(): void
    {
        EmailTemplate::query()->create(['key' => 'accepted', 'name' => 'Accepted', 'action' => 'accept', 'header_title' => 'UHS-AMS', 'subject' => 'Accepted: {{ form }}', 'button' => 'Open', 'body' => '<p>{{ message }}</p>']);
        EmailTemplate::query()->create(['key' => 'chosen', 'name' => 'Chosen', 'header_title' => 'UHS-AMS', 'subject' => 'Chosen on the stage', 'button' => 'Open', 'body' => '<p>{{ message }}</p>']);

        $stages = $this->workflow->stages;
        $stages[1]['data']['notification_channels'] = ['email'];
        $stages[1]['data']['review_actions'] = ['accept' => ['notification_message' => 'You are accepted.']];
        $this->workflow->update(['stages' => $stages]);

        Mail::swap(new MailManager(app()));
        config(['mail.default' => 'array']);

        // No template chosen on the stage: the "Review – Accept" template is used.
        $this->assertTrue(WorkflowStageMessages::notifyReviewAction($this->entry('pending'), 'accept'));
        $sent = app('mailer')->getSymfonyTransport()->messages()->last()->getOriginalMessage();
        $this->assertSame('Accepted: Admission Form', $sent->getSubject());
        $this->assertStringContainsString('You are accepted.', $sent->getHtmlBody());

        // A template chosen on the stage wins over the action template.
        $stages[1]['data']['email_template_key'] = 'chosen';
        $this->workflow->update(['stages' => $stages]);
        $this->assertTrue(WorkflowStageMessages::notifyReviewAction($this->entry('pending'), 'accept'));
        $this->assertSame('Chosen on the stage', app('mailer')->getSymfonyTransport()->messages()->last()->getOriginalMessage()->getSubject());
    }

    public function test_the_sms_template_set_for_an_action_is_used_automatically(): void
    {
        SmsTemplate::query()->create(['key' => 'accepted_sms', 'name' => 'Accepted SMS', 'action' => 'accept', 'app_name' => 'UHS-AMS', 'body' => 'ACCEPT: {{ message }}']);
        SmsTemplate::query()->create(['key' => 'chosen_sms', 'name' => 'Chosen SMS', 'app_name' => 'UHS-AMS', 'body' => 'CHOSEN: {{ message }}']);

        $stages = $this->workflow->stages;
        $stages[1]['data']['notification_channels'] = ['sms'];
        $stages[1]['data']['review_actions'] = ['accept' => ['notification_message' => 'You are accepted.']];
        $this->workflow->update(['stages' => $stages]);

        // No template chosen on the stage: the "Review – Accept" SMS template is used.
        $this->assertTrue(WorkflowStageMessages::notifyReviewAction($this->entry('pending'), 'accept'));
        Http::assertSent(fn (HttpRequest $request): bool => $request['messages'][0]['content'] === 'ACCEPT: You are accepted.');

        // A template chosen on the stage wins over the action template.
        $stages[1]['data']['sms_template_key'] = 'chosen_sms';
        $this->workflow->update(['stages' => $stages]);
        $this->assertTrue(WorkflowStageMessages::notifyReviewAction($this->entry('pending'), 'accept'));
        Http::assertSent(fn (HttpRequest $request): bool => $request['messages'][0]['content'] === 'CHOSEN: You are accepted.');
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
