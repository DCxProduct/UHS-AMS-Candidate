<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Payments\Pages\ListPayments;
use App\Filament\Admin\Resources\Payments\Tables\PaymentsTable;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class PaymentsWorkflowStatusLabelTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;
    private CustomForm $form;
    private CustomFormEntry $entry;
    private WorkflowNotification $workflow;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        $admin = $this->user('payment_record_label_admin', 'admin');
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
        $this->candidate = $this->user('payment_record_label_candidate', 'student');
        $this->form = CustomForm::query()->create(['name' => 'Payment record labels', 'slug' => 'payment-record-labels', 'is_active' => true]);
        $this->workflow = WorkflowNotification::query()->create([
            'name' => 'Payment record status workflow',
            'stages' => [['type' => 'stage', 'data' => [
                'stage_name' => 'Payment', 'stage_type' => 'payment',
                'status_message' => 'បានបង់ប្រាក់រួចរាល់',
                'payment_statuses' => ['unpaid_status_message' => 'រង់ចាំបង់ប្រាក់'],
            ]]],
        ]);
        $this->workflow->forms()->attach($this->form);
        $this->entry = $this->createEntry();
    }

    public function test_payment_record_badge_shows_exact_applications_paid_label_and_preserves_state_and_amounts(): void
    {
        $payment = $this->payment();
        $component = $this->list()->assertCanSeeTableRecords([$payment])
            ->assertTableColumnStateSet('status_payt', 'paid', $payment);
        $column = $component->instance()->getTable()->getColumn('status_payt')->record($payment);
        $this->assertSame('បានបង់ប្រាក់រួចរាល់', $column->formatState($column->getState()));
        $this->assertSame('success', $column->getColor($column->getState()));
        $this->assertSame('paid', $payment->fresh()->status_payt);
        $this->assertSame('5.00', $payment->fresh()->amount_usd);
        $this->assertSame('approved', $this->entry->fresh()->review_status);
    }

    public function test_formatted_status_export_uses_workflow_text_but_database_export_keeps_paid(): void
    {
        $payment = $this->payment();
        $formatted = (new ReflectionMethod(PaymentsTable::class, 'excelRows'))->invoke(null, [$payment], ['status_payt']);
        $clean = (new ReflectionMethod(PaymentsTable::class, 'cleanDataRows'))->invoke(null, [$payment], ['status_payt']);

        $this->assertSame('បានបង់ប្រាក់រួចរាល់', $formatted[1][0]);
        $this->assertSame('paid', $clean[1][0]);
    }

    public static function locales(): array
    {
        return [['en'], ['km']];
    }

    #[DataProvider('locales')]
    public function test_paid_labels_are_frozen_while_new_blank_or_unassigned_workflows_keep_existing_fallbacks(string $locale): void
    {
        app()->setLocale($locale);
        $legacy = $this->payment(linked: false);
        $label = new ReflectionMethod(PaymentsTable::class, 'paymentStatusLabel');
        // A matching form and owner alone must not borrow another application's label.
        $this->assertSame(__('payments.options.status_payt.paid'), $label->invoke(null, $legacy));

        $linked = $this->payment();
        $stages = $this->workflow->stages;
        $stages[0]['data']['status_message'] = ' ';
        $this->workflow->update(['stages' => $stages]);
        $this->assertSame('បានបង់ប្រាក់រួចរាល់', $label->invoke(null, $linked));

        $this->entry = $this->createEntry();
        $blankWorkflowPayment = $this->payment();
        $this->assertSame(__('payments.options.status_payt.paid'), $label->invoke(null, $blankWorkflowPayment));

        $this->workflow->forms()->detach($this->form);
        $this->assertSame('បានបង់ប្រាក់រួចរាល់', $label->invoke(null, $linked));
        $this->assertSame(__('payments.options.status_payt.paid'), $label->invoke(null, $blankWorkflowPayment));

        $this->entry = $this->createEntry();
        $unassignedWorkflowPayment = $this->payment();
        $this->assertSame(__('payments.options.status_payt.paid'), $label->invoke(null, $unassignedWorkflowPayment));
    }

    public function test_other_canonical_payment_states_keep_their_existing_color_and_translation(): void
    {
        $payment = $this->payment(status: 'return');
        $component = $this->list()->assertTableColumnStateSet('status_payt', 'return', $payment);
        $column = $component->instance()->getTable()->getColumn('status_payt')->record($payment);

        $this->assertSame(__('payments.options.status_payt.return'), $column->formatState($column->getState()));
        $this->assertSame('danger', $column->getColor($column->getState()));
    }

    private function list()
    {
        return Livewire::test(ListPayments::class)
            ->set('tableFilters.payment_filters.from_date', null)
            ->set('tableFilters.payment_filters.to_date', null)
            ->set('tableFilters.payment_filters.received_by', null);
    }

    private function payment(bool $linked = true, string $status = 'paid'): Payment
    {
        return Payment::query()->create([
            'custom_form_entry_id' => $linked ? $this->entry->getKey() : null,
            'users_id' => $this->candidate->getKey(), 'form_id' => $this->form->getKey(),
            'receipt_number' => 'RECORD-LABEL-'.Payment::query()->count(),
            'status_payt' => $status, 'amount_usd' => 5, 'datetime_pay' => now(),
        ]);
    }

    private function createEntry(): CustomFormEntry
    {
        return CustomFormEntry::query()->forceCreate([
            'custom_form_id' => $this->form->getKey(), 'created_by' => $this->candidate->getKey(),
            'review_status' => 'approved', 'data' => ['registration_status' => 'approved'],
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
