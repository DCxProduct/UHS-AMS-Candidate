<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\PaymentStatusLists\Pages\ListPaymentStatusLists;
use App\Filament\Admin\Resources\PaymentStatusLists\Tables\PaymentStatusListsTable;
use App\Models\Payment;
use App\Models\PaymentStatusList;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Support\WorkflowStageMessages;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkflowPaymentStatusLabelTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;
    private CustomForm $form;
    private WorkflowNotification $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        $admin = $this->user('payment_label_admin', 'admin');
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
        $this->candidate = $this->user('payment_label_candidate', 'student');
        $this->form = CustomForm::query()->create([
            'name' => 'Payment labels', 'slug' => 'payment-labels',
            'is_active' => true, 'requires_payment' => true,
        ]);
        $this->workflow = WorkflowNotification::query()->create([
            'name' => 'Payment label workflow',
            'stages' => [
                ['type' => 'stage', 'data' => [
                    'stage_name' => 'Review', 'stage_type' => 'review',
                    'review_actions' => ['accept' => ['status_message' => 'បានពិនិត្យពាក្យ']],
                ]],
                ['type' => 'stage', 'data' => [
                    'stage_name' => 'Payment', 'stage_type' => 'payment',
                    'responsible_role' => ['admin'],
                    'status_message' => 'បានបង់ប្រាក់រួចរាល់',
                    'payment_statuses' => ['unpaid_status_message' => 'រង់ចាំការបង់ប្រាក់'],
                ]],
                ['type' => 'stage', 'data' => [
                    'stage_name' => 'Results', 'stage_type' => 'awaiting_results',
                    'status_message' => 'សូមរង់ចាំលទ្ធផល',
                ]],
            ],
        ]);
        $this->workflow->forms()->attach($this->form);
    }

    public function test_badge_uses_configured_labels_before_and_after_payment_without_changing_the_raw_state_or_workflow(): void
    {
        $entry = $this->entry();
        $component = $this->list()->assertTableColumnStateSet('status_payt', 'unpaid', $entry)
            ->assertTableActionVisible('pay', $entry);
        $column = $component->instance()->getTable()->getColumn('status_payt')->record($entry);
        $this->assertSame('រង់ចាំការបង់ប្រាក់', $column->formatState($column->getState()));
        $this->assertSame('បានពិនិត្យពាក្យ', WorkflowStageMessages::statusText($entry));
        $this->assertSame(WorkflowStageType::Payment, WorkflowStageMessages::stageTypeFor($entry));

        $this->pay($entry);
        $component->call('$refresh')->assertTableColumnStateSet('status_payt', 'paid', $entry)
            ->assertTableActionHidden('pay', $entry);
        $column = $component->instance()->getTable()->getColumn('status_payt')->record($entry->fresh());
        $this->assertSame('បានបង់ប្រាក់រួចរាល់', $column->formatState($column->getState()));
        $this->assertSame('approved', $entry->fresh()->review_status);
        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageMessages::stageTypeFor($entry));
        $this->assertSame('បានបង់ប្រាក់រួចរាល់', WorkflowStageMessages::statusText($entry));
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_custom_labels_leave_canonical_filters_counts_and_exact_application_payment_lookup_unchanged(): void
    {
        $paid = $this->entry();
        $unpaid = $this->entry();
        $this->pay($paid);
        $component = $this->list()
            ->assertCanSeeTableRecords([$paid, $unpaid])
            ->assertTableColumnStateSet('status_payt', 'paid', $paid)
            ->assertTableColumnStateSet('status_payt', 'unpaid', $unpaid);
        $column = $component->instance()->getTable()->getColumn('status_payt')->record($unpaid);
        $this->assertSame('រង់ចាំការបង់ប្រាក់', $column->formatState($column->getState()));
        $this->assertSame(['total' => 2, 'paid' => 1, 'unpaid' => 1], PaymentStatusListsTable::summaryCounts($component->instance()->getFilteredTableQuery()));

        $component->set('tableFilters.payment_status_list_filters.status_payt', 'paid')
            ->assertCanSeeTableRecords([$paid])->assertCanNotSeeTableRecords([$unpaid]);
        $this->assertSame(['total' => 1, 'paid' => 1, 'unpaid' => 0], PaymentStatusListsTable::summaryCounts($component->instance()->getFilteredTableQuery()));
        $component->set('tableFilters.payment_status_list_filters.status_payt', 'unpaid')
            ->assertCanSeeTableRecords([$unpaid])->assertCanNotSeeTableRecords([$paid]);
        $this->assertSame(['total' => 1, 'paid' => 0, 'unpaid' => 1], PaymentStatusListsTable::summaryCounts($component->instance()->getFilteredTableQuery()));
    }

    public static function locales(): array
    {
        return [['en'], ['km']];
    }

    #[DataProvider('locales')]
    public function test_blank_or_legacy_labels_keep_translated_defaults(string $locale): void
    {
        app()->setLocale($locale);
        $stages = $this->workflow->stages;
        $stages[1]['data']['status_message'] = ' ';
        unset($stages[1]['data']['payment_statuses']);
        $this->workflow->update(['stages' => $stages]);
        $paid = $this->entry();
        $unpaid = $this->entry();
        $this->pay($paid);
        $component = $this->list();
        $column = $component->instance()->getTable()->getColumn('status_payt');
        foreach (['paid' => $paid, 'unpaid' => $unpaid] as $state => $record) {
            $column->record($record);
            $this->assertNull(WorkflowStageMessages::paymentStatusLabel($record, $state));
            $this->assertSame(__('payments.options.status_payt.'.$state), $column->formatState($column->getState()));
        }
    }

    public function test_unassigned_forms_and_invalid_states_do_not_receive_another_forms_labels(): void
    {
        $entry = $this->entry();
        $otherForm = CustomForm::query()->create(['name' => 'Other', 'slug' => 'other-payment-labels', 'is_active' => true, 'requires_payment' => true]);
        $other = $this->entry($otherForm);
        $this->assertNull(WorkflowStageMessages::paymentStatusLabel($other, 'unpaid'));
        $this->assertNull(WorkflowStageMessages::paymentStatusLabel($other, 'paid'));
        $this->assertNull(WorkflowStageMessages::paymentStatusLabel($entry, 'pending'));

        $stages = $this->workflow->stages;
        $stages[1]['data']['payment_statuses']['unpaid_status_message'] = ' ';
        $this->workflow->update(['stages' => $stages]);
        $this->assertSame('រង់ចាំការបង់ប្រាក់', WorkflowStageMessages::paymentStatusLabel($entry, 'unpaid'));
        $this->assertSame('បានបង់ប្រាក់រួចរាល់', WorkflowStageMessages::paymentStatusLabel($entry, 'paid'));
        $newEntry = $this->entry();
        $this->assertNull(WorkflowStageMessages::paymentStatusLabel($newEntry, 'unpaid'));
        $this->assertSame('បានបង់ប្រាក់រួចរាល់', WorkflowStageMessages::paymentStatusLabel($newEntry, 'paid'));
    }

    private function list()
    {
        return Livewire::test(ListPaymentStatusLists::class)
            ->set('tableFilters.payment_status_list_filters.from_date', null)
            ->set('tableFilters.payment_status_list_filters.to_date', null)
            ->set('tableFilters.payment_status_list_filters.received_by', null);
    }

    private function entry(?CustomForm $form = null): PaymentStatusList
    {
        return PaymentStatusList::query()->forceCreate([
            'custom_form_id' => ($form ?? $this->form)->getKey(), 'created_by' => $this->candidate->getKey(),
            'review_status' => 'approved', 'data' => ['registration_status' => 'approved'],
        ]);
    }

    private function pay(PaymentStatusList $entry): void
    {
        Payment::query()->create([
            'custom_form_entry_id' => $entry->getKey(), 'users_id' => $entry->created_by,
            'form_id' => $entry->custom_form_id, 'receipt_number' => 'LABEL-'.$entry->getKey(),
            'status_payt' => 'paid', 'amount_usd' => 5, 'datetime_pay' => now(),
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
