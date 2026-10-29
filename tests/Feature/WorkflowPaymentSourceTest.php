<?php

namespace Tests\Feature;

use App\Enums\WorkflowPaymentSource;
use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\CreateWorkflowNotification;
use App\Filament\Admin\Resources\WorkflowNotifications\Pages\EditWorkflowNotification;
use App\Filament\Admin\Resources\WorkflowNotifications\WorkflowNotificationResource;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Support\WorkflowNotificationStageSummary;
use App\Support\WorkflowStageMessages;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkflowPaymentSourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        $this->admin = $this->user('source_admin', 'admin');
        $this->admin->assignRole(Role::findOrCreate('admin', 'web'));
        Role::findOrCreate('cashier_officer', 'web');
        Role::findOrCreate('registrar_officer', 'web');
        $this->actingAs($this->admin);
    }

    public static function sources(): array
    {
        return [['system'], ['ams']];
    }

    #[DataProvider('sources')]
    public function test_create_and_edit_persist_both_sources_without_changing_stage_settings_or_form_assignment(string $source): void
    {
        $stages = $this->stages($source);
        Livewire::test(CreateWorkflowNotification::class)
            ->set('data.stages', [])
            ->fillForm(['name' => 'Selectable payment source', 'stages' => $stages])
            ->call('create')->assertHasNoFormErrors();
        $workflow = WorkflowNotification::query()->sole();
        $this->assertSame($source, data_get($workflow->stages, '1.data.payment_source'));
        $this->assertSame(WorkflowPaymentSource::from($source), $workflow->paymentSource());
        $form = $this->form('assigned_source');
        $workflow->forms()->attach($form);
        $this->assertSame(WorkflowPaymentSource::from($source), WorkflowNotification::paymentSourceForForm($form->getKey()));

        $original = $workflow->stages;
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $paymentKey = array_keys($component->get('data.stages'))[1];
        $next = $source === 'system' ? 'ams' : 'system';
        $component->assertSet("data.stages.{$paymentKey}.data.payment_source", $source)
            ->set("data.stages.{$paymentKey}.data.payment_source", $next)
            ->call('save')->assertHasNoFormErrors();

        $saved = $workflow->fresh()->stages;
        $this->assertSame($next, data_get($saved, '1.data.payment_source'));
        $this->assertSame(WorkflowPaymentSource::from($next), WorkflowNotification::paymentSourceForForm($form->getKey()));
        foreach ($original as $index => $stage) {
            foreach ($stage['data'] as $field => $value) {
                if ($field !== 'payment_source') {
                    $this->assertEquals($value, $saved[$index]['data'][$field] ?? null);
                }
            }
        }
        $this->assertSame([$form->getKey()], $workflow->forms()->pluck('custom_forms.id')->all());
        $reopened = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $key = array_keys($reopened->get('data.stages'))[1];
        $reopened->assertSet("data.stages.{$key}.data.payment_source", $next);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public static function locales(): array
    {
        return [['en'], ['km']];
    }

    #[DataProvider('locales')]
    public function test_payment_only_selector_options_and_summary_are_localized(string $locale): void
    {
        app()->setLocale($locale);
        $stages = $this->stages('ams');
        array_unshift($stages, ['type' => 'stage', 'data' => ['stage_name' => 'Submit', 'stage_type' => 'form_submission']]);
        $stages[] = ['type' => 'stage', 'data' => ['stage_name' => 'Completed', 'stage_type' => 'completed']];
        $workflow = WorkflowNotification::query()->create(['name' => 'Localized source', 'stages' => $stages]);
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $fields = collect($component->instance()->form->getFlatFields())
            ->filter(fn ($field): bool => str_ends_with($field->getStatePath(), '.payment_source'));
        $this->assertCount(1, $fields);
        $picker = $fields->first();
        $expected = $locale === 'km'
            ? ['system' => 'ប្រព័ន្ធនេះ', 'ams' => 'ប្រព័ន្ធ AMS']
            : ['system' => 'This system', 'ams' => 'AMS system'];
        $this->assertSame($expected, $picker->getOptions());
        $this->assertSame($expected['ams'], $picker->getOptionLabel());
        $this->assertTrue($picker->isRequired());
        $this->assertSame(__('workflow_notifications.fields.payment_source'), $picker->getLabel());
        $summary = WorkflowNotificationStageSummary::from($workflow->stages);
        foreach ($summary as $index => $stage) {
            if ($index === 2) {
                $this->assertSame($expected['ams'], $stage['payment_source_label']);
            } else {
                $this->assertArrayNotHasKey('payment_source_label', $stage);
            }
        }
        $this->get(WorkflowNotificationResource::getUrl('view', ['record' => $workflow]))
            ->assertOk()->assertSee(__('workflow_notifications.fields.payment_source'))->assertSee($expected['ams']);
    }

    public function test_legacy_missing_source_and_unassigned_form_resolve_to_system_without_rewriting_saved_json(): void
    {
        $stages = $this->stages('system');
        unset($stages[1]['data']['payment_source']);
        $workflow = WorkflowNotification::query()->create(['name' => 'Legacy source', 'stages' => $stages]);
        $form = $this->form('legacy_source');
        $workflow->forms()->attach($form);
        $raw = DB::table('workflow_notifications')->where('id', $workflow->getKey())->value('stages');

        $this->assertSame(WorkflowPaymentSource::System, $workflow->paymentSource());
        $this->assertSame(WorkflowPaymentSource::System, WorkflowNotification::paymentSourceForForm($form->getKey()));
        $unassigned = $this->form('unassigned_source');
        $this->assertSame(WorkflowPaymentSource::System, WorkflowNotification::paymentSourceForForm($unassigned->getKey()));
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $key = array_keys($component->get('data.stages'))[1];
        $component->assertSet("data.stages.{$key}.data.payment_source", 'system');
        $this->assertArrayNotHasKey('payment_source', $workflow->fresh()->stages[1]['data']);
        $this->assertSame($raw, DB::table('workflow_notifications')->where('id', $workflow->getKey())->value('stages'));
        $this->assertSame(__('workflow_notifications.payment_sources.system'), WorkflowNotificationStageSummary::from($workflow->stages)[1]['payment_source_label']);
    }

    public function test_invalid_and_empty_source_are_rejected_by_the_payment_form_without_changing_saved_source(): void
    {
        $workflow = WorkflowNotification::query()->create(['name' => 'Validate source', 'stages' => $this->stages('ams')]);
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $key = array_keys($component->get('data.stages'))[1];
        $path = "stages.{$key}.data.payment_source";
        $component->set('data.'.$path, 'unsupported-source')->call('save')->assertHasFormErrors([$path => 'in']);
        $this->assertSame(WorkflowPaymentSource::Ams, $workflow->fresh()->paymentSource());
        $component->set('data.'.$path, null)->call('save')->assertHasFormErrors([$path => 'required']);
        $this->assertSame(WorkflowPaymentSource::Ams, $workflow->fresh()->paymentSource());
    }

    public function test_selecting_payment_on_the_initial_or_appended_stage_defaults_to_this_system(): void
    {
        $component = Livewire::test(CreateWorkflowNotification::class);
        $firstKey = array_key_first($component->get('data.stages'));
        $component->set("data.stages.{$firstKey}.data.stage_type", 'payment')
            ->assertSet("data.stages.{$firstKey}.data.payment_source", 'system');

        $component->callAction(TestAction::make('addStage')->schemaComponent('stages-section', schema: 'form'));
        $appendedKey = array_key_last($component->get('data.stages'));
        $this->assertNotSame($firstKey, $appendedKey);
        $component->set("data.stages.{$appendedKey}.data.stage_type", 'payment')
            ->assertSet("data.stages.{$appendedKey}.data.payment_source", 'system');
    }

    public function test_only_present_payment_stage_sources_are_normalized_and_other_stages_remain_untouched(): void
    {
        $stages = [
            ['type' => 'stage', 'data' => ['stage_type' => 'review', 'payment_source' => 'custom-review-value', 'notification_message' => 'Review body']],
            ['type' => 'stage', 'data' => ['stage_type' => 'payment', 'notification_message' => 'Legacy payment body']],
            ['stage_type' => 'payment', 'payment_source' => 'unsupported', 'status_message' => 'Paid label'],
        ];
        $normalized = WorkflowNotification::normalizeStages($stages);
        $this->assertSame($stages[0], $normalized[0]);
        $this->assertSame($stages[1], $normalized[1]);
        $this->assertSame('system', $normalized[2]['payment_source']);
        $this->assertSame('Paid label', $normalized[2]['status_message']);
        $this->assertSame(WorkflowPaymentSource::System, WorkflowPaymentSource::resolve(['ams']));
        $this->assertSame(WorkflowPaymentSource::System, WorkflowPaymentSource::resolve('unsupported'));
    }

    public function test_source_configuration_does_not_mark_an_application_paid_notify_or_replace_existing_payment_routing(): void
    {
        $workflow = WorkflowNotification::query()->create(['name' => 'Configure without paying', 'stages' => $this->stages('system')]);
        $form = $this->form('configure_without_paying');
        $workflow->forms()->attach($form);
        $candidate = $this->user('source_candidate', 'student');
        $entry = CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $form->getKey(), 'created_by' => $candidate->getKey(),
            'review_status' => 'approved', 'data' => ['registration_status' => 'approved', 'candidate_status' => 'pending'],
        ]);
        $component = Livewire::test(EditWorkflowNotification::class, ['record' => $workflow->getRouteKey()]);
        $key = array_keys($component->get('data.stages'))[1];
        foreach (['ams', 'system', 'ams'] as $source) {
            $component->set("data.stages.{$key}.data.payment_source", $source)->call('save')->assertHasNoFormErrors();
            $this->assertSame(WorkflowPaymentSource::from($source), WorkflowNotification::paymentSourceForForm($form->getKey()));
            $this->assertSame(WorkflowStageType::Payment, WorkflowStageMessages::stageTypeFor($entry->fresh()));
            $this->assertTrue(WorkflowStageMessages::requiresPayment($entry));
            $this->assertSame('approved', $entry->fresh()->review_status);
            $this->assertSame('approved', data_get($entry->fresh()->data, 'registration_status'));
            $this->assertDatabaseCount('payments', 0);
            $this->assertDatabaseCount('notifications', 0);
        }

        Payment::query()->create([
            'custom_form_entry_id' => $entry->getKey(), 'users_id' => $candidate->getKey(), 'form_id' => $form->getKey(),
            'receipt_number' => 'SOURCE-MANUAL-PAYMENT', 'status_payt' => 'paid', 'amount_usd' => 5, 'datetime_pay' => now(),
        ]);
        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageMessages::stageTypeFor($entry->fresh()));
        $this->assertSame(WorkflowPaymentSource::Ams, WorkflowNotification::paymentSourceForForm($form->getKey()));
        $this->assertSame('approved', $entry->fresh()->review_status);
        $this->assertDatabaseCount('notifications', 0);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    private function stages(string $source): array
    {
        return [
            ['type' => 'stage', 'data' => [
                'stage_name' => 'Review', 'stage_type' => 'review', 'responsible_role' => ['registrar_officer'],
                'notification_channels' => ['system'],
                'review_actions' => ['accept' => ['status_message' => 'Checked', 'notification_message' => 'Your application was checked.']],
            ]],
            ['type' => 'stage', 'data' => [
                'stage_name' => 'Payment', 'stage_type' => 'payment', 'responsible_role' => ['cashier_officer'],
                'payment_source' => $source, 'notification_channels' => ['system'],
                'status_message' => 'Paid', 'notification_message' => 'Payment received.',
                'payment_statuses' => ['unpaid_status_message' => 'Unpaid'],
                'audience_messages' => ['staff_notification_message' => 'An application payment was received.', 'staff_recipient_ids' => [$this->admin->getKey()]],
            ]],
            ['type' => 'stage', 'data' => [
                'stage_name' => 'Results', 'stage_type' => 'awaiting_results', 'responsible_role' => ['registrar_officer'],
                'notification_channels' => ['system'], 'status_message' => 'Passed',
                'result_statuses' => ['pending_status_message' => 'Pending result'], 'notification_message' => 'Your exam result is available.',
            ]],
        ];
    }

    private function form(string $slug): CustomForm
    {
        return CustomForm::query()->create(['name' => $slug, 'slug' => $slug, 'is_active' => true]);
    }

    private function user(string $username, string $type): User
    {
        return User::query()->forceCreate([
            'registration_type' => $type, 'name' => $username, 'username' => $username,
            'email' => $username.'@example.test', 'date_of_birth' => '2000-01-01', 'password' => 'password', 'is_active' => true,
        ]);
    }
}
