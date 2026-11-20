<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType as StageBehavior;
use App\Mail\WorkflowStageMail;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowButton;
use App\Models\WorkflowNotification;
use App\Models\WorkflowStageType;
use App\Services\WorkflowCustomButtonService;
use App\Support\WorkflowButtonColors;
use App\Support\WorkflowStageMessages;
use App\Support\WorkflowStageProgression;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\ListCustomFormEntries;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class WorkflowCustomButtonExecutionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $candidate;

    private User $recipient;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        $this->admin = $this->user('custom-button-admin', 'admin', 'admin');
        $this->candidate = $this->user('custom-button-candidate', 'student');
        $this->recipient = $this->user('custom-button-recipient', 'admin', 'custom_receiver');
        $this->actingAs($this->admin);
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(null);
        parent::tearDown();
    }

    public static function candidateChannels(): array
    {
        return [
            'all channels' => [['system', 'sms', 'email']],
            'no channels' => [[]],
            'SMS only' => [['sms']],
            'email only' => [['email']],
        ];
    }

    #[DataProvider('candidateChannels')]
    public function test_custom_click_uses_only_frozen_selected_channels_and_never_sends_twice(array $channels): void
    {
        config(['services.plasgate.private_key' => 'key', 'services.plasgate.secret' => 'secret', 'services.plasgate.test_phone' => null]);
        Http::fake(['cloudapi.plasgate.com/*' => Http::response(['batchId' => 1])]);
        $this->candidate->update(['phone' => '012345678', 'locale' => 'en']);
        $button = $this->button('Record selected channels');
        $type = $this->type('Channel-aware custom stage', [$button->id]);
        $stage = $this->customStage($type, 'Channel');
        $stage['data']['notification_channels'] = $channels;
        $stage['data']['submission_messages']['staff_notification_message'] = null;
        $stage['data']['submission_messages']['staff_recipient_roles'] = [];
        [$entry, $workflow] = $this->entry([$stage], 'channels-'.(implode('-', $channels) ?: 'none'));
        $snapshot = $entry->getRawOriginal('workflow_snapshot');
        $before = $this->businessState($entry);
        $changed = $workflow->stages;
        $changed[0]['data']['notification_channels'] = $channels === [] ? ['system', 'sms', 'email'] : [];
        $changed[0]['data']['submission_messages']['candidate_notification_message'] = 'Changed template message';
        $changed[0]['data']['submission_messages']['candidate_status_message'] = 'Changed template status';
        $workflow->update(['stages' => $changed]);
        $service = app(WorkflowCustomButtonService::class);
        $this->assertDatabaseCount('notifications', 0);
        Http::assertNothingSent();
        Mail::assertNothingSent();

        $this->assertTrue($service->execute($entry, 0, $button->id));

        $this->assertSame('Channel recorded', WorkflowStageMessages::statusText($entry));
        $this->assertSame($before, $this->businessState($entry->fresh()));
        $this->assertSame($snapshot, $entry->getRawOriginal('workflow_snapshot'));
        $systemCount = in_array('system', $channels, true) ? 1 : 0;
        $smsCount = in_array('sms', $channels, true) ? 1 : 0;
        $emailCount = in_array('email', $channels, true) ? 1 : 0;
        $this->assertSame($systemCount, $this->candidate->notifications()->count());
        $this->assertSame(0, $this->recipient->notifications()->count());
        if ($systemCount) {
            $this->assertSame('Channel candidate', $this->candidate->notifications()->sole()->data['body']);
        }
        Http::assertSentCount($smsCount);
        if ($smsCount) {
            Http::assertSent(fn (HttpRequest $request): bool => $request['messages'][0]['to'][0] === '85512345678'
                && str_contains($request['messages'][0]['content'], 'Channel candidate'));
        }
        Mail::assertSentCount($emailCount);
        if ($emailCount) {
            Mail::assertSent(WorkflowStageMail::class, fn (WorkflowStageMail $mail): bool => $mail->hasTo($this->candidate->email)
                && $mail->text === 'Channel candidate');
        }

        $this->assertFalse($service->execute($entry, 0, $button->id));
        $this->assertSame($systemCount, $this->candidate->notifications()->count());
        Http::assertSentCount($smsCount);
        Mail::assertSentCount($emailCount);
    }

    public function test_custom_click_sends_configured_messages_and_completes_only_its_checkpoint(): void
    {
        $button = $this->button('Record receipt');
        $type = $this->type('Custom receipt', [$button->id]);
        [$entry] = $this->entry([$this->customStage($type, 'Receipt'), $this->reviewStage()], 'receipt');
        $before = $this->businessState($entry);
        $snapshot = $entry->getRawOriginal('workflow_snapshot');
        $service = app(WorkflowCustomButtonService::class);
        $this->assertSame(StageBehavior::Custom, WorkflowStageProgression::currentType($entry));
        $this->assertTrue($service->canExecute($entry, 0, $button->id, $this->admin));
        $this->assertDatabaseCount('notifications', 0);

        $this->assertTrue($service->execute($entry, 0, $button->id));

        $entry->refresh();
        $this->assertSame($before, $this->businessState($entry));
        $this->assertSame($snapshot, $entry->getRawOriginal('workflow_snapshot'));
        $this->assertNotNull(WorkflowStageProgression::completedAt($entry, 0));
        $this->assertNull(WorkflowStageProgression::completedAt($entry, 1));
        $this->assertSame(StageBehavior::Review, WorkflowStageProgression::currentType($entry));
        $this->assertSame('Receipt recorded', WorkflowStageMessages::statusText($entry));
        $this->assertSame('Receipt recorded', data_get($entry->data, 'workflow_custom_status.text'));
        $this->assertSame($this->admin->id, data_get($entry->data, 'workflow_custom_button_events.0:'.$button->id.'.by'));
        $this->assertMessages('Receipt candidate', 'Receipt staff', 0, $button->id);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('ams_payment_requests', 0);
        $this->assertDatabaseCount('candidate_id_sequences', 0);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_repeated_custom_stages_and_stale_double_clicks_are_isolated_by_stage_index(): void
    {
        $button = $this->button('Same label at two checkpoints');
        $type = $this->type('Repeated custom type', [$button->id]);
        [$entry] = $this->entry([$this->customStage($type, 'First'), $this->customStage($type, 'Second')], 'repeated');
        $service = app(WorkflowCustomButtonService::class);
        $stale = $entry->fresh();
        $before = $this->businessState($entry);

        $this->assertFalse($service->canExecute($entry, 1, $button->id, $this->admin));
        $this->assertTrue($service->execute($entry, 0, $button->id));
        $afterFirst = $entry->fresh()->getRawOriginal();
        $this->assertFalse($service->execute($stale, 0, $button->id));
        $this->assertSame($afterFirst, $entry->fresh()->getRawOriginal());
        $this->assertSame(1, WorkflowStageProgression::currentStage($entry->fresh())['_index']);
        $this->assertTrue($service->canExecute($entry->fresh(), 1, $button->id, $this->admin));
        $this->assertSame(1, $this->candidate->notifications()->count());
        $this->assertSame(1, $this->recipient->notifications()->count());

        $this->assertTrue($service->execute($entry, 1, $button->id));
        $this->assertTrue(WorkflowStageProgression::finished($entry->fresh()));
        $this->assertNotNull(WorkflowStageProgression::completedAt($entry, 0));
        $this->assertNotNull(WorkflowStageProgression::completedAt($entry, 1));
        $this->assertSame('Second recorded', WorkflowStageMessages::statusText($entry));
        $this->assertSame($before, $this->businessState($entry->fresh()));
        $this->assertSame(2, $this->candidate->notifications()->count());
        $this->assertSame(2, $this->recipient->notifications()->count());
        $this->assertCount(2, data_get($entry->fresh()->data, 'workflow_custom_button_events'));
        $this->assertFalse($service->execute($entry, 1, $button->id));
        $this->assertSame(2, $this->candidate->notifications()->count());
        $this->assertSame(2, $this->recipient->notifications()->count());
        $this->assertFalse(WorkflowStageProgression::readyForFinal($entry));
        WorkflowStageProgression::advance($entry);
        WorkflowStageProgression::submitted($entry);
        $this->assertSame($before, $this->businessState($entry->fresh()));
    }

    public function test_table_renders_distinct_frozen_buttons_and_executes_the_selected_callback(): void
    {
        $first = $this->button('First custom table button');
        $second = $this->button('Second custom table button');
        $second->update(['icon' => 'heroicon-o-folder', 'color' => 'zinc']);
        $type = $this->type('Custom table stage', [$first->id, $second->id]);
        [$entry, $workflow] = $this->entry([$this->customStage($type, 'Table'), $this->reviewStage()], 'table');
        $before = $this->businessState($entry);
        $snapshot = $entry->getRawOriginal('workflow_snapshot');
        $savedStages = $workflow->getRawOriginal('stages');
        $component = Livewire::test(ListCustomFormEntries::class)
            ->assertCanSeeTableRecords([$entry])
            ->assertTableActionVisible('custom_workflow_stage', $entry)
            ->assertSee('First custom table button')
            ->assertSee('Second custom table button');
        $html = $component->html();
        $this->assertStringContainsString('-'.$entry->id.'-0-'.$first->id.'"', $html);
        $this->assertStringContainsString('-'.$entry->id.'-0-'.$second->id.'"', $html);
        $action = $component->instance()->getTable()->getAction('custom_workflow_stage')
            ->record($entry)->arguments(['stage_index' => 0, 'button_id' => $second->id]);
        $this->assertSame('zinc', $action->getColor());

        $second->update(['color' => 'info', 'label_en' => 'Updated catalog label', 'icon' => 'heroicon-o-bell']);
        $component->call('$refresh');
        $this->assertStringContainsString('fi-color-info', $component->html());
        $this->assertSame('Second custom table button', $action->getLabel());
        $this->assertSame('heroicon-o-folder', $action->getIcon());
        $this->assertSame('info', $action->getColor());
        $frozenButton = collect(data_get($entry->workflow_snapshot, 'stages.0.data.buttons'))->firstWhere('id', $second->id);
        $this->assertSame('zinc', $frozenButton['color']);
        $this->view('components.workflow-button-preview', ['buttons' => [$frozenButton]])
            ->assertSee('fi-color-info', false)
            ->assertSee('Second custom table button')
            ->assertDontSee('Updated catalog label');
        $this->assertSame($snapshot, $entry->fresh()->getRawOriginal('workflow_snapshot'));
        $this->assertSame($savedStages, $workflow->fresh()->getRawOriginal('stages'));
        $this->assertDatabaseCount('notifications', 0);

        $component->callTableAction('custom_workflow_stage', $entry, arguments: ['stage_index' => 0, 'button_id' => $second->id])
            ->assertHasNoTableActionErrors();

        $entry->refresh();
        $this->assertSame($before, $this->businessState($entry));
        $this->assertSame($snapshot, $entry->getRawOriginal('workflow_snapshot'));
        $this->assertNotNull(WorkflowStageProgression::completedAt($entry, 0));
        $this->assertSame(StageBehavior::Review, WorkflowStageProgression::currentType($entry));
        $this->assertArrayHasKey('0:'.$second->id, data_get($entry->data, 'workflow_custom_button_events'));
        $this->assertArrayNotHasKey('0:'.$first->id, data_get($entry->data, 'workflow_custom_button_events'));
        $this->assertMessages('Table candidate', 'Table staff', 0, $second->id);
    }

    public function test_custom_color_falls_back_to_snapshot_when_catalog_button_is_removed(): void
    {
        $button = $this->button('Archived custom button');
        $button->update(['color' => 'zinc']);
        $definition = $button->toDefinition();
        $button->update(['color' => 'info', 'is_active' => false]);
        $this->assertSame('info', WorkflowButtonColors::forDefinition($definition));
        $this->assertSame('zinc', WorkflowButtonColors::forDefinition([...$definition, 'action_key' => 'accept']));
        unset($definition['action_key']);
        $this->assertSame('zinc', WorkflowButtonColors::forDefinition($definition));
        $definition['action_key'] = null;

        $button->delete();

        $this->assertSame('zinc', WorkflowButtonColors::forDefinition($definition));
        $this->view('components.workflow-button-preview', ['buttons' => [$definition]])
            ->assertSee('fi-color-zinc', false);
    }

    public function test_a_custom_button_in_a_real_review_stage_does_not_advance_or_change_review_logic(): void
    {
        $display = $this->button('Acknowledge message');
        $accept = $this->button('Existing check', 'accept');
        $type = $this->type('Mixed review', [$display->id, $accept->id]);
        $stage = ['type' => 'stage', 'data' => [
            'stage_name' => 'Mixed review', 'stage_type_id' => $type->id, 'stage_type' => 'review',
            'responsible_role' => ['admin'], 'notification_channels' => ['system'],
            'status_message' => 'Mixed recorded', 'notification_message' => 'Mixed candidate',
            'audience_messages' => ['staff_notification_message' => 'Mixed staff', 'staff_recipient_roles' => ['custom_receiver']],
        ]];
        [$entry] = $this->entry([$stage], 'mixed');
        $service = app(WorkflowCustomButtonService::class);
        $before = $this->businessState($entry);
        $progress = data_get($entry->data, 'workflow_progress');
        $this->assertFalse($service->canExecute($entry, 0, $accept->id, $this->admin));

        $this->assertTrue($service->execute($entry, 0, $display->id));

        $entry->refresh();
        $this->assertSame($before, $this->businessState($entry));
        $this->assertSame($progress, data_get($entry->data, 'workflow_progress'));
        $this->assertNull(WorkflowStageProgression::completedAt($entry, 0));
        $this->assertSame(StageBehavior::Review, WorkflowStageProgression::currentType($entry));
        $this->assertFalse(WorkflowStageProgression::finished($entry));
        $this->assertSame('Mixed recorded', WorkflowStageMessages::statusText($entry));
        $this->assertMessages('Mixed candidate', 'Mixed staff', 0, $display->id);
        $this->assertFalse($service->execute($entry, 0, $display->id));
        $this->assertSame(1, $this->candidate->notifications()->count());
        $this->assertSame(1, $this->recipient->notifications()->count());
    }

    public function test_custom_checkpoint_preserves_the_existing_final_completion_notification_gate(): void
    {
        $button = $this->button('Prepare final notification');
        $type = $this->type('Before normal completion', [$button->id]);
        $completed = ['type' => 'stage', 'data' => ['stage_name' => 'Final notification', 'stage_type' => 'completed',
            'responsible_role' => [], 'status_message' => 'Completed normally', 'notification_message' => 'Existing final message', 'notification_channels' => ['system']]];
        [$entry] = $this->entry([$this->customStage($type, 'Prepared'), $completed], 'custom-then-completed');
        $before = $this->businessState($entry);

        $this->assertTrue(app(WorkflowCustomButtonService::class)->execute($entry, 0, $button->id));

        $entry->refresh();
        $this->assertSame($before, $this->businessState($entry));
        $this->assertSame(StageBehavior::Completed, WorkflowStageProgression::currentType($entry));
        $this->assertTrue(WorkflowStageProgression::readyForFinal($entry));
        $this->assertFalse(WorkflowStageProgression::finished($entry));
        $this->assertNull(WorkflowStageProgression::completedAt($entry, 1));
        $this->assertSame(1, $this->candidate->notifications()->count());
        $this->assertTrue(WorkflowStageMessages::notifyConfiguredStage($entry, StageBehavior::Completed));
        $this->assertTrue($this->candidate->notifications()->get()->contains(fn ($notification): bool => $notification->data['body'] === 'Existing final message'));
        $this->assertTrue(WorkflowStageProgression::finished($entry->fresh()));
    }

    public static function mixedStageNotificationChannels(): array
    {
        return [
            'completion with system bell' => ['completed', ['system']],
            'completion with SMS and email' => ['completed', ['sms', 'email']],
            'results with system bell' => ['awaiting_results', ['system']],
            'results with SMS and email' => ['awaiting_results', ['sms', 'email']],
        ];
    }

    #[DataProvider('mixedStageNotificationChannels')]
    public function test_mixed_custom_notifications_never_release_or_replace_the_builtin_notification(string $stageType, array $channels): void
    {
        config(['services.plasgate.private_key' => 'key', 'services.plasgate.secret' => 'secret', 'services.plasgate.test_phone' => null]);
        Http::fake(['cloudapi.plasgate.com/*' => Http::response(['batchId' => 1])]);
        $this->candidate->update(['phone' => '012345678', 'locale' => 'en']);
        $custom = $this->button('Record mixed stage message');
        $builtin = $this->button('Existing stage action', $stageType === 'completed' ? 'notify' : 'passed');
        $type = $this->type('Mixed notification '.$stageType, [$custom->id, $builtin->id]);
        [$entry] = $this->entry([['type' => 'stage', 'data' => [
            'stage_name' => 'Original '.$stageType, 'stage_type_id' => $type->id, 'stage_type' => $stageType,
            'responsible_role' => ['admin'], 'notification_channels' => $channels,
            'status_message' => 'Original stage status', 'notification_message' => 'Original candidate message',
            'audience_messages' => ['staff_notification_message' => 'Original staff message', 'staff_recipient_roles' => ['custom_receiver']],
        ]]], 'mixed-notification-'.$stageType.'-'.implode('-', $channels));
        $behavior = StageBehavior::from($stageType);
        $before = $this->businessState($entry);
        $snapshot = $entry->getRawOriginal('workflow_snapshot');
        $event = 'custom_stage:0:button:'.$custom->id.':candidate';

        $this->assertTrue(app(WorkflowCustomButtonService::class)->execute($entry, 0, $custom->id));

        $this->assertSame($before, $this->businessState($entry->fresh()));
        $this->assertSame($snapshot, $entry->getRawOriginal('workflow_snapshot'));
        $this->assertSame($behavior, WorkflowStageProgression::currentType($entry));
        $this->assertNull(WorkflowStageProgression::completedAt($entry, 0));
        $this->assertFalse(WorkflowStageMessages::hasSentNotification($entry, $behavior));
        $customDelivery = DB::table('workflow_notification_deliveries')->where('custom_form_entry_id', $entry->id)->where('workflow_event', $event)->sole();
        $this->assertSame('custom', $customDelivery->stage_type);
        $this->assertSame($channels, json_decode($customDelivery->channels, true));
        $customStaff = $this->recipient->notifications()->sole();
        $this->assertSame('custom', data_get($customStaff->data, 'viewData.workflow_stage_type'));
        $this->assertSame($stageType, data_get($customStaff->data, 'viewData.workflow_origin_stage_type'));
        $candidateNotification = $this->candidate->notifications()->first();
        if (in_array('system', $channels, true)) {
            $this->assertNotNull($candidateNotification);
            $this->assertSame('custom', data_get($candidateNotification->data, 'viewData.workflow_stage_type'));
            $this->assertSame($stageType, data_get($candidateNotification->data, 'viewData.workflow_origin_stage_type'));
        } else {
            $this->assertNull($candidateNotification);
        }

        if ($behavior === StageBehavior::AwaitingResults) {
            $this->assertTrue(WorkflowStageProgression::passed($entry));
        }
        $this->assertTrue(WorkflowStageMessages::notifyResultStage($entry));
        $this->assertTrue(WorkflowStageMessages::hasSentNotification($entry, $behavior));
        $this->assertFalse(WorkflowStageMessages::notifyResultStage($entry));
        Http::assertSentCount(in_array('sms', $channels, true) ? 2 : 0);
        Mail::assertSentCount(in_array('email', $channels, true) ? 2 : 0);
        $this->assertDatabaseHas('workflow_notification_deliveries', [
            'custom_form_entry_id' => $entry->id, 'user_id' => $this->candidate->id, 'stage_type' => $stageType, 'workflow_event' => null,
        ]);

        if ($behavior === StageBehavior::Completed) {
            WorkflowStageMessages::resetCompletedNotification($entry);
            $this->assertFalse(WorkflowStageMessages::hasSentNotification($entry, $behavior));
        } else {
            $this->assertTrue(WorkflowStageMessages::notifyConfiguredStage($entry, $behavior, replaceExisting: true));
        }
        $this->assertDatabaseHas('workflow_notification_deliveries', ['id' => $customDelivery->id, 'workflow_event' => $event]);
        if ($candidateNotification !== null) {
            $this->assertTrue($this->candidate->notifications()->whereKey($candidateNotification->id)->exists());
        }
        $this->assertTrue($this->recipient->notifications()->whereKey($customStaff->id)->exists());
    }

    public function test_custom_checkpoint_starts_the_following_ams_payment_once_without_changing_business_fields(): void
    {
        config()->set('services.ams', ['enabled' => true, 'base_url' => 'https://ams.example.test', 'token' => 'custom-transition-test', 'timeout' => 5]);
        Http::swap(new HttpFactory);
        Http::fake(fn (HttpRequest $request) => Http::response([
            'application_id' => $request['application_id'], 'ams_request_id' => 'custom-transition-request', 'payment_status' => 'unpaid',
        ], 201));
        $button = $this->button('Continue to AMS payment');
        $type = $this->type('Custom before AMS payment', [$button->id]);
        $submitted = ['type' => 'stage', 'data' => ['stage_name' => 'Deferred submission', 'stage_type' => 'form_submission',
            'notification_channels' => ['system'], 'submission_messages' => ['candidate_status_message' => 'Submission recorded',
                'candidate_notification_message' => 'Submission candidate', 'staff_notification_message' => null, 'staff_recipient_roles' => []]]];
        $payment = ['type' => 'stage', 'data' => ['stage_name' => 'AMS payment', 'stage_type' => 'payment',
            'payment_source' => 'ams', 'responsible_role' => ['admin'], 'notification_channels' => []]];
        [$entry] = $this->entry([$this->customStage($type, 'Continue'), $submitted, $payment, $this->reviewStage()], 'custom-to-ams');
        $before = $this->businessState($entry);
        $snapshot = $entry->getRawOriginal('workflow_snapshot');
        $service = app(WorkflowCustomButtonService::class);
        Http::assertNothingSent();
        $this->assertDatabaseCount('ams_payment_requests', 0);

        $this->assertTrue($service->execute($entry, 0, $button->id));

        $this->assertSame($before, $this->businessState($entry->fresh()));
        $this->assertSame($snapshot, $entry->getRawOriginal('workflow_snapshot'));
        $this->assertSame(StageBehavior::Payment, WorkflowStageProgression::currentType($entry));
        $this->assertNotNull(WorkflowStageProgression::completedAt($entry, 1));
        $this->assertTrue($this->candidate->notifications()->get()->contains(fn ($notification): bool => $notification->data['body'] === 'Submission candidate'));
        $this->assertDatabaseHas('ams_payment_requests', [
            'custom_form_entry_id' => $entry->id, 'ams_request_id' => 'custom-transition-request', 'sync_status' => 'synced', 'attempts' => 1,
        ]);
        $this->assertFalse($service->execute($entry, 0, $button->id));
        $this->assertFalse(WorkflowStageProgression::initialize($entry));
        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://ams.example.test/api/external/candidate/other-fees'
            && $request['application_id'] === (string) $entry->id);
        $this->assertDatabaseCount('ams_payment_requests', 1);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('candidate_id_sequences', 0);
    }

    public function test_custom_checkpoint_does_not_leave_an_already_submitted_checkpoint_stuck(): void
    {
        $button = $this->button('Continue to submission');
        $type = $this->type('Custom before submission', [$button->id]);
        $submitted = ['type' => 'stage', 'data' => ['stage_name' => 'Deferred submission', 'stage_type' => 'form_submission',
            'notification_channels' => ['system'], 'submission_messages' => ['candidate_status_message' => 'Submission recorded',
                'candidate_notification_message' => 'Submission candidate', 'staff_notification_message' => null, 'staff_recipient_roles' => []]]];
        [$entry] = $this->entry([$this->customStage($type, 'Continue'), $submitted, $this->reviewStage()], 'custom-then-submitted');
        $before = $this->businessState($entry);

        $this->assertTrue(app(WorkflowCustomButtonService::class)->execute($entry, 0, $button->id));

        $entry->refresh();
        $this->assertSame($before, $this->businessState($entry));
        $this->assertNotNull(WorkflowStageProgression::completedAt($entry, 0));
        $this->assertNotNull(WorkflowStageProgression::completedAt($entry, 1));
        $this->assertNull(WorkflowStageProgression::completedAt($entry, 2));
        $this->assertSame(StageBehavior::Review, WorkflowStageProgression::currentType($entry));
        $this->assertSame(2, WorkflowStageProgression::currentStage($entry)['_index']);
        $this->assertTrue($this->candidate->notifications()->get()->contains(fn ($notification): bool => $notification->data['body'] === 'Submission candidate'));
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('ams_payment_requests', 0);
        $this->assertDatabaseCount('candidate_id_sequences', 0);
        Http::assertNothingSent();
    }

    public function test_frozen_button_ids_messages_and_unrelated_workflows_are_preserved(): void
    {
        $button = $this->button('Frozen custom button');
        $unselected = $this->button('Not in this snapshot');
        $type = $this->type('Frozen custom stage', [$button->id]);
        [$entry, $workflow] = $this->entry([$this->customStage($type, 'Original')], 'frozen');
        [$unrelated] = $this->entry([$this->reviewStage()], 'unrelated');
        $snapshot = $entry->getRawOriginal('workflow_snapshot');
        $otherBefore = $unrelated->fresh()->getRawOriginal();
        $button->update(['label_en' => 'Changed catalog label', 'is_active' => false]);
        $workflow->update(['stages' => [$this->customStage($type, 'Changed')]]);
        $service = app(WorkflowCustomButtonService::class);
        $this->assertFalse($service->canExecute($entry, 0, $unselected->id, $this->admin));
        $this->assertFalse($service->canExecute($unrelated, 0, $button->id, $this->admin));

        $this->assertTrue($service->execute($entry, 0, $button->id));

        $this->assertMessages('Original candidate', 'Original staff', 0, $button->id);
        $this->assertSame($snapshot, $entry->fresh()->getRawOriginal('workflow_snapshot'));
        $this->assertSame($otherBefore, $unrelated->fresh()->getRawOriginal());
        $this->assertNoExecution($service, $unrelated, 0, $button->id);
        $this->assertSame($otherBefore, $unrelated->fresh()->getRawOriginal());
        $this->assertSame(1, $this->candidate->notifications()->count());
    }

    public function test_roles_candidates_inactive_users_and_form_restrictions_cannot_bypass_authorization(): void
    {
        $button = $this->button('Authorized checkpoint');
        $type = $this->type('Authorized custom stage', [$button->id]);
        $stage = $this->customStage($type, 'Authorized');
        $stage['data']['responsible_role'] = ['custom_handler'];
        [$entry] = $this->entry([$stage], 'authorization');
        $service = app(WorkflowCustomButtonService::class);
        $handler = $this->staff('custom-handler', 'custom_handler');
        $wrongRole = $this->staff('wrong-handler', 'different_role');
        $inactive = $this->staff('inactive-handler', 'custom_handler');
        $inactive->update(['is_active' => false]);
        $before = $entry->getRawOriginal();
        $this->assertTrue($service->canExecute($entry, 0, $button->id, $handler));
        foreach ([$this->candidate, $wrongRole, $inactive] as $actor) {
            $this->assertFalse($service->canExecute($entry, 0, $button->id, $actor));
            $this->actingAs($actor);
            $this->assertNoExecution($service, $entry, 0, $button->id);
            $this->assertSame($before, $entry->fresh()->getRawOriginal());
        }
        Role::findByName('custom_handler', 'web')->forceFill(['restrict_custom_form_access' => true, 'allowed_custom_form_ids' => []])->save();
        $handler = $handler->fresh();
        $this->assertFalse($service->canExecute($entry, 0, $button->id, $handler));
        $this->actingAs($handler);
        $this->assertNoExecution($service, $entry, 0, $button->id);
        $this->assertSame($before, $entry->fresh()->getRawOriginal());
        $this->assertDatabaseCount('notifications', 0);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_missing_responsible_roles_allow_only_an_actual_admin_role(): void
    {
        $button = $this->button('Admin-only checkpoint');
        $type = $this->type('No configured handler', [$button->id]);
        $stage = $this->customStage($type, 'Admin only');
        $stage['data']['responsible_role'] = [];
        [$entry] = $this->entry([$stage], 'admin-only');
        $namedAdminWithoutRole = $this->staff('Admin', 'different_role');
        $service = app(WorkflowCustomButtonService::class);
        $this->assertFalse($service->canExecute($entry, 0, $button->id, $namedAdminWithoutRole));
        $this->assertTrue($service->canExecute($entry, 0, $button->id, $this->admin));
        $this->actingAs($namedAdminWithoutRole);
        $this->assertNoExecution($service, $entry, 0, $button->id);
        $this->actingAs($this->admin);
        $this->assertTrue($service->execute($entry, 0, $button->id));
    }

    private function assertMessages(string $candidateText, string $staffText, int $index, int $buttonId): void
    {
        $candidate = $this->candidate->notifications()->latest()->firstOrFail();
        $staff = $this->recipient->notifications()->latest()->firstOrFail();
        $this->assertSame($candidateText, $candidate->data['body']);
        $this->assertSame($staffText, $staff->data['body']);
        foreach ([$candidate, $staff] as $notification) {
            $this->assertSame($index, (int) data_get($notification->data, 'viewData.workflow_stage_index'));
            $this->assertSame($buttonId, (int) data_get($notification->data, 'viewData.workflow_button_id'));
            $this->assertStringStartsWith('custom_stage:'.$index.':button:'.$buttonId, data_get($notification->data, 'viewData.workflow_event'));
        }
    }

    private function assertNoExecution(WorkflowCustomButtonService $service, CustomFormEntry $entry, int $index, int $buttonId): void
    {
        try {
            $this->assertFalse($service->execute($entry, $index, $buttonId));
        } catch (HttpExceptionInterface $exception) {
            $this->assertContains($exception->getStatusCode(), [403, 409]);
        }
    }

    private function businessState(CustomFormEntry $entry): array
    {
        return [
            'review_status' => $entry->review_status, 'candidate_id' => $entry->candidate_id,
            'custom_form_id' => $entry->custom_form_id, 'created_by' => $entry->created_by,
            'data' => array_diff_key($entry->data, array_flip(['workflow_progress', 'workflow_custom_button_events', 'workflow_custom_status'])),
        ];
    }

    private function customStage(WorkflowStageType $type, string $name): array
    {
        return ['type' => 'stage', 'data' => [
            'stage_name' => $name, 'stage_type_id' => $type->id, 'stage_type' => $type->behavior,
            'responsible_role' => ['admin'], 'notification_channels' => ['system'],
            'alert_message' => $name.' alert',
            'submission_messages' => ['candidate_status_message' => $name.' recorded', 'candidate_notification_message' => $name.' candidate',
                'staff_notification_message' => $name.' staff', 'staff_recipient_roles' => ['custom_receiver']],
        ]];
    }

    private function reviewStage(): array
    {
        return ['type' => 'stage', 'data' => ['stage_name' => 'Review', 'stage_type' => 'review', 'responsible_role' => ['admin'], 'enabled_actions' => ['accept'], 'notification_channels' => []]];
    }

    private function entry(array $stages, string $suffix): array
    {
        $workflow = WorkflowNotification::query()->create(['name' => 'Custom button workflow '.$suffix, 'stages' => $stages]);
        $form = CustomForm::query()->create(['name' => 'Custom button form '.$suffix, 'slug' => 'custom-button-'.$suffix, 'is_active' => true]);
        $workflow->forms()->attach($form);
        $entry = CustomFormEntry::query()->forceCreate(['custom_form_id' => $form->id, 'created_by' => $this->candidate->id, 'review_status' => 'pending',
            'data' => ['registration_status' => 'pending', 'candidate_status' => 'pending', 'retained_value' => 'keep', 'submitted_at' => now()->toIso8601String()]])->fresh();

        return [$entry, $workflow, $form];
    }

    private function button(string $label, ?string $action = null): WorkflowButton
    {
        return WorkflowButton::query()->create(['label_en' => $label, 'action_key' => $action]);
    }

    private function type(string $label, array $ids): WorkflowStageType
    {
        return WorkflowStageType::query()->create(['label_en' => $label, 'label_km' => 'ប្រភេទ '.$label, 'workflow_button_ids' => $ids]);
    }

    private function staff(string $name, string $role): User
    {
        $user = $this->user($name, 'admin', $role);
        foreach (['ViewAny:CustomFormEntry', 'View:CustomFormEntry'] as $permission) {
            $user->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        return $user->fresh();
    }

    private function user(string $name, string $type, ?string $role = null): User
    {
        $user = User::query()->create(['registration_type' => $type, 'name' => $name, 'username' => $name, 'email' => $name.'@example.test', 'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true]);
        if ($role !== null) {
            $user->assignRole(Role::findOrCreate($role, 'web'));
        }

        return $user->fresh();
    }
}
