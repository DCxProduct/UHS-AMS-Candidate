<?php

namespace Tests\Feature;

use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\Payments\Pages\ListPayments;
use App\Filament\Admin\Resources\Payments\PaymentResource;
use App\Filament\Admin\Resources\Payments\Schemas\PaymentForm;
use App\Filament\Admin\Resources\PaymentStatusLists\Pages\ListPaymentStatusLists;
use App\Filament\Widgets\CashierStatsOverview;
use App\Models\MefInvoiceCode;
use App\Models\Payment;
use App\Models\PaymentStatusList;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use App\Rules\ValidMefInvoiceReceipt;
use App\Services\CandidatePaymentRecorder;
use App\Support\WorkflowStageProgression;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use ReflectionProperty;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PaymentRecordsClearDataTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;

    private User $candidate;

    protected function setUp(): void
    {
        parent::setUp();
        (new ReflectionProperty(CustomForm::class, 'profileFormIdCache'))->setValue(null, []);
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        Http::fake();
        Mail::fake();
        config(['services.plasgate.private_key' => null, 'services.plasgate.secret' => null]);
        $this->cashier = $this->staff(true);
        $this->candidate = $this->user('student', 'candidate');
        $this->actingAs($this->cashier);
    }

    public function test_clearing_a_payment_record_preserves_its_paid_application_workflow_and_other_payment_menus(): void
    {
        $form = $this->form();
        $workflow = WorkflowNotification::query()->create(['name' => 'Paid application workflow', 'stages' => collect([
            WorkflowStageType::Payment, WorkflowStageType::AwaitingResults, WorkflowStageType::Completed,
        ])->map(fn (WorkflowStageType $type): array => ['type' => 'stage', 'data' => [
            'stage_type' => $type->value, 'stage_name' => $type->label(), 'responsible_role' => ['cashier_manager'],
            'payment_source' => 'system', 'notification_channels' => ['system'],
        ]])->all()]);
        $workflow->forms()->attach($form);
        $entry = $this->entry($form, 'pending');
        $payment = app(CandidatePaymentRecorder::class)->record(['custom_form_entry_id' => $entry->id, 'receipt_number' => 'CLEAR-ORDERED', 'amount_usd' => 10, 'datetime_pay' => now()]);
        $entryBefore = $entry->fresh()->getAttributes();
        $paymentBefore = $payment->fresh()->getAttributes();
        $this->assertSame(['1', '1', '0'], $this->dashboardTotals());
        $component = Livewire::test(ListPayments::class)->assertActionVisible('clear_data')->assertCanSeeTableRecords([$payment]);
        $this->assertTrue($component->instance()->getAction('clear_data')->isConfirmationRequired());
        $component->callAction('clear_data', arguments: ['selectedRecordKeys' => [$payment->id]])->assertCanNotSeeTableRecords([$payment]);
        $this->assertFinancialAttributesUnchanged($paymentBefore, $payment->fresh());
        $this->assertNotNull($payment->fresh()->records_cleared_at);
        $this->assertSame(['1', '0', '0'], $this->dashboardTotals());
        $this->assertSame($entryBefore, $entry->fresh()->getAttributes());
        $this->assertSame(WorkflowStageType::AwaitingResults, WorkflowStageProgression::currentType($entry->fresh()));
        $this->assertSame('0', PaymentResource::getNavigationBadge());
        Livewire::test(ListPaymentStatusLists::class)->assertCanSeeTableRecords([$entry])->assertTableColumnStateSet('status_payt', 'paid', $entry)->assertTableActionHidden('pay', $entry);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    public function test_a_cleared_static_receipt_remains_used_and_the_paid_application_cannot_be_paid_again(): void
    {
        $entry = $this->entry($this->form());
        $mapping = MefInvoiceCode::query()->create(['mef_code' => 'MEF-CLEAR-STATIC', 'invoice_code' => '120--CLEAR-STATIC']);
        $payment = $this->payment($mapping->invoice_code, $entry);
        $entryBefore = $entry->fresh()->getAttributes();
        $paymentBefore = $payment->fresh()->getAttributes();
        Livewire::test(ListPayments::class)->call('clearDataFromTableSelection', [$payment->id]);
        $this->assertFinancialAttributesUnchanged($paymentBefore, $payment->fresh());
        $this->assertSame($entryBefore, $entry->fresh()->getAttributes());
        Livewire::test(ListPaymentStatusLists::class)->assertCanSeeTableRecords([$entry])->assertTableColumnStateSet('status_payt', 'paid', $entry)->assertTableActionHidden('pay', $entry);
        $validation = Validator::make(['receipt_number' => $mapping->mef_code], ['receipt_number' => [new ValidMefInvoiceReceipt]]);
        $this->assertFalse($validation->passes());
        $this->assertSame(__('payments.validation.receipt_number_unique'), $validation->errors()->first('receipt_number'));
        try {
            app(CandidatePaymentRecorder::class)->record(['custom_form_entry_id' => $entry->id, 'receipt_number' => 'DIFFERENT-RECEIPT', 'amount_usd' => 5, 'datetime_pay' => now()]);
            $this->fail('A menu clear must not allow another payment for the same application.');
        } catch (ValidationException $exception) {
            $this->assertSame(['A payment is already recorded for this application.'], $exception->errors()['receipt_number']);
        }
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_selected_rows_across_pages_are_hidden_without_deleting_other_records_and_selection_state_is_reset(): void
    {
        $payments = collect(range(1, 12))->map(fn (int $i): Payment => $this->payment('SELECT-'.$i));
        $selected = [$payments->first()->id, $payments->last()->id];
        Livewire::test(ListPayments::class)->set('selectedTableRecords', $selected)->set('deselectedTableRecords', [$payments[2]->id])
            ->call('clearDataFromTableSelection', $selected)->assertSet('selectedTableRecords', [])->assertSet('deselectedTableRecords', [])->assertSet('isTrackingDeselectedTableRecords', false);
        $this->assertDatabaseCount('payments', 12);
        $this->assertEqualsCanonicalizing($selected, Payment::query()->whereNotNull('records_cleared_at')->pluck('id')->all());
        $this->assertEqualsCanonicalizing($payments->slice(1, 10)->pluck('id')->all(), PaymentResource::getEloquentQuery()->pluck('id')->all());
        $this->assertSame(12, Payment::query()->where('status_payt', 'paid')->count());
    }

    public function test_no_selection_clears_only_the_current_form_filter_and_excludes_hidden_rows_from_exports_and_totals(): void
    {
        $form = $this->form();
        $first = $this->payment('FILTER-1', $this->entry($form));
        $second = $this->payment('FILTER-2', $this->entry($form));
        $outside = $this->payment('OTHER-1', $this->entry($this->form()));
        $component = Livewire::test(ListPayments::class)->set('tableFilters.payment_filters.form_id', (string) $form->id)->assertCanSeeTableRecords([$first, $second])->assertCanNotSeeTableRecords([$outside])
            ->callAction('clear_data')->set('tableFilters.payment_filters.form_id', null)->assertCanSeeTableRecords([$outside])->assertCanNotSeeTableRecords([$first, $second])->assertTableColumnSummarySet('amount_usd', 'amount_usd_total', 5);
        $exportQuery = new ReflectionMethod(ListPayments::class, 'selectedOrFilteredQuery');
        $this->assertSame([$outside->id], $exportQuery->invoke($component->instance())->pluck('payments.id')->all());
        $this->assertEqualsCanonicalizing([$first->id, $second->id], Payment::query()->whereNotNull('records_cleared_at')->pluck('id')->all());
        $this->assertNull($outside->fresh()->records_cleared_at);
        $this->assertDatabaseCount('payments', 3);
        $this->assertDatabaseCount('custom_form_entries', 3);
    }

    public function test_select_all_can_exclude_rows_and_cannot_clear_records_outside_the_search(): void
    {
        $remove = $this->payment('MATCH-1');
        $excluded = $this->payment('MATCH-2');
        $outside = $this->payment('OTHER-1');
        Livewire::test(ListPayments::class)->searchTable('MATCH')->call('clearDataFromTableSelection', [], true, [$excluded->id]);
        $this->assertNotNull($remove->fresh()->records_cleared_at);
        $this->assertNull($excluded->fresh()->records_cleared_at);
        $this->assertNull($outside->fresh()->records_cleared_at);
        $this->assertDatabaseCount('payments', 3);
    }

    public function test_stale_selected_rows_cannot_bypass_search_or_the_ams_resource_boundary(): void
    {
        $this->payment('MATCH-1');
        $outside = $this->payment('OTHER-1');
        $ams = $this->payment('MATCH-AMS');
        $ams->forceFill(['integration_source' => 'ams'])->saveQuietly();
        $before = Payment::query()->orderBy('id')->get()->map->getAttributes()->all();
        Livewire::test(ListPayments::class)->searchTable('MATCH')->call('clearDataFromTableSelection', [$outside->id, $ams->id]);
        $this->assertSame($before, Payment::query()->orderBy('id')->get()->map->getAttributes()->all());
    }

    public function test_repeating_clear_all_does_not_delete_or_retimestamp_hidden_payments(): void
    {
        $entry = $this->entry($this->form());
        $payment = $this->payment('ONCE', $entry);
        $component = Livewire::test(ListPayments::class)->call('clearDataFromTableSelection', [$payment->id]);
        $after = $payment->fresh()->getAttributes();
        $entryBefore = $entry->fresh()->getAttributes();
        $this->travel(1)->hour();
        $component->call('clearDataFromTableSelection')->call('clearDataFromTableSelection', [$payment->id]);
        $this->assertSame($after, $payment->fresh()->getAttributes());
        $this->assertSame($entryBefore, $entry->fresh()->getAttributes());
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_clearing_an_ambiguous_legacy_payment_only_hides_its_own_row_and_never_changes_either_application(): void
    {
        $form = $this->form();
        $first = $this->entry($form);
        $second = $this->entry($form);
        $legacy = $this->payment('LEGACY-AMBIGUOUS');
        $legacy->forceFill(['form_id' => $form->id, 'custom_form_entry_id' => null])->saveQuietly();
        $before = [$first->fresh()->getAttributes(), $second->fresh()->getAttributes()];
        $paymentBefore = $legacy->fresh()->getAttributes();
        Livewire::test(ListPayments::class)->callAction('clear_data', arguments: ['selectedRecordKeys' => [$legacy->id]])->assertHasNoActionErrors()->assertCanNotSeeTableRecords([$legacy]);
        $this->assertSame($before, [$first->fresh()->getAttributes(), $second->fresh()->getAttributes()]);
        $this->assertFinancialAttributesUnchanged($paymentBefore, $legacy->fresh());
        $this->assertSame('paid', $legacy->fresh()->status_payt);
        Livewire::test(ListPaymentStatusLists::class)->assertCanSeeTableRecords([$first, $second]);
    }

    public function test_clearing_an_unpaid_payment_status_row_keeps_it_available_for_payment_form_options_and_normal_payment(): void
    {
        $form = $this->form();
        $entry = $this->entry($form);
        Livewire::test(ListPaymentStatusLists::class)->call('clearDataFromTableSelection', [$entry->id])->assertCanNotSeeTableRecords([$entry]);
        $clearedAt = $entry->fresh()->getRawOriginal('payment_status_lists_deleted_at');
        $this->assertNotNull($clearedAt);
        $candidateOptions = (new ReflectionMethod(PaymentForm::class, 'unpaidCandidateOptions'))->invoke(null);
        $formOptions = (new ReflectionMethod(PaymentForm::class, 'unpaidFormOptions'))->invoke(null);
        $this->assertArrayHasKey($this->candidate->id, $candidateOptions);
        $this->assertArrayHasKey($form->id, $formOptions);
        $payment = app(CandidatePaymentRecorder::class)->record(['custom_form_entry_id' => $entry->id, 'receipt_number' => 'PAID-AFTER-MENU-CLEAR', 'amount_usd' => 5, 'datetime_pay' => now()]);
        $this->assertSame('paid', $payment->status_payt);
        $this->assertSame($entry->id, $payment->custom_form_entry_id);
        $this->assertSame($clearedAt, $entry->fresh()->getRawOriginal('payment_status_lists_deleted_at'));
        Livewire::test(ListPayments::class)->assertCanSeeTableRecords([$payment]);
        Livewire::test(ListPaymentStatusLists::class)->assertCanNotSeeTableRecords([$entry]);
    }

    public static function usersWithoutClearPermission(): array
    {
        return [['view only', false], ['delete only', true]];
    }

    #[DataProvider('usersWithoutClearPermission')]
    public function test_clear_requires_its_own_permission_even_when_the_method_is_called_directly(string $label, bool $canDelete): void
    {
        $viewer = $this->staff(false, $canDelete);
        $this->actingAs($viewer);
        $payment = $this->payment('DENIED-'.$label);
        $before = $payment->fresh()->getAttributes();
        Livewire::test(ListPayments::class)->assertActionHidden('clear_data')
            ->call('clearDataFromTableSelection', [$payment->id])->assertForbidden();

        $this->assertFalse($viewer->can('ClearData:Payment'));
        $this->assertSame($canDelete, $viewer->can('Delete:Payment'));
        $this->assertSame($before, $payment->fresh()->getAttributes());
        $this->assertDatabaseCount('payments', 1);
    }

    private function assertFinancialAttributesUnchanged(array $before, Payment $after): void
    {
        unset($before['records_cleared_at']);
        $attributes = $after->getAttributes();
        unset($attributes['records_cleared_at']);
        $this->assertSame($before, $attributes);
    }

    private function dashboardTotals(): array
    {
        $stats = (new ReflectionMethod(CashierStatsOverview::class, 'getStats'))->invoke(new CashierStatsOverview);

        return array_map(fn ($stat): string => (string) $stat->getValue(), $stats);
    }

    private function form(): CustomForm
    {
        return CustomForm::query()->create(['name' => 'Clear payment form', 'slug' => 'clear-payment-'.uniqid(), 'is_active' => true, 'requires_payment' => true]);
    }

    private function entry(CustomForm $form, string $status = 'approved'): PaymentStatusList
    {
        return PaymentStatusList::query()->forceCreate(['custom_form_id' => $form->id, 'created_by' => $this->candidate->id, 'review_status' => $status, 'data' => ['registration_status' => $status, 'candidate_status' => 'pending']]);
    }

    private function payment(string $receipt, ?PaymentStatusList $entry = null): Payment
    {
        return Payment::query()->create([
            'custom_form_entry_id' => $entry?->id, 'users_id' => $entry?->created_by ?? $this->candidate->id,
            'form_id' => $entry?->custom_form_id, 'received_by' => auth()->id(), 'receipt_number' => $receipt,
            'status_payt' => 'paid', 'amount_usd' => 5, 'datetime_pay' => now(),
        ]);
    }

    private function staff(bool $canClear, bool $canDelete = false): User
    {
        $role = Role::findOrCreate($canClear ? 'cashier_manager' : 'cashier_officer', 'web');
        foreach (['ViewAny:Payment', 'ViewAny:PaymentStatusList', 'Pay:PaymentStatusList'] as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }
        if ($canClear) {
            $role->givePermissionTo(Permission::findOrCreate('ClearData:Payment', 'web'));
            $role->givePermissionTo(Permission::findOrCreate('ClearData:PaymentStatusList', 'web'));
        }
        if ($canDelete) {
            $role->givePermissionTo(Permission::findOrCreate('Delete:Payment', 'web'));
        }
        $user = $this->user('admin', 'cashier_'.uniqid());
        $user->assignRole($role);

        return $user;
    }

    private function user(string $type, string $name): User
    {
        return User::query()->forceCreate(['registration_type' => $type, 'name' => $name, 'username' => $name, 'email' => $name.'@example.test', 'password' => 'password', 'date_of_birth' => '2000-01-01', 'is_active' => true]);
    }
}
