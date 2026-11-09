<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\CandidateEntranceStatistics\Pages\ListCandidateEntranceStatistics;
use App\Filament\Admin\Resources\EmailTemplates\Pages\CreateEmailTemplate;
use App\Filament\Admin\Resources\EmailTemplates\Pages\EditEmailTemplate;
use App\Filament\Admin\Resources\ExamResults\Pages\ListExamResults;
use App\Filament\Admin\Resources\SmsTemplates\Pages\CreateSmsTemplate;
use App\Filament\Admin\Resources\SmsTemplates\Pages\EditSmsTemplate;
use App\Filament\Admin\Resources\WorkflowStageTypes\Pages\EditWorkflowStageType;
use App\Models\EmailTemplate;
use App\Models\Major;
use App\Models\Role;
use App\Models\SmsTemplate;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Models\WorkflowStageType as StageTypeCatalog;
use App\Services\CandidatePaymentRecorder;
use App\Services\WorkflowCandidateVerifier;
use App\Support\FilamentActionPermissions;
use App\Support\PassedResultMenuOptions;
use App\Support\StatisticsMenuOptions;
use App\Support\WorkflowStageMessages;
use App\Support\WorkflowStageProgression;
use App\Support\WorkflowSubmissionMessages;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Pages\ListCustomFormEntries;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class WorkflowActionEmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    private const ACTIONS = ['form_submission', 'accept', 'send_back', 'reject', 'verification', 'payment', 'awaiting_results', 'completed'];

    private const STAGE_NAMES = ['form_submission' => 'Submit checkpoint', 'review' => 'Review checkpoint', 'verification' => 'Receipt checkpoint', 'payment' => 'Payment checkpoint', 'awaiting_results' => 'Results checkpoint', 'completed' => 'Final checkpoint'];

    private User $candidate;

    private Major $major;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        config(['services.ams.enabled' => false]);
        Http::preventStrayRequests();
        Http::fake();
        Mail::fake();
        $admin = $this->user('Template administrator', 'admin');
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
        $this->candidate = $this->user('Template Candidate', 'student');
        $this->major = Major::query()->create(['key' => 'email-nurse', 'abbreviation' => 'BNM', 'name_en' => 'Nurse Midwifery', 'is_active' => true]);
    }

    public static function templateModes(): array
    {
        return ['Used For default' => [false], 'Explicit stage template' => [true]];
    }

    #[DataProvider('templateModes')]
    public function test_all_six_real_workflow_stages_use_the_action_template_or_explicit_stage_override_once(bool $explicit): void
    {
        $this->defaultTemplates();
        if ($explicit) {
            $this->template('explicit', null);
        }
        [$form] = $this->workflow($explicit ? 'explicit' : null);
        $this->useArrayMail();
        $entry = $this->entry($form);
        $snapshot = $entry->getRawOriginal('workflow_snapshot');
        $this->assertSame(WorkflowStageType::Review, WorkflowStageProgression::currentType($entry));
        $this->assertLastEmail('form_submission', $explicit, 1);

        $this->check($entry);
        $this->assertSame(WorkflowStageType::Verification, WorkflowStageProgression::currentType($entry));
        $this->assertLastEmail('accept', $explicit, 2);
        $this->assertNull($entry->candidate_id);
        Livewire::test(ListCustomFormEntries::class)->callTableAction('verify', $entry)->assertHasNoTableActionErrors();
        $this->assertLastEmail('verification', $explicit, 3);
        $this->assertSame('BNM-0001', $entry->fresh()->candidate_id);

        app(CandidatePaymentRecorder::class)->record(['custom_form_entry_id' => $entry->id, 'receipt_number' => 'EMAIL-'.$entry->id, 'amount_usd' => 10, 'datetime_pay' => now()]);
        $this->assertLastEmail('payment', $explicit, 4);
        Livewire::test(ListCandidateEntranceStatistics::class)->assertCanSeeTableRecords([$entry])
            ->callTableAction('passed', $entry)->assertHasNoTableActionErrors();
        $this->assertLastEmail('awaiting_results', $explicit, 5);
        Livewire::test(ListExamResults::class)->assertCanSeeTableRecords([$entry])
            ->callTableAction('notify_student', $entry)->assertHasNoTableActionErrors();
        $this->assertLastEmail('completed', $explicit, 6);
        $this->assertTrue(WorkflowStageProgression::finished($entry->fresh()));
        $this->assertSame($snapshot, $entry->getRawOriginal('workflow_snapshot'));

        $this->assertSame(0, WorkflowSubmissionMessages::notify($entry));
        $this->assertFalse(WorkflowStageMessages::notifyReviewAction($entry, 'accept'));
        $this->assertSame('BNM-0001', app(WorkflowCandidateVerifier::class)->verify($entry));
        $this->assertFalse(WorkflowStageMessages::notifyResultStage($entry));
        $this->assertCount(6, $this->sentEmails());
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('workflow_notification_deliveries', 6);
        Http::assertNothingSent();
    }

    public function test_responsible_operator_completes_all_six_stages_without_separate_action_permissions(): void
    {
        $this->defaultTemplates();
        [$form, $workflow] = $this->workflow();
        $operator = $this->user('Workflow operator', 'admin');
        $operator->assignRole(Role::findOrCreate('workflow_operator', 'web'));
        foreach (['ViewAny:CustomFormEntry', 'View:CustomFormEntry', 'ViewAny:PaymentStatusList', 'ViewAny:CandidateEntranceStatistic', 'ViewAny:ExamResult'] as $permission) {
            $operator->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        $stages = $workflow->stages;
        foreach ($stages as &$stage) {
            if ($stage['data']['stage_type'] !== 'form_submission') {
                $stage['data']['responsible_role'] = ['workflow_operator'];
            }
        }
        unset($stage);
        $workflow->update(['stages' => $stages]);
        $this->actingAs($operator);
        foreach (['Accepted:CustomFormEntry', 'Rejected:CustomFormEntry', 'Reject:CustomFormEntry', 'Verify:CustomFormEntry', 'Pay:PaymentStatusList', 'Passed:CandidateEntranceStatistic', 'NotifyStudent:ExamResult'] as $permission) {
            $this->assertFalse($operator->can($permission));
        }

        $this->useArrayMail();
        $entry = $this->entry($form);
        $snapshot = $entry->getRawOriginal('workflow_snapshot');
        $this->assertLastEmail('form_submission', false, 1);
        $this->check($entry);
        $this->assertLastEmail('accept', false, 2);
        Livewire::test(ListCustomFormEntries::class)->assertTableActionVisible('verify', $entry)
            ->callTableAction('verify', $entry)->assertHasNoTableActionErrors();
        $this->assertLastEmail('verification', false, 3);
        app(CandidatePaymentRecorder::class)->record([
            'custom_form_entry_id' => $entry->id, 'receipt_number' => 'ROLE-ONLY-'.$entry->id,
            'amount_usd' => 10, 'datetime_pay' => now(),
        ]);
        $this->assertLastEmail('payment', false, 4);
        Livewire::test(ListCandidateEntranceStatistics::class)->assertCanSeeTableRecords([$entry])
            ->callTableAction('passed', $entry)->assertHasNoTableActionErrors();
        $this->assertLastEmail('awaiting_results', false, 5);
        Livewire::test(ListExamResults::class)->assertCanSeeTableRecords([$entry])
            ->callTableAction('notify_student', $entry)->assertHasNoTableActionErrors();
        $this->assertLastEmail('completed', false, 6);
        $entry->refresh();
        $this->assertTrue(WorkflowStageProgression::finished($entry));
        $this->assertSame('BNM-0001', $entry->candidate_id);
        $this->assertSame($snapshot, $entry->getRawOriginal('workflow_snapshot'));
        $this->assertFalse(FilamentActionPermissions::canForResource(
            CustomFormEntryResource::class,
            'download_excel',
        ));
        Http::assertNothingSent();
    }

    public static function negativeReviewActions(): array
    {
        return [['send_back', 'rejected'], ['reject', 'failed']];
    }

    #[DataProvider('negativeReviewActions')]
    public function test_real_review_return_and_reject_choose_their_existing_action_key(string $action, string $status): void
    {
        $this->defaultTemplates();
        [$form] = $this->workflow();
        $this->useArrayMail();
        $entry = $this->entry($form);
        DocumentTemplate::query()->where('type', 'custom_form_'.$form->id)->delete();
        Livewire::test(ListCustomFormEntries::class)
            ->callTableAction($action === 'send_back' ? 'rejected' : 'reject', $entry, data: ['review_note' => 'Supporting document needs review'])
            ->assertHasNoTableActionErrors();
        $this->assertLastEmail($action, false, 2);
        $this->assertStringContainsString('Supporting document needs review', $this->sentEmails()->last()->getHtmlBody());
        $this->assertSame($status, $entry->fresh()->review_status);
        $this->assertNull($entry->candidate_id);
        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
    }

    public static function blockedSubmissions(): array
    {
        return [['unticked email'], ['blank message'], ['draft']];
    }

    #[DataProvider('blockedSubmissions')]
    public function test_a_submission_template_does_not_bypass_channel_message_or_draft_guards(string $mode): void
    {
        $this->defaultTemplates();
        [$form, $workflow] = $this->workflow();
        $stages = $workflow->stages;
        if ($mode === 'unticked email') {
            $stages[0]['data']['notification_channels'] = [];
        } elseif ($mode === 'blank message') {
            $stages[0]['data']['submission_messages']['candidate_notification_message'] = '';
        }
        $workflow->update(['stages' => $stages]);
        $this->useArrayMail();
        $entry = $this->entry($form, $mode === 'draft' ? 'draft' : 'pending');
        WorkflowSubmissionMessages::notify($entry);
        $this->assertCount(0, $this->sentEmails());
        $this->assertDatabaseCount('workflow_notification_deliveries', 0);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertSame($mode === 'draft' ? 'draft' : 'pending', $entry->fresh()->review_status);
        Http::assertNothingSent();
    }

    public static function locales(): array
    {
        return [['en'], ['km']];
    }

    #[DataProvider('locales')]
    public function test_email_and_sms_selectors_show_eight_plain_actions_under_six_localized_stage_headers(string $locale): void
    {
        app()->setLocale($locale);
        foreach ([EmailTemplate::class => CreateEmailTemplate::class, SmsTemplate::class => CreateSmsTemplate::class] as $model => $page) {
            $component = Livewire::test($page);
            $field = $component->instance()->form->getFlatFields()['action'];
            $options = $field->getOptions();
            $this->assertCount(6, $options);
            $this->assertSame(array_values(WorkflowStageType::options()), array_keys($options));
            $this->assertSame(self::ACTIONS, collect($options)->collapse()->keys()->all());
            $this->assertEqualsCanonicalizing($model::actionOptions(), collect($options)->collapse()->all());
            $this->assertSame($model::actionPickerOptions(), $options);
            $this->assertFalse($field->isNative());
            $this->assertFalse($field->isHtmlAllowed());
            foreach ($model::actionOptions() as $key => $label) {
                $this->assertSame(__('email_templates.actions_for.'.$key), $label);
                $stage = match ($key) {
                    'accept', 'send_back', 'reject' => WorkflowStageType::Review,
                    default => WorkflowStageType::from($key),
                };
                $this->assertGroupMembership($options, $key, $stage->label(), $label);
            }
            $this->assertFalse($field->isRequired());
            $jsOptions = $field->getOptionsForJs();
            $this->assertCount(6, $jsOptions);
            $this->assertSame(array_keys($options), array_column($jsOptions, 'label'));
            $this->assertSame(self::ACTIONS, collect($jsOptions)->pluck('options')->collapse()->pluck('value')->all());
            $this->assertEqualsCanonicalizing($model::actionOptions(), collect($jsOptions)->pluck('options')->collapse()->pluck('label', 'value')->all());
        }
        $this->assertSame(__('candidate_entrance_statistics.statuses.accepted'), EmailTemplate::actionOptions()['accept']);
        $this->assertSame(__('workflow_notifications.actions.verify'), EmailTemplate::actionOptions()['verification']);
        $this->assertSame(__('candidate_entrance_statistics.statuses.send_back'), EmailTemplate::actionOptions()['send_back']);
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_existing_action_templates_keep_ids_and_keys_and_new_submission_action_remains_unique(): void
    {
        $legacy = [];
        foreach (array_diff(self::ACTIONS, ['form_submission']) as $action) {
            $legacy[$action] = $this->template('legacy_'.$action, $action);
        }
        foreach ($legacy as $action => $template) {
            $this->assertSame($template->id, EmailTemplate::forAction($action)?->id);
            $this->assertSame('legacy_'.$action, EmailTemplate::forAction($action)?->key);
        }
        $old = $legacy['accept'];
        Livewire::test(EditEmailTemplate::class, ['record' => $old->getRouteKey()])->assertSchemaStateSet(['action' => 'accept'])
            ->set('data.name', 'Renamed legacy checked template')->call('save')->assertHasNoFormErrors();
        $this->assertSame($old->id, EmailTemplate::forAction('accept')?->id);
        $this->assertSame('legacy_accept', $old->fresh()->key);
        $fields = ['name' => 'Submission notice', 'action' => 'form_submission', 'header_title' => 'UHS', 'subject' => 'Submitted {{ name }}', 'button' => 'Open', 'body' => '<p>{{ message }}</p>'];
        Livewire::test(CreateEmailTemplate::class)->fillForm($fields)->call('create')->assertHasNoFormErrors();
        $this->assertSame('submission_notice', EmailTemplate::forAction('form_submission')?->key);
        Livewire::test(CreateEmailTemplate::class)->fillForm(array_replace($fields, ['name' => 'Second submission notice']))
            ->call('create')->assertHasFormErrors(['action' => 'unique']);
        Livewire::test(CreateEmailTemplate::class)->fillForm(array_replace($fields, ['name' => 'Invalid workflow action', 'action' => 'unknown_action']))
            ->call('create')->assertHasFormErrors(['action' => 'in']);
        $this->assertDatabaseMissing('email_templates', ['name' => 'Invalid workflow action']);
        Livewire::test(CreateEmailTemplate::class)->fillForm(array_replace($fields, ['action' => null]))
            ->call('create')->assertHasNoFormErrors();
        $this->assertTrue(EmailTemplate::query()->where('key', 'submission_notice_1')->exists());
        $this->assertSame(1, EmailTemplate::query()->where('action', 'form_submission')->count());
    }

    public function test_native_catalog_renames_refresh_all_email_and_sms_annotations_without_changing_saved_templates_or_workflows(): void
    {
        $catalog = StageTypeCatalog::query()->where('is_system', true)->get()->keyBy('behavior');
        [$form, $workflow] = $this->workflow();
        $stages = $workflow->stages;
        foreach ($stages as &$stage) {
            $stage['data']['stage_type_id'] = $catalog[$stage['data']['stage_type']]->id;
            $stage['data']['notification_channels'] = [];
        }
        unset($stage);
        $workflow->update(['stages' => $stages]);
        $entry = $this->entry($form);
        $email = $this->template('existing_checked_email', 'accept');
        $sms = SmsTemplate::query()->create(['key' => 'existing_checked_sms', 'name' => 'Existing checked SMS', 'action' => 'accept', 'app_name' => 'UHS', 'body' => '{{ message }}']);
        $before = [
            (array) DB::table('custom_form_entries')->where('id', $entry->id)->first(),
            (array) DB::table('workflow_notifications')->where('id', $workflow->id)->first(),
            (array) DB::table('email_templates')->where('id', $email->id)->first(),
            (array) DB::table('sms_templates')->where('id', $sms->id)->first(),
        ];
        $labels = [];
        foreach (array_keys(WorkflowStageType::options()) as $index => $type) {
            $labels[$type] = ['en' => 'Configured workflow stage '.$index, 'km' => 'ដំណាក់កាលផ្ទាល់ខ្លួន '.$index];
            Livewire::test(EditWorkflowStageType::class, ['record' => $catalog[$type]->getRouteKey()])
                ->set('data.label_en', $labels[$type]['en'])->set('data.label_km', $labels[$type]['km'])
                ->call('save')->assertHasNoFormErrors();
        }
        $catalogBeforeRead = DB::table('workflow_stage_types')->orderBy('id')->get()->toArray();
        foreach (['en', 'km'] as $locale) {
            app()->setLocale($locale);
            foreach ([
                [CreateEmailTemplate::class, [], EmailTemplate::class],
                [EditEmailTemplate::class, ['record' => $email->getRouteKey()], EmailTemplate::class],
                [CreateSmsTemplate::class, [], SmsTemplate::class],
                [EditSmsTemplate::class, ['record' => $sms->getRouteKey()], SmsTemplate::class],
            ] as [$page, $parameters, $model]) {
                $component = Livewire::test($page, $parameters);
                if ($parameters !== []) {
                    $component->assertSet('data.action', 'accept');
                }
                $field = $component->instance()->form->getFlatFields()['action'];
                $options = $field->getOptions();
                $this->assertCount(6, $options);
                $this->assertSame(self::ACTIONS, collect($options)->collapse()->keys()->all());
                $this->assertEqualsCanonicalizing($model::actionOptions(), collect($options)->collapse()->all());
                $this->assertFalse($field->isNative());
                $this->assertFalse($field->isHtmlAllowed());
                $jsOptions = $field->getOptionsForJs();
                $this->assertCount(6, $jsOptions);
                $this->assertSame(array_keys($options), array_column($jsOptions, 'label'));
                $this->assertSame(self::ACTIONS, collect($jsOptions)->pluck('options')->collapse()->pluck('value')->all());
                $this->assertEqualsCanonicalizing($model::actionOptions(), collect($jsOptions)->pluck('options')->collapse()->pluck('label', 'value')->all());
                if ($parameters !== []) {
                    $this->assertSame($model::actionOptions()['accept'], $field->getOptionLabel());
                }
                foreach ($model::actionOptions() as $action => $label) {
                    $type = in_array($action, ['accept', 'send_back', 'reject'], true) ? 'review' : $action;
                    $this->assertGroupMembership($options, $action, $labels[$type][$locale], $label);
                }
            }
        }
        $this->assertEquals($catalogBeforeRead, DB::table('workflow_stage_types')->orderBy('id')->get()->toArray());
        $this->assertSame($before, [
            (array) DB::table('custom_form_entries')->where('id', $entry->id)->first(),
            (array) DB::table('workflow_notifications')->where('id', $workflow->id)->first(),
            (array) DB::table('email_templates')->where('id', $email->id)->first(),
            (array) DB::table('sms_templates')->where('id', $sms->id)->first(),
        ]);
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_catalog_annotations_are_read_only_and_use_canonical_inactive_locale_and_missing_record_fallbacks(): void
    {
        $canonical = StageTypeCatalog::query()->where('is_system', true)->where('behavior', 'review')->firstOrFail();
        StageTypeCatalog::query()->create(['label_en' => 'Active review alias', 'label_km' => 'ឈ្មោះជំនួសសកម្ម', 'behavior' => 'review', 'is_active' => true]);
        $canonical->update(['label_en' => 'Inactive canonical review', 'label_km' => 'ការពិនិត្យដើមអសកម្ម', 'is_active' => false]);
        foreach (['en' => 'Inactive canonical review', 'km' => 'ការពិនិត្យដើមអសកម្ម'] as $locale => $label) {
            app()->setLocale($locale);
            $before = DB::table('workflow_stage_types')->orderBy('id')->get()->toArray();
            foreach ([EmailTemplate::class, SmsTemplate::class] as $model) {
                $this->assertGroupMembership($model::actionPickerOptions(), 'accept', $label, $model::actionOptions()['accept']);
            }
            $this->assertEquals($before, DB::table('workflow_stage_types')->orderBy('id')->get()->toArray());
        }
        $canonical->update(['label_en' => 'Updated canonical label']);
        DB::table('workflow_stage_types')->where('id', $canonical->id)->update(['label_km' => '']);
        app()->setLocale('km');
        $this->assertGroupMembership(EmailTemplate::actionPickerOptions(), 'accept', 'Updated canonical label', EmailTemplate::actionOptions()['accept']);
        DB::table('workflow_stage_types')->where('id', $canonical->id)->update(['label_en' => '']);
        $this->assertGroupMembership(EmailTemplate::actionPickerOptions(), 'accept', WorkflowStageType::Review->label(), EmailTemplate::actionOptions()['accept']);
        StageTypeCatalog::query()->create(['label_en' => 'Received alias', 'label_km' => 'ឈ្មោះទទួលពាក្យជំនួស', 'behavior' => 'verification', 'is_active' => true]);
        DB::table('workflow_stage_types')->where('is_system', true)->where('behavior', 'verification')->delete();
        $before = DB::table('workflow_stage_types')->orderBy('id')->get()->toArray();
        foreach ([EmailTemplate::class, SmsTemplate::class] as $model) {
            $this->assertGroupMembership($model::actionPickerOptions(), 'verification', WorkflowStageType::Verification->label(), $model::actionOptions()['verification']);
        }
        $this->assertEquals($before, DB::table('workflow_stage_types')->orderBy('id')->get()->toArray());
        DB::table('workflow_stage_types')->where('id', $canonical->id)->update(['label_km' => 'Shared stage heading']);
        DB::table('workflow_stage_types')->where('is_system', true)->where('behavior', 'payment')->update(['label_en' => 'Shared stage heading']);
        app()->setLocale('en');
        $before = DB::table('workflow_stage_types')->orderBy('id')->get()->toArray();
        foreach ([EmailTemplate::class, SmsTemplate::class] as $model) {
            $options = $model::actionPickerOptions();
            $this->assertCount(5, $options);
            $this->assertEqualsCanonicalizing($model::actionOptions(), collect($options)->collapse()->all());
            $this->assertSame(['accept', 'send_back', 'reject', 'payment'], array_keys($options['Shared stage heading']));
        }
        $this->assertEquals($before, DB::table('workflow_stage_types')->orderBy('id')->get()->toArray());
        Schema::drop('workflow_stage_types');
        foreach ([EmailTemplate::class, SmsTemplate::class] as $model) {
            $options = $model::actionPickerOptions();
            $this->assertCount(6, $options);
            $this->assertSame(self::ACTIONS, collect($options)->collapse()->keys()->all());
            $this->assertGroupMembership($options, 'accept', WorkflowStageType::Review->label(), $model::actionOptions()['accept']);
            $this->assertGroupMembership($options, 'verification', WorkflowStageType::Verification->label(), $model::actionOptions()['verification']);
        }
        $this->assertFalse(Schema::hasTable('workflow_stage_types'));
        Mail::assertNothingSent();
        Http::assertNothingSent();
    }

    private function assertGroupMembership(array $groups, string $action, string $heading, string $label): void
    {
        $this->assertArrayHasKey($heading, $groups);
        $this->assertArrayHasKey($action, $groups[$heading]);
        $this->assertSame($label, $groups[$heading][$action]);
        $this->assertSame([$heading], array_keys(array_filter($groups, fn (array $options): bool => array_key_exists($action, $options))));
    }

    private function defaultTemplates(): void
    {
        foreach (self::ACTIONS as $action) {
            $this->template('default_'.$action, $action);
        }
    }

    private function template(string $key, ?string $action): EmailTemplate
    {
        return EmailTemplate::query()->create(['key' => $key, 'name' => $key, 'action' => $action, 'header_title' => 'UHS', 'subject' => $key.' | {{ form }} | {{ stage }} | {{ name }}', 'button' => 'Open', 'body' => '<p>MARKER '.$key.' | {{ name }} | {{ form }} | {{ stage }} | {{ message }} | {{ status }}</p>']);
    }

    private function workflow(?string $explicitTemplate = null): array
    {
        $form = CustomForm::query()->create(['name' => 'Email Routing Application', 'slug' => 'email-routing-'.uniqid(), 'is_active' => true, 'requires_payment' => true, 'statistics_menu' => StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS, 'passed_result_menu' => PassedResultMenuOptions::EXAM_RESULTS]);
        $stages = [];
        foreach (['form_submission', 'review', 'verification', 'payment', 'awaiting_results', 'completed'] as $type) {
            $data = ['stage_name' => self::STAGE_NAMES[$type], 'stage_type' => $type, 'responsible_role' => $type === 'form_submission' ? null : ['admin'], 'notification_channels' => ['email'], 'notification_message' => 'Candidate message '.$type, 'status_message' => 'Candidate status '.$type, 'payment_source' => 'system', 'email_template_key' => $explicitTemplate];
            if ($type === 'form_submission') {
                $data['submission_messages'] = ['candidate_status_message' => 'Candidate status form_submission', 'candidate_notification_message' => 'Candidate message form_submission'];
            } elseif ($type === 'review') {
                foreach (['accept', 'send_back', 'reject'] as $action) {
                    $data['review_actions'][$action] = ['status_message' => 'Candidate status '.$action, 'notification_message' => 'Candidate message '.$action];
                }
            } elseif ($type === 'awaiting_results') {
                $data['statistics_menu'] = StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS;
            } elseif ($type === 'completed') {
                $data['passed_result_menu'] = PassedResultMenuOptions::EXAM_RESULTS;
            }
            $stages[] = ['type' => 'stage', 'data' => $data];
        }
        $workflow = WorkflowNotification::query()->create(['name' => 'Email routing '.uniqid(), 'stages' => $stages]);
        $workflow->forms()->attach($form);

        return [$form, $workflow];
    }

    private function entry(CustomForm $form, string $status = 'pending'): CustomFormEntry
    {
        return CustomFormEntry::query()->forceCreate(['custom_form_id' => $form->id, 'created_by' => $this->candidate->id, 'review_status' => $status, 'data' => ['registration_status' => $status, 'candidate_status' => 'pending', 'submitted_at' => now()->toDateTimeString(), 'major' => (string) $this->major->id]]);
    }

    private function check(CustomFormEntry $entry): void
    {
        DocumentTemplate::query()->where('type', 'custom_form_'.$entry->custom_form_id)->delete();
        Livewire::test(ListCustomFormEntries::class)->callTableAction('accepted', $entry)->assertHasNoTableActionErrors();
        $entry->refresh();
    }

    private function useArrayMail(): void
    {
        config(['mail.default' => 'array']);
        Mail::swap(new MailManager(app()));
        app()->forgetInstance('mailer');
    }

    private function sentEmails()
    {
        return app('mailer')->getSymfonyTransport()->messages()->map(fn ($sent) => $sent->getOriginalMessage());
    }

    private function assertLastEmail(string $action, bool $explicit, int $count): void
    {
        $messages = $this->sentEmails();
        $this->assertCount($count, $messages);
        $message = $messages->last();
        $this->assertInstanceOf(Email::class, $message);
        $stageType = in_array($action, ['accept', 'send_back', 'reject'], true) ? 'review' : $action;
        $key = $explicit ? 'explicit' : 'default_'.$action;
        $this->assertSame($key.' | Email Routing Application | '.self::STAGE_NAMES[$stageType].' | Template Candidate', $message->getSubject());
        $this->assertSame($this->candidate->email, $message->getTo()[0]->getAddress());
        foreach (['MARKER '.$key, 'Template Candidate', 'Email Routing Application', self::STAGE_NAMES[$stageType], 'Candidate message '.$action, 'Candidate status '.$action] as $expected) {
            $this->assertStringContainsString($expected, $message->getHtmlBody());
        }
        $this->assertStringNotContainsString('{{', $message->getHtmlBody());
    }

    private function user(string $name, string $type): User
    {
        $username = 'email-routing-'.$type.'-'.uniqid();

        return User::query()->forceCreate(['registration_type' => $type, 'name' => $name, 'username' => $username, 'email' => $username.'@example.test', 'date_of_birth' => '2000-01-01', 'password' => 'password', 'is_active' => true, 'locale' => 'en']);
    }
}
