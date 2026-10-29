<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Payments\Pages\ListPayments;
use App\Filament\Admin\Resources\Payments\PaymentResource;
use App\Filament\Admin\Resources\Payments\Tables\PaymentsTable;
use App\Filament\Admin\Resources\PaymentStatusLists\Pages\ListPaymentStatusLists;
use App\Filament\Admin\Resources\PaymentStatusLists\Tables\PaymentStatusListsTable;
use App\Filament\Widgets\CashierStatsOverview;
use App\Models\Payment;
use App\Models\PaymentStatusList;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkflowNotification;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class PaymentSourceLabelTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;
    private CustomForm $form;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Mail::fake();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        $admin = $this->user('payment_source_admin', 'admin');
        $admin->assignRole(Role::findOrCreate('admin', 'web'));
        $this->actingAs($admin);
        $this->candidate = $this->user('payment_source_candidate', 'student');
        $this->form = CustomForm::query()->create([
            'name' => 'Payment source labels', 'slug' => 'payment-source-labels',
            'is_active' => true, 'requires_payment' => true,
        ]);
        $workflow = WorkflowNotification::query()->create([
            'name' => 'Configured AMS source is not proof of payment',
            'stages' => [['type' => 'stage', 'data' => [
                'stage_name' => 'Payment', 'stage_type' => 'payment',
                'payment_source' => 'ams', 'responsible_role' => ['admin'],
            ]]],
        ]);
        $workflow->forms()->attach($this->form);
    }

    public static function locales(): array
    {
        return [
            ['en', 'Paid via AMS', 'Paid in Candidate system'],
            ['km', 'បង់ប្រាក់តាម AMS', 'បង់ប្រាក់ក្នុងប្រព័ន្ធ Candidate'],
        ];
    }

    #[DataProvider('locales')]
    public function test_model_labels_use_actual_paid_marker_and_leave_nonpaid_states_without_a_source(string $locale, string $amsLabel, string $candidateLabel): void
    {
        app()->setLocale($locale);
        foreach ([['paid', 'ams', $amsLabel, true], ['paid', null, $candidateLabel, false],
            ['unpaid', 'ams', '-', false], ['return', 'ams', '-', false], ['unpaid', null, '-', false]] as [$status, $source, $expected, $isAms]) {
            $payment = (new Payment)->forceFill(['status_payt' => $status, 'integration_source' => $source]);
            $this->assertSame($expected, $payment->paymentSourceLabel());
            $this->assertSame($isAms, $payment->paidViaAms());
            $this->assertSame($status, $payment->status_payt);
            $this->assertSame($source, $payment->integration_source);
        }
    }

    #[DataProvider('locales')]
    public function test_payment_status_list_shows_actual_source_for_exact_application_and_exports_the_same_label(string $locale, string $amsLabel, string $candidateLabel): void
    {
        app()->setLocale($locale);
        $ams = $this->entry();
        $local = $this->entry();
        $unpaid = $this->entry();
        $returned = $this->entry();
        $amsPayment = $this->payment($ams, 'ams');
        $localPayment = $this->payment($local);
        $this->payment($returned, 'ams', 'return');

        $component = Livewire::test(ListPaymentStatusLists::class)
            ->set('tableFilters.payment_status_list_filters.from_date', null)
            ->set('tableFilters.payment_status_list_filters.to_date', null)
            ->set('tableFilters.payment_status_list_filters.received_by', null)
            ->assertCanSeeTableRecords([$ams, $local, $unpaid, $returned])
            ->assertTableColumnStateSet('payment_source', $amsLabel, $ams)
            ->assertTableColumnStateSet('payment_source', $candidateLabel, $local)
            ->assertTableColumnStateSet('payment_source', '-', $unpaid)
            ->assertTableColumnStateSet('payment_source', '-', $returned)
            ->assertTableColumnStateSet('status_payt', 'paid', $ams)
            ->assertTableColumnStateSet('status_payt', 'unpaid', $returned);
        $column = $component->instance()->getTable()->getColumn('payment_source');
        $this->assertFalse($column->isToggledHiddenByDefault());
        $this->assertSame(['total' => 4, 'paid' => 2, 'unpaid' => 2], PaymentStatusListsTable::summaryCounts($component->instance()->getFilteredTableQuery()));

        $records = [$ams->fresh(), $local->fresh(), $unpaid->fresh(), $returned->fresh()];
        $formatted = (new ReflectionMethod(PaymentStatusListsTable::class, 'excelRows'))->invoke(null, $records, ['payment_source']);
        $clean = (new ReflectionMethod(PaymentStatusListsTable::class, 'cleanDataRows'))->invoke(null, $records, ['payment_source']);
        $this->assertSame([[$amsLabel], [$candidateLabel], ['-'], ['-']], array_slice($formatted, 1));
        $this->assertSame([['ams'], ['candidate'], ['-'], ['-']], array_slice($clean, 1));
        $this->assertSame('paid', $amsPayment->fresh()->status_payt);
        $this->assertNull($localPayment->fresh()->integration_source);
        $this->assertDatabaseMissing('payments', ['custom_form_entry_id' => $unpaid->id]);
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    #[DataProvider('locales')]
    public function test_payment_records_exclude_ams_from_menu_queries_options_exports_and_edit_binding(string $locale, string $amsLabel, string $candidateLabel): void
    {
        app()->setLocale($locale);
        $ams = $this->payment($this->entry(), 'ams');
        $ams->update(['amount_usd' => 123]);
        $local = $this->payment($this->entry());
        $legacy = $this->payment($this->entry(), 'legacy');
        $legacy->update(['amount_usd' => 6]);
        $returned = $this->payment($this->entry(), 'ams', 'return');
        $returned->update(['amount_usd' => null, 'amount_kh' => 25000]);
        $amsForm = CustomForm::query()->create([
            'name' => 'AMS-only payment form', 'slug' => 'ams-only-record-form', 'is_active' => true,
        ]);
        $amsEntry = $this->entry();
        $amsEntry->update(['custom_form_id' => $amsForm->id]);
        $uppercaseAms = $this->payment($amsEntry, 'AMS');
        $component = Livewire::test(ListPayments::class)
            ->set('tableFilters.payment_filters.from_date', null)
            ->set('tableFilters.payment_filters.to_date', null)
            ->set('tableFilters.payment_filters.received_by', null)
            ->assertCanSeeTableRecords([$local, $legacy])
            ->assertCanNotSeeTableRecords([$ams, $returned, $uppercaseAms])
            ->assertTableColumnStateSet('payment_source', $candidateLabel, $local)
            ->assertTableColumnStateSet('payment_source', $candidateLabel, $legacy);
        $this->assertFalse($component->instance()->getTable()->getColumn('payment_source')->isToggledHiddenByDefault());
        $this->assertEqualsCanonicalizing([$local->id, $legacy->id], PaymentResource::getEloquentQuery()->pluck('id')->all());
        $this->assertSame('2', PaymentResource::getNavigationBadge());
        $this->assertSame(5, Payment::query()->count(), 'Resource filtering must preserve stored AMS callback payments.');
        $stats = (new ReflectionMethod(CashierStatsOverview::class, 'getStats'))->invoke(new CashierStatsOverview);
        $this->assertSame('4', $stats[1]->getValue(), 'The dashboard keeps both local and AMS paid totals.');
        $this->assertNull(PaymentResource::resolveRecordRouteBinding($ams->id));
        $this->assertNull(PaymentResource::resolveRecordRouteBinding($returned->id));
        $this->assertSame($local->id, PaymentResource::resolveRecordRouteBinding($local->id)?->id);

        $receipts = (new ReflectionMethod(PaymentsTable::class, 'dynamicReceiptOptions'))->invoke(null);
        $this->assertArrayHasKey($local->receipt_number, $receipts);
        $this->assertArrayNotHasKey($ams->receipt_number, $receipts);
        $this->assertArrayNotHasKey($returned->receipt_number, $receipts);
        $forms = (new ReflectionMethod(PaymentsTable::class, 'dynamicFormOptions'))->invoke(null);
        $this->assertArrayHasKey($this->form->id, $forms);
        $this->assertArrayNotHasKey($amsForm->id, $forms);
        $usd = (new ReflectionMethod(PaymentsTable::class, 'dynamicAmountUsdOptions'))->invoke(null);
        $this->assertArrayHasKey(5, $usd);
        $this->assertArrayHasKey(6, $usd);
        $this->assertArrayNotHasKey(123, $usd);
        $this->assertSame([], (new ReflectionMethod(PaymentsTable::class, 'dynamicAmountKhOptions'))->invoke(null));
        $this->assertSame(['paid'], array_keys((new ReflectionMethod(PaymentsTable::class, 'dynamicStatusPaymentOptions'))->invoke(null)));

        $filtered = $component->instance()->getFilteredTableQuery()->get();
        $formatted = (new ReflectionMethod(PaymentsTable::class, 'excelRows'))->invoke(null, $filtered, ['payment_source']);
        $clean = (new ReflectionMethod(PaymentsTable::class, 'cleanDataRows'))->invoke(null, $filtered, ['payment_source']);
        $this->assertSame([[$candidateLabel], [$candidateLabel]], array_slice($formatted, 1));
        $this->assertSame([['candidate'], ['candidate']], array_slice($clean, 1));
        $selected = (new ReflectionMethod(ListPayments::class, 'selectedOrFilteredQuery'))->invoke(
            $component->instance(), [(string) $ams->id, (string) $local->id], false, [],
        );
        $this->assertSame([$local->id], $selected->pluck('id')->all(), 'Forged AMS selection cannot enter export/clear queries.');
        $report = (new ReflectionMethod(PaymentsTable::class, 'reportColumnDefinitions'))->invoke(null);
        $this->assertSame($candidateLabel, $report['payment_source']['value']($local));
        $this->assertSame('ams', $ams->fresh()->integration_source);
        $this->assertSame('paid', $ams->fresh()->status_payt);
    }

    #[DataProvider('locales')]
    public function test_source_filter_uses_latest_actual_payment_and_combines_with_other_filters_and_exports(string $locale, string $amsLabel, string $candidateLabel): void
    {
        app()->setLocale($locale);
        $ams = $this->entry();
        $olderAms = $this->entry();
        $local = $this->entry();
        $legacy = $this->entry();
        $unpaid = $this->entry();
        $returned = $this->entry();
        $paid = $this->payment($ams, 'ams');
        $this->payment($olderAms, 'ams')->update(['datetime_pay' => now()->subDay()]);
        $this->payment($local);
        $this->payment($legacy, 'legacy');
        $history = $this->payment($returned, 'ams');
        $history->replicate()->forceFill([
            'custom_form_entry_id' => null,
            'receipt_number' => 'SOURCE-HISTORY-'.$returned->id,
            'integration_payment_id' => 'external-history-'.$returned->id,
        ])->save();
        // Callbacks update the application's unique payment; owner/form-only history cannot replace it.
        $history->update(['status_payt' => 'return']);
        $otherForm = CustomForm::query()->create([
            'name' => 'Different source form', 'slug' => 'different-payment-source-form',
            'is_active' => true, 'requires_payment' => true,
        ]);
        WorkflowNotification::query()->where('name', 'Configured AMS source is not proof of payment')->firstOrFail()->forms()->attach($otherForm);
        $other = $this->entry();
        $other->update(['custom_form_id' => $otherForm->id]);
        $this->payment($other, 'ams');
        $all = [$ams, $olderAms, $local, $legacy, $unpaid, $returned, $other];

        $component = Livewire::test(ListPaymentStatusLists::class)
            ->set('tableFilters.payment_status_list_filters.from_date', null)
            ->set('tableFilters.payment_status_list_filters.to_date', null)
            ->set('tableFilters.payment_status_list_filters.received_by', null)
            ->assertCanSeeTableRecords($all)
            ->set('tableFilters.payment_status_list_filters.payment_source', 'ams')
            ->assertCanSeeTableRecords([$ams, $olderAms, $other])
            ->assertCanNotSeeTableRecords([$local, $legacy, $unpaid, $returned]);
        $this->assertSame(['total' => 3, 'paid' => 3, 'unpaid' => 0], PaymentStatusListsTable::summaryCounts($component->instance()->getFilteredTableQuery()));

        $component->set('tableFilters.payment_status_list_filters.payment_source', 'candidate')
            ->assertCanSeeTableRecords([$local, $legacy])
            ->assertCanNotSeeTableRecords([$ams, $olderAms, $other, $unpaid, $returned]);
        $this->assertSame(['total' => 2, 'paid' => 2, 'unpaid' => 0], PaymentStatusListsTable::summaryCounts($component->instance()->getFilteredTableQuery()));
        $component->set('tableFilters.payment_status_list_filters.payment_source', null)
            ->assertCanSeeTableRecords($all);
        $this->assertSame(['total' => 7, 'paid' => 5, 'unpaid' => 2], PaymentStatusListsTable::summaryCounts($component->instance()->getFilteredTableQuery()));

        $component->set('tableFilters.payment_status_list_filters.payment_source', 'ams')
            ->set('tableFilters.payment_status_list_filters.status_payt', 'paid')
            ->set('tableFilters.payment_status_list_filters.form_id', (string) $this->form->id)
            ->set('tableFilters.payment_status_list_filters.from_date', now()->toDateString())
            ->set('tableFilters.payment_status_list_filters.to_date', now()->toDateString())
            ->set('tableFilters.payment_status_list_filters.receipt_number', $paid->receipt_number)
            ->assertCanSeeTableRecords([$ams])
            ->assertCanNotSeeTableRecords([$olderAms, $other, $local, $legacy, $unpaid, $returned]);
        $query = $component->instance()->getFilteredTableQuery();
        $this->assertSame(['total' => 1, 'paid' => 1, 'unpaid' => 0], PaymentStatusListsTable::summaryCounts($query));
        $this->assertSame([$ams->id], $query->pluck('custom_form_entries.id')->all());
        $formatted = (new ReflectionMethod(PaymentStatusListsTable::class, 'excelRows'))->invoke(null, $query->get(), ['payment_source']);
        $clean = (new ReflectionMethod(PaymentStatusListsTable::class, 'cleanDataRows'))->invoke(null, $query->get(), ['payment_source']);
        $this->assertSame([[$amsLabel]], array_slice($formatted, 1));
        $this->assertSame([['ams']], array_slice($clean, 1));

        $component->set('tableFilters.payment_status_list_filters.payment_source', 'candidate')
            ->assertCanNotSeeTableRecords($all);
        $component->set('tableFilters.payment_status_list_filters.payment_source', 'ams')
            ->set('tableFilters.payment_status_list_filters.status_payt', 'unpaid')
            ->assertCanNotSeeTableRecords($all);
        $component->set('tableFilters.payment_status_list_filters.payment_source', null)
            ->set('tableFilters.payment_status_list_filters.form_id', null)
            ->set('tableFilters.payment_status_list_filters.from_date', null)
            ->set('tableFilters.payment_status_list_filters.to_date', null)
            ->set('tableFilters.payment_status_list_filters.receipt_number', null)
            ->assertCanSeeTableRecords([$unpaid, $returned])
            ->assertCanNotSeeTableRecords([$ams, $olderAms, $other, $local, $legacy]);
        $this->assertSame(['total' => 2, 'paid' => 0, 'unpaid' => 2], PaymentStatusListsTable::summaryCounts($component->instance()->getFilteredTableQuery()));
        Http::assertNothingSent();
        Mail::assertNothingSent();
    }

    private function entry(): PaymentStatusList
    {
        return PaymentStatusList::query()->forceCreate([
            'custom_form_id' => $this->form->id, 'created_by' => $this->candidate->id,
            'candidate_id' => 'SAME-CANDIDATE-ID', 'review_status' => 'approved',
            'data' => ['registration_status' => 'approved'],
        ]);
    }

    private function payment(PaymentStatusList $entry, ?string $source = null, string $status = 'paid'): Payment
    {
        return Payment::query()->forceCreate([
            'custom_form_entry_id' => $entry->id, 'users_id' => $this->candidate->id,
            'form_id' => $entry->custom_form_id, 'receipt_number' => 'SOURCE-'.$entry->id,
            'status_payt' => $status, 'amount_usd' => 5, 'datetime_pay' => now(),
            'integration_source' => $source,
            'integration_payment_id' => $source ? 'external-source-'.$entry->id : null,
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
