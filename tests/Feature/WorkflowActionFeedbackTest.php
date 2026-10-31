<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\CandidateEntranceStatistics\Pages\ListCandidateEntranceStatistics;
use App\Filament\Admin\Resources\ExamResults\Pages\ListExamResults;
use App\Filament\Admin\Resources\Payments\Pages\CreatePayment;
use App\Filament\Admin\Resources\PaymentStatusLists\Pages\CreatePaymentStatusList;
use App\Filament\Admin\Resources\PaymentStatusLists\Pages\ListPaymentStatusLists;
use App\Models\MefInvoiceCode;
use App\Models\Payment;
use App\Models\PaymentStatusList;
use App\Models\PaymentType;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Support\PassedResultMenuOptions;
use App\Support\StatisticsMenuOptions;
use App\Support\WorkflowActionFeedback;
use App\Support\WorkflowStageMessages;
use App\Support\WorkflowStageProgression;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\CreateCustomFormEntry;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\EditCustomFormEntry;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\ListCustomFormEntries;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WorkflowActionFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private User $candidate;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        $this->staff = $this->user('admin');
        $role = Role::findOrCreate('admin', 'web');
        foreach ([
            'ViewAny:CustomFormEntry', 'View:CustomFormEntry', 'Accepted:CustomFormEntry', 'Rejected:CustomFormEntry', 'Reject:CustomFormEntry', 'ViewPdf:CustomFormEntry',
            'ViewAny:PaymentStatusList', 'Pay:PaymentStatusList',
            'ViewAny:CandidateEntranceStatistic', 'Passed:CandidateEntranceStatistic',
            'ViewAny:ExamResult', 'NotifyStudent:ExamResult',
        ] as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $this->staff->assignRole($role);
        $this->candidate = $this->user('student');
        $this->actingAs($this->staff);
    }

    public static function actionLabels(): array
    {
        return [
            ['submission', 'Alert: បានដាក់ពាក្យ'], ['accept', 'Alert: បានពិនិត្យពាក្យ'],
            ['send_back', 'Alert: បញ្ជូនត្រឡប់កែពាក្យ'], ['reject', 'Alert: បានបដិសេធ'],
            ['payment', 'Alert: បានបង់ប្រាក់រួចរាល់'], ['passed', 'Alert: បានជាប់'],
            ['pending', 'Alert: កំពុងរង់ចាំ'], ['completed', 'Alert: ជោគជ័យ'],
        ];
    }

    #[DataProvider('actionLabels')]
    public function test_resolver_uses_the_separate_frozen_action_alert_without_mutating_status_or_delivering_messages(string $event, string $expected): void
    {
        [$form, $workflow] = $this->workflow();
        $entry = $this->entry($form);
        $before = $entry->getAttributes();
        $stages = $workflow->stages;
        array_walk_recursive($stages, static function (&$value): void {
            if (is_string($value) && ! in_array($value, ['stage', 'form_submission', 'review', 'payment', 'awaiting_results', 'completed', 'admin', 'system'], true)) {
                $value .= ' edited';
            }
        });
        $workflow->update(['stages' => $stages]);

        $this->assertSame($expected, WorkflowActionFeedback::title($entry, $event, 'Original toast'));
        $this->assertSame($before, $entry->fresh()->getAttributes());
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_blank_configured_alerts_are_silent_while_static_profile_and_draft_keep_the_existing_toast(): void
    {
        [$form, $workflow] = $this->workflow();
        $stages = $workflow->stages;
        array_walk_recursive($stages, static function (&$value, $key): void {
            if (str_ends_with((string) $key, 'alert_message')) {
                $value = ' ';
            }
        });
        $workflow->update(['stages' => $stages]);
        $blank = $this->entry($form);
        $static = $this->entry($this->form());
        [$configured] = $this->workflow();
        $draft = $this->entry($configured, 'draft');
        $profile = $this->form(['slug' => 'profile']);
        $workflow->forms()->attach($profile);
        $profileEntry = $this->entry($profile);

        foreach ([$static, $draft, $profileEntry] as $entry) {
            foreach (self::actionLabels() as [$event]) {
                $this->assertSame('Original toast', WorkflowActionFeedback::title($entry, $event, 'Original toast'));
            }
        }
        foreach (self::actionLabels() as [$event]) {
            $this->assertNull(WorkflowActionFeedback::title($blank, $event, 'Original toast'));
        }
        $this->assertNull(WorkflowActionFeedback::title($blank, 'unknown', 'Unknown toast'));
    }

    public function test_old_entries_keep_old_feedback_and_new_entries_use_the_edited_action_label(): void
    {
        [$form, $workflow] = $this->workflow();
        $old = $this->entry($form);
        $stages = $workflow->stages;
        $stages[1]['data']['review_actions']['accept']['status_message'] = 'បានពិនិត្យពាក្យថ្មី';
        $stages[1]['data']['review_actions']['accept']['alert_message'] = 'New checked alert';
        $workflow->update(['stages' => $stages]);
        $new = $this->entry($form);

        $this->assertSame('Alert: បានពិនិត្យពាក្យ', WorkflowActionFeedback::title($old, 'accept', 'Fallback'));
        $this->assertSame('New checked alert', WorkflowActionFeedback::title($new, 'accept', 'Fallback'));
        $this->assertSame('បានពិនិត្យពាក្យ', data_get($old->fresh()->workflow_snapshot, 'stages.1.data.review_actions.accept.status_message'));
        $this->assertSame('បានពិនិត្យពាក្យថ្មី', data_get($new->workflow_snapshot, 'stages.1.data.review_actions.accept.status_message'));
        $workflow->forms()->detach($form);
        $this->assertSame('Alert: បានពិនិត្យពាក្យ', WorkflowActionFeedback::title($old->fresh(), 'accept', 'Fallback'));
    }

    public function test_old_snapshots_without_alerts_stay_silent_even_after_alerts_are_added_to_the_template(): void
    {
        [$form, $workflow] = $this->workflow();
        $withAlerts = $workflow->stages;
        $withoutAlerts = $withAlerts;
        foreach ($withoutAlerts as &$stage) {
            unset($stage['data']['alert_message'], $stage['data']['result_statuses']['pending_alert_message']);
            foreach (['accept', 'send_back', 'reject'] as $action) {
                unset($stage['data']['review_actions'][$action]['alert_message']);
            }
        }
        unset($stage);
        $workflow->update(['stages' => $withoutAlerts]);
        $old = $this->entry($form);
        $workflow->update(['stages' => $withAlerts]);
        $new = $this->entry($form);

        foreach (self::actionLabels() as [$event, $alert]) {
            $this->assertNull(WorkflowActionFeedback::title($old->fresh(), $event, 'Original toast'));
            $this->assertSame($alert, WorkflowActionFeedback::title($new, $event, 'Original toast'));
        }
        $this->assertSame('បានពិនិត្យពាក្យ', data_get($old->fresh()->workflow_snapshot, 'stages.1.data.review_actions.accept.status_message'));
        $this->assertArrayNotHasKey('alert_message', $old->fresh()->workflow_snapshot['stages'][0]['data']);
    }

    public function test_wrapped_and_flat_stage_normalization_keeps_alert_status_and_notification_values_independent(): void
    {
        $stages = [
            ['type' => 'stage', 'data' => [
                'stage_type' => 'payment', 'status_message' => 'Paid status',
                'alert_message' => '  Payment action alert  ', 'notification_message' => 'Candidate payment notification',
            ]],
            [
                'stage_type' => 'review', 'status_message' => 'Review status',
                'review_actions' => [
                    'accept' => ['status_message' => 'Checked status', 'alert_message' => '  Checked action alert  ', 'notification_message' => 'Checked candidate message'],
                    'send_back' => ['status_message' => 'Correction status', 'alert_message' => '  '],
                    'reject' => ['status_message' => 'Rejected status'],
                ],
            ],
            ['type' => 'stage', 'data' => [
                'stage_type' => 'awaiting_results', 'status_message' => 'Passed status', 'alert_message' => ' Result action alert ',
                'result_statuses' => ['pending_status_message' => 'Waiting status', 'pending_alert_message' => ' Waiting action alert '],
            ]],
        ];
        $normalized = WorkflowNotification::normalizeStages($stages);
        $this->assertSame('Payment action alert', data_get($normalized, '0.data.alert_message'));
        $this->assertSame('Paid status', data_get($normalized, '0.data.status_message'));
        $this->assertSame('Candidate payment notification', data_get($normalized, '0.data.notification_message'));
        $this->assertSame('Checked action alert', data_get($normalized, '1.review_actions.accept.alert_message'));
        $this->assertSame('Checked status', data_get($normalized, '1.review_actions.accept.status_message'));
        $this->assertSame('Checked candidate message', data_get($normalized, '1.review_actions.accept.notification_message'));
        $this->assertNull(data_get($normalized, '1.review_actions.send_back.alert_message'));
        $this->assertArrayNotHasKey('alert_message', $normalized[1]['review_actions']['reject']);
        $this->assertSame('Result action alert', data_get($normalized, '2.data.alert_message'));
        $this->assertSame('Waiting action alert', data_get($normalized, '2.data.result_statuses.pending_alert_message'));
        $this->assertSame('Waiting status', data_get($normalized, '2.data.result_statuses.pending_status_message'));

        $workflow = WorkflowNotification::query()->create(['name' => 'Independent alert normalization', 'stages' => $stages]);
        $this->assertSame($normalized, $workflow->fresh()->stages);
    }

    public function test_absent_review_alert_does_not_borrow_status_and_ordered_final_feedback_uses_the_correct_stage(): void
    {
        [$form, $workflow] = $this->workflow([WorkflowStageType::Review, WorkflowStageType::Payment]);
        $stages = $workflow->stages;
        unset($stages[0]['data']['review_actions']);
        $stages[0]['data']['status_message'] = 'Generic review status';
        $stages[0]['data']['alert_message'] = 'Unrelated generic review alert';
        $workflow->update(['stages' => $stages]);
        $entry = $this->entry($form);
        $this->assertNull(WorkflowActionFeedback::title($entry, 'accept', 'Fallback'));
        $this->assertNull(WorkflowActionFeedback::title($entry, 'send_back', 'Fallback'));
        $this->assertSame('Alert: បានបង់ប្រាក់រួចរាល់', WorkflowActionFeedback::title($entry, 'completed', 'Fallback'));

        [$resultForm] = $this->workflow([WorkflowStageType::Review, WorkflowStageType::AwaitingResults]);
        $this->assertSame('Alert: បានជាប់', WorkflowActionFeedback::title($this->entry($resultForm), 'completed', 'Fallback'));
        [$completedForm] = $this->workflow([WorkflowStageType::Review, WorkflowStageType::Completed]);
        $this->assertSame('Alert: ជោគជ័យ', WorkflowActionFeedback::title($this->entry($completedForm), 'passed', 'Fallback'));
    }

    public function test_bulk_feedback_skips_silent_entries_and_keeps_generic_feedback_for_mixed_non_silent_entries(): void
    {
        [$form, $workflow] = $this->workflow();
        $first = $this->entry($form);
        $second = $this->entry($form);
        $fallback = 'Updated 2 applications';
        $this->assertSame('Alert: បានជាប់', WorkflowActionFeedback::bulkTitle(collect([$first, $second]), 'passed', $fallback));

        $stages = $workflow->stages;
        $stages[3]['data']['alert_message'] = 'Different passed alert';
        $workflow->update(['stages' => $stages]);
        $different = $this->entry($form);
        $this->assertSame($fallback, WorkflowActionFeedback::bulkTitle([$first, $different], 'passed', $fallback));

        $stages[3]['data']['alert_message'] = ' ';
        $workflow->update(['stages' => $stages]);
        $blank = $this->entry($form);
        $static = $this->entry($this->form());
        $this->assertSame('Alert: បានជាប់', WorkflowActionFeedback::bulkTitle([$first, $blank], 'passed', $fallback));
        $this->assertSame('Alert: បានជាប់', WorkflowActionFeedback::bulkTitle([$blank, $first, $second], 'passed', $fallback));
        $this->assertNull(WorkflowActionFeedback::bulkTitle([$blank], 'passed', $fallback));
        $this->assertNull(WorkflowActionFeedback::bulkTitle([$blank, $blank], 'passed', $fallback));
        $this->assertSame($fallback, WorkflowActionFeedback::bulkTitle([$blank, $static], 'passed', $fallback));
        $this->assertSame($fallback, WorkflowActionFeedback::bulkTitle([$first, $static], 'passed', $fallback));
        $this->assertSame($fallback, WorkflowActionFeedback::bulkTitle([], 'passed', $fallback));
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public static function alertModes(): array
    {
        return [[true], [false]];
    }

    #[DataProvider('alertModes')]
    public function test_candidate_done_obeys_its_alert_setting_and_save_draft_keeps_its_own_toast(bool $alerts): void
    {
        [$form] = $this->workflow(alerts: $alerts);
        $this->authorizeCandidate($form);
        $this->actingAs($this->candidate);
        $component = $this->candidateCreate($form)->set('data.data.full_name', 'Submitted candidate')
            ->callAction(TestAction::make('submit')->schemaComponent('form-actions', schema: 'content'))
            ->assertHasNoFormErrors();
        $this->assertFeedback($component, $alerts ? 'Alert: បានដាក់ពាក្យ' : null);
        $entry = CustomFormEntry::query()->where('custom_form_id', $form->id)->sole();
        $this->assertSame('pending', $entry->review_status);
        $this->assertSame(WorkflowStageType::Review, WorkflowStageProgression::currentType($entry));
        $this->assertNotEmpty(data_get($entry->data, 'submitted_at'));

        [$draftForm] = $this->workflow(alerts: $alerts);
        $this->authorizeCandidate($draftForm);
        $this->candidateCreate($draftForm)->set('data.data.full_name', 'Draft candidate')
            ->callAction('save_draft')->assertNotified(__('student_profile.draft_saved'));
        $draft = CustomFormEntry::query()->where('custom_form_id', $draftForm->id)->sole();
        $this->assertSame('draft', $draft->review_status);
        $this->assertSame(__('student_profile.draft_saved'), WorkflowActionFeedback::title($draft, 'submission', __('student_profile.draft_saved')));

        $component = $this->candidateCreate($draftForm, $draft)->set('data.data.full_name', 'Draft submitted')
            ->callAction(TestAction::make('submit')->schemaComponent('form-actions', schema: 'content'))
            ->assertHasNoFormErrors();
        $this->assertFeedback($component, $alerts ? 'Alert: បានដាក់ពាក្យ' : null);
        $this->assertSame(1, CustomFormEntry::query()->where('custom_form_id', $draftForm->id)->count());
        $this->assertSame('pending', $draft->fresh()->review_status);
        $this->assertSame('Draft submitted', data_get($draft->fresh()->data, 'full_name'));
        $this->assertSame(WorkflowStageType::Review, WorkflowStageProgression::currentType($draft->fresh()));
    }

    public static function inlineReviewActions(): array
    {
        $cases = [];
        foreach ([true, false] as $alerts) {
            foreach ([['accepted', 'approved', 'Alert: បានពិនិត្យពាក្យ'], ['rejected', 'rejected', 'Alert: បញ្ជូនត្រឡប់កែពាក្យ'], ['reject', 'failed', 'Alert: បានបដិសេធ']] as $case) {
                $cases[] = [...$case, $alerts];
            }
        }

        return $cases;
    }

    #[DataProvider('inlineReviewActions')]
    public function test_inline_review_toast_obeys_the_action_alert_and_preserves_its_existing_transition(string $action, string $status, string $label, bool $alerts): void
    {
        [$form] = $this->workflow(alerts: $alerts);
        $entry = $this->entry($form);
        DocumentTemplate::query()->where('type', 'custom_form_'.$form->id)->delete();
        $component = Livewire::test(ListCustomFormEntries::class);
        $action === 'accepted'
            ? $component->callTableAction($action, $entry)
            : $component->callTableAction($action, $entry, data: ['review_note' => 'Please correct the document.']);
        $component->assertHasNoTableActionErrors();
        $this->assertFeedback($component, $alerts ? $label : null);

        $this->assertSame($status, $entry->fresh()->review_status);
        $this->assertSame($status, data_get($entry->fresh()->data, 'registration_status'));
        $this->assertSame($action === 'accepted' ? WorkflowStageType::Payment : null, WorkflowStageProgression::currentType($entry->fresh()));
        $this->assertSame($action === 'accepted', data_get($entry->fresh()->data, 'workflow_progress.reviewed'));
        $this->assertSame($action === 'accepted' ? __('payments.options.status_payt.unpaid') : str_replace('Alert: ', '', $label), WorkflowStageMessages::statusText($entry->fresh()));
        $event = match ($action) {
            'accepted' => 'accept', 'rejected' => 'send_back', default => 'reject',
        };
        $this->assertSame(str_replace('Alert: ', '', $label), data_get($entry->fresh()->workflow_snapshot, 'stages.1.data.review_actions.'.$event.'.status_message'));
        $this->assertDatabaseCount('payments', 0);
    }

    public static function pdfReviewActions(): array
    {
        return [
            ['approve', 'approved', 'Alert: បានពិនិត្យពាក្យ', true], ['reject', 'rejected', 'Alert: បញ្ជូនត្រឡប់កែពាក្យ', true],
            ['approve', 'approved', 'Alert: បានពិនិត្យពាក្យ', false], ['reject', 'rejected', 'Alert: បញ្ជូនត្រឡប់កែពាក្យ', false],
        ];
    }

    #[DataProvider('pdfReviewActions')]
    public function test_pdf_review_redirect_obeys_the_configured_action_alert(string $action, string $status, string $label, bool $alerts): void
    {
        [$form] = $this->workflow(alerts: $alerts);
        $entry = $this->entry($form);
        $this->post(route('admin.custom-form-entries.'.$action, $entry), ['review_note' => 'Please correct the document.'])->assertRedirect();
        $alerts ? Notification::assertNotified($label) : Notification::assertNotNotified();
        $this->assertSame($status, $entry->fresh()->review_status);
        $this->assertDatabaseCount('payments', 0);
    }

    #[DataProvider('alertModes')]
    public function test_payment_toast_obeys_its_alert_after_progress_advances_and_paid_status_stays_separate(bool $alerts): void
    {
        [$form] = $this->workflow([WorkflowStageType::Payment, WorkflowStageType::AwaitingResults, WorkflowStageType::Completed], $alerts);
        $entry = $this->entry($form);
        $record = PaymentStatusList::query()->findOrFail($entry->id);
        MefInvoiceCode::query()->create(['mef_code' => 'MEF1', 'invoice_code' => '120--FEEDBACK']);
        PaymentType::query()->create(['key' => 'cash', 'name_en' => 'Cash', 'name_kh' => 'សាច់ប្រាក់', 'method' => 'cash', 'is_active' => true]);

        $component = Livewire::test(ListPaymentStatusLists::class)->callTableAction('pay', $record, data: [
            'receipt_number' => 'MEF1', 'payment_method' => 'cash', 'amount_kh' => '1000', 'datetime_pay' => now()->toDateString(),
        ])->assertHasNoTableActionErrors();
        $this->assertFeedback($component, $alerts ? 'Alert: បានបង់ប្រាក់រួចរាល់' : null);

        $this->assertSame('paid', Payment::query()->sole()->status_payt);
        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageProgression::currentType($entry->fresh()));
        $this->assertSame('បានបង់ប្រាក់រួចរាល់', WorkflowStageMessages::paymentStatusLabel($entry->fresh(), 'paid'));
        $this->assertFalse(WorkflowStageProgression::readyForFinal($entry->fresh()));
    }

    #[DataProvider('alertModes')]
    public function test_both_admin_payment_create_pages_obey_the_exact_linked_entry_alert_and_keep_unlinked_fallbacks(bool $alerts): void
    {
        [$form] = $this->workflow([WorkflowStageType::Payment, WorkflowStageType::Completed], $alerts);
        $entry = $this->entry($form);
        $linked = Payment::query()->forceCreate([
            'custom_form_entry_id' => $entry->id, 'users_id' => $this->candidate->id, 'form_id' => $form->id,
            'receipt_number' => 'FEEDBACK-LINKED', 'status_payt' => 'paid', 'amount_usd' => 10, 'datetime_pay' => now(),
        ]);
        $unlinked = Payment::query()->forceCreate([
            'users_id' => $this->candidate->id, 'form_id' => $form->id,
            'receipt_number' => 'FEEDBACK-UNLINKED', 'status_payt' => 'paid', 'amount_usd' => 10, 'datetime_pay' => now(),
        ]);
        foreach ([CreatePayment::class, CreatePaymentStatusList::class] as $class) {
            $page = app($class);
            $title = new ReflectionMethod($class, 'getCreatedNotificationTitle');
            $fallback = $title->invoke($page);
            $this->assertNotEmpty($fallback);
            $page->record = $linked;
            $this->assertSame($alerts ? 'Alert: បានបង់ប្រាក់រួចរាល់' : null, $title->invoke($page));
            $notification = (new ReflectionMethod($class, 'getCreatedNotification'))->invoke($page);
            if ($alerts) {
                $this->assertInstanceOf(Notification::class, $notification);
                $notification->send();
                Notification::assertNotified('Alert: បានបង់ប្រាក់រួចរាល់');
            } else {
                $this->assertNull($notification);
                $notification?->send();
                Notification::assertNotNotified();
            }
            $page->record = $unlinked;
            $this->assertSame($fallback, $title->invoke($page));
        }
        $this->assertDatabaseCount('payments', 2);
    }

    #[DataProvider('alertModes')]
    public function test_result_and_manual_final_notification_toasts_each_obey_their_own_alert(bool $alerts): void
    {
        [$form] = $this->workflow([WorkflowStageType::AwaitingResults, WorkflowStageType::Completed], $alerts);
        $entry = $this->entry($form);
        $component = Livewire::test(ListCandidateEntranceStatistics::class)->callTableAction('passed', $entry)
            ->assertHasNoTableActionErrors();
        $this->assertFeedback($component, $alerts ? 'Alert: បានជាប់' : null);
        $this->assertSame(WorkflowStageType::Completed, WorkflowStageProgression::currentType($entry->fresh()));
        $this->assertTrue(WorkflowStageProgression::readyForFinal($entry->fresh()));
        $this->assertFalse(WorkflowStageProgression::finished($entry->fresh()));

        $component = Livewire::test(ListExamResults::class)->callTableAction('notify_student', $entry)
            ->assertHasNoTableActionErrors();
        $this->assertFeedback($component, $alerts ? 'Alert: ជោគជ័យ' : null);
        $this->assertTrue(WorkflowStageProgression::finished($entry->fresh()));
        $this->assertSame('ជោគជ័យ', WorkflowStageMessages::statusText($entry->fresh()));
        $this->assertTrue(WorkflowStageMessages::hasSentNotification($entry->fresh(), WorkflowStageType::Completed));
        $this->assertGreaterThanOrEqual(2, $this->candidate->notifications()->count());
    }

    public function test_blank_submission_alert_suppresses_only_the_popup_and_keeps_candidate_and_staff_bells(): void
    {
        [$form, $workflow] = $this->workflow(alerts: false);
        $stages = $workflow->stages;
        $stages[0]['data']['submission_messages'] = [
            ...$stages[0]['data']['submission_messages'],
            'candidate_notification_message' => 'Candidate application submitted',
            'staff_notification_message' => 'Staff application submitted',
            'staff_recipient_roles' => ['admin'],
        ];
        $workflow->update(['stages' => $stages]);
        $this->authorizeCandidate($form);
        $this->actingAs($this->candidate);
        $this->candidateCreate($form)->set('data.data.full_name', 'Candidate with silent alert')
            ->callAction(TestAction::make('submit')->schemaComponent('form-actions', schema: 'content'))
            ->assertHasNoFormErrors()->assertNotNotified();

        $entry = CustomFormEntry::query()->where('custom_form_id', $form->id)->sole();
        $this->assertSame(WorkflowStageType::Review, WorkflowStageProgression::currentType($entry));
        $this->assertSame('Candidate application submitted', $this->candidate->notifications()->sole()->data['body']);
        $this->assertSame('Staff application submitted', $this->staff->notifications()->sole()->data['body']);
        $this->assertSame('បានដាក់ពាក្យ', data_get($entry->workflow_snapshot, 'stages.0.data.submission_messages.candidate_status_message'));
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_blank_submission_alert_stays_silent_when_a_candidate_resubmits_a_returned_application(): void
    {
        [$form] = $this->workflow(alerts: false);
        $this->authorizeCandidate($form);
        $this->actingAs($this->candidate);
        $this->candidateCreate($form)->set('data.data.full_name', 'Original answer')
            ->callAction(TestAction::make('submit')->schemaComponent('form-actions', schema: 'content'))
            ->assertHasNoFormErrors()->assertNotNotified();
        $entry = CustomFormEntry::query()->where('custom_form_id', $form->id)->sole();
        $snapshot = $entry->workflow_snapshot;
        $this->actingAs($this->staff)->post(route('admin.custom-form-entries.reject', $entry), ['review_note' => 'Please correct the name'])->assertRedirect();
        Notification::assertNotNotified();
        $this->assertSame('rejected', $entry->fresh()->review_status);

        Livewire::actingAs($this->candidate)->test(EditCustomFormEntry::class, ['record' => $entry->id])
            ->set('data.data.full_name', 'Corrected answer')
            ->callAction(TestAction::make('save')->schemaComponent('form-actions', schema: 'content'))
            ->assertHasNoFormErrors()->assertNotNotified();

        $this->assertSame('Corrected answer', data_get($entry->fresh()->data, 'full_name'));
        $this->assertSame('pending', $entry->fresh()->review_status);
        $this->assertSame(WorkflowStageType::Review, WorkflowStageProgression::currentType($entry->fresh()));
        $this->assertSame($snapshot, $entry->fresh()->workflow_snapshot);
        $this->assertSame(1, CustomFormEntry::query()->where('custom_form_id', $form->id)->count());
    }

    public function test_nullable_feedback_sender_sends_only_non_empty_titles(): void
    {
        WorkflowActionFeedback::send(Notification::make()->success()->body('No immediate alert'), null);
        Notification::assertNotNotified();
        WorkflowActionFeedback::send(Notification::make()->success()->body('Configured immediate alert'), 'Configured alert');
        Notification::assertNotified('Configured alert');
    }

    public function test_static_review_action_keeps_the_existing_success_toast(): void
    {
        $form = $this->form(['requires_payment' => true]);
        $entry = $this->entry($form);
        DocumentTemplate::query()->where('type', 'custom_form_'.$form->id)->delete();
        Livewire::test(ListCustomFormEntries::class)->callTableAction('accepted', $entry)
            ->assertHasNoTableActionErrors()
            ->assertNotified(__('candidate_entrance_statistics.notifications.admin_accept_success_title'));
        $this->assertSame('approved', $entry->fresh()->review_status);
    }

    private function workflow(?array $order = null, bool $alerts = true): array
    {
        $order ??= [WorkflowStageType::FormSubmission, WorkflowStageType::Review, WorkflowStageType::Payment, WorkflowStageType::AwaitingResults, WorkflowStageType::Completed];
        $form = $this->form();
        $stages = array_map(static fn (WorkflowStageType $type): array => ['type' => 'stage', 'data' => [
            'stage_type' => $type->value, 'stage_name' => $type->label(), 'responsible_role' => ['admin'],
            'payment_source' => 'system', 'notification_channels' => ['system'],
            'status_message' => match ($type) {
                WorkflowStageType::Payment => 'បានបង់ប្រាក់រួចរាល់', WorkflowStageType::AwaitingResults => 'បានជាប់',
                WorkflowStageType::Completed => 'ជោគជ័យ', default => 'Generic status',
            },
            'alert_message' => match ($type) {
                WorkflowStageType::FormSubmission => 'Alert: បានដាក់ពាក្យ',
                WorkflowStageType::Payment => 'Alert: បានបង់ប្រាក់រួចរាល់',
                WorkflowStageType::AwaitingResults => 'Alert: បានជាប់',
                WorkflowStageType::Completed => 'Alert: ជោគជ័យ', default => 'Unused generic alert',
            },
            'notification_message' => 'Notification body for '.$type->value,
            'submission_messages' => ['candidate_status_message' => 'បានដាក់ពាក្យ'],
            'review_actions' => [
                'accept' => ['status_message' => 'បានពិនិត្យពាក្យ', 'alert_message' => 'Alert: បានពិនិត្យពាក្យ'],
                'send_back' => ['status_message' => 'បញ្ជូនត្រឡប់កែពាក្យ', 'alert_message' => 'Alert: បញ្ជូនត្រឡប់កែពាក្យ'],
                'reject' => ['status_message' => 'បានបដិសេធ', 'alert_message' => 'Alert: បានបដិសេធ'],
            ],
            'result_statuses' => ['pending_status_message' => 'កំពុងរង់ចាំ', 'pending_alert_message' => 'Alert: កំពុងរង់ចាំ'],
        ]], $order);
        if (! $alerts) {
            array_walk_recursive($stages, static function (&$value, $key): void {
                if (str_ends_with((string) $key, 'alert_message')) {
                    $value = ' ';
                }
            });
        }
        $workflow = WorkflowNotification::query()->create(['name' => 'Action feedback', 'stages' => $stages]);
        $workflow->forms()->attach($form);

        return [$form, $workflow];
    }

    private function assertFeedback($component, ?string $title): void
    {
        $title === null ? $component->assertNotNotified() : $component->assertNotified($title);
    }

    private function form(array $overrides = []): CustomForm
    {
        return CustomForm::query()->create([...[
            'name' => 'Feedback form', 'slug' => 'feedback-'.uniqid(), 'is_active' => true,
            'allowed_roles' => ['candidate', 'feedback_applicant', 'admin'],
            'statistics_menu' => StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS,
            'passed_result_menu' => PassedResultMenuOptions::EXAM_RESULTS,
        ], ...$overrides]);
    }

    private function entry(CustomForm $form, string $status = 'pending'): CustomFormEntry
    {
        return CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $form->id, 'created_by' => $this->candidate->id, 'review_status' => $status,
            'data' => ['registration_status' => $status, 'candidate_status' => 'pending'],
        ]);
    }

    private function authorizeCandidate(CustomForm $form): void
    {
        $role = Role::findOrCreate('candidate', 'web');
        foreach (['ViewAny', 'View', 'Create', 'Update'] as $action) {
            $role->givePermissionTo(Permission::findOrCreate($action.':CustomFormEntry', 'web'));
        }
        $this->candidate->assignRole($role);
        $this->candidate->assignRole(Role::findOrCreate('feedback_applicant', 'web'));
        if (! CustomForm::query()->where('slug', 'profile')->exists()) {
            $profile = $this->form(['slug' => 'profile']);
            $this->entry($profile);
        }
        $form->fields()->create(['name' => 'full_name', 'label' => 'Full name', 'type' => 'text_input', 'required' => true]);
    }

    private function candidateCreate(CustomForm $form, ?CustomFormEntry $draft = null)
    {
        $parameters = ['form_id' => (string) $form->id];
        if ($draft !== null) {
            $parameters['draft_id'] = (string) $draft->id;
        }

        return Livewire::withQueryParams($parameters)->test(CreateCustomFormEntry::class, $parameters);
    }

    private function user(string $type): User
    {
        $name = 'action_feedback_'.$type.'_'.uniqid();

        return User::query()->forceCreate([
            'registration_type' => $type, 'name' => $name, 'username' => $name,
            'email' => $name.'@example.test', 'password' => 'password', 'is_active' => true, 'date_of_birth' => '2000-01-01',
        ]);
    }
}
