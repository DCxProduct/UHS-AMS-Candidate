<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\PaymentStatusLists\PaymentStatusListResource;
use App\Filament\Admin\Resources\PaymentStatusLists\Pages\ListPaymentStatusLists;
use App\Filament\Admin\Resources\PaymentStatusLists\Tables\PaymentStatusListsTable;
use App\Filament\Admin\Resources\Payments\Schemas\PaymentForm;
use App\Filament\Widgets\CashierStatsOverview;
use App\Models\Payment;
use App\Models\Role;
use App\Models\PaymentStatusList;
use App\Models\User;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as Pdf;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PaymentStatusListVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $candidate;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');
        $role = Role::findOrCreate('admin', 'web');
        foreach (['ViewAny', 'Pay', 'DownloadExcel', 'DownloadPdf'] as $action) {
            $role->givePermissionTo(Permission::findOrCreate($action.':PaymentStatusList', 'web'));
        }
        $admin = $this->user('payment_list_admin', 'admin');
        $admin->assignRole($role);
        $this->candidate = $this->user('payment_list_candidate', 'student');
        $this->actingAs($admin);
    }

    public function test_paid_and_unpaid_applications_remain_visible_with_correct_status_and_pay_action(): void
    {
        $form = $this->form('payment-applications');
        $paid = $this->entry($form);
        $unpaid = $this->entry($form);
        $this->pay($paid);

        $component = $this->paymentListWithoutDateFilters()
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$paid, $unpaid])
            ->assertTableColumnStateSet('status_payt', 'paid', $paid)
            ->assertTableColumnStateSet('status_payt', 'unpaid', $unpaid)
            ->assertTableActionHidden('pay', $paid)
            ->assertTableActionVisible('pay', $unpaid);

        $this->assertSame('2', PaymentStatusListResource::getNavigationBadge());
        $stats = (new ReflectionMethod(CashierStatsOverview::class, 'getStats'))->invoke(new CashierStatsOverview);
        $this->assertSame('2', $stats[0]->getValue());
        $this->assertSame('1', $stats[2]->getValue());
        $query = new ReflectionMethod(ListPaymentStatusLists::class, 'selectedOrFilteredQuery');
        $this->assertEqualsCanonicalizing(
            [$paid->getKey(), $unpaid->getKey()],
            $query->invoke($component->instance())->pluck('id')->all(),
        );

        $this->pay($unpaid);
        $component->call('$refresh')
            ->assertCanSeeTableRecords([$paid, $unpaid])
            ->assertTableColumnStateSet('status_payt', 'paid', $unpaid)
            ->assertTableActionHidden('pay', $unpaid);
        $this->assertSame('2', PaymentStatusListResource::getNavigationBadge());
        $stats = (new ReflectionMethod(CashierStatsOverview::class, 'getStats'))->invoke(new CashierStatsOverview);
        $this->assertSame('2', $stats[0]->getValue());
        $this->assertSame('0', $stats[2]->getValue());
    }

    public function test_existing_eligibility_and_hidden_application_rules_still_apply(): void
    {
        $form = $this->form('eligible-applications');
        $paid = $this->entry($form);
        $unpaid = $this->entry($form);
        $this->pay($paid);
        $pending = $this->entry($form, 'pending');
        $hidden = $this->entry($form, data: [PaymentStatusListResource::HIDDEN_FLAG => true]);
        $noPayment = $this->entry($this->form('no-payment', requiresPayment: false));
        $inactive = $this->entry($this->form('inactive-payment', active: false));
        $profile = $this->entry($this->form('profile'));
        $this->form('empty-active-form');
        $hiddenOnly = $this->entry($this->form('hidden-only-form'), data: [PaymentStatusListResource::HIDDEN_FLAG => true]);
        $pendingOnly = $this->entry($this->form('pending-only-form'), 'pending');

        $this->paymentListWithoutDateFilters()
            ->assertCanSeeTableRecords([$paid, $unpaid])
            ->assertCanNotSeeTableRecords([$pending, $hidden, $noPayment, $inactive, $profile, $hiddenOnly, $pendingOnly]);
        $this->assertSame('2', PaymentStatusListResource::getNavigationBadge());
        $formOptions = new ReflectionMethod(PaymentStatusListsTable::class, 'dynamicFormOptions');
        $this->assertSame(['form:'.$form->getKey()], array_keys($formOptions->invoke(null)));
    }

    public function test_payment_detail_columns_use_the_exact_application_payment_and_empty_values_for_unpaid(): void
    {
        $form = $this->form('payment-detail-columns');
        $paid = $this->entry($form);
        $unpaid = $this->entry($form);
        $receiver = $this->user('detail_receiver', 'admin');
        $receiver->forceFill(['name' => 'Payment Receiver'])->save();
        $this->pay($paid);
        Payment::query()->where('custom_form_entry_id', $paid->getKey())->update([
            'datetime_pay' => '2026-10-27 13:45:00',
            'received_by' => $receiver->getKey(),
            'receipt_number' => 'DETAIL-RECEIPT-001',
        ]);

        $component = $this->paymentListWithoutDateFilters()
            ->assertCanSeeTableRecords([$paid, $unpaid])
            ->assertTableColumnStateSet('datetime_pay', '27-Oct-2026', $paid)
            ->assertTableColumnStateSet('received_by', 'Payment Receiver', $paid)
            ->assertTableColumnStateSet('receipt_number', 'DETAIL-RECEIPT-001', $paid)
            ->assertTableColumnStateSet('datetime_pay', '-', $unpaid)
            ->assertTableColumnStateSet('received_by', '-', $unpaid)
            ->assertTableColumnStateSet('receipt_number', '-', $unpaid);

        Payment::query()->where('custom_form_entry_id', $paid->getKey())->update(['received_by' => null]);
        $component->call('$refresh')->assertTableColumnStateSet('received_by', '-', $paid);
    }

    public function test_form_filter_and_export_selection_include_both_payment_statuses(): void
    {
        $selectedForm = $this->form('selected-form');
        $paid = $this->entry($selectedForm);
        $unpaid = $this->entry($selectedForm);
        $other = $this->entry($this->form('other-form'));
        $this->pay($paid);

        $component = $this->paymentListWithoutDateFilters()
            ->set('tableFilters.payment_status_list_filters.form_id', (string) $selectedForm->getKey())
            ->assertCanSeeTableRecords([$paid, $unpaid])
            ->assertCanNotSeeTableRecords([$other]);
        $query = new ReflectionMethod(ListPaymentStatusLists::class, 'selectedOrFilteredQuery');
        $this->assertEqualsCanonicalizing(
            [$paid->getKey(), $unpaid->getKey()],
            $query->invoke($component->instance())->pluck('id')->all(),
        );
        $this->assertSame(
            [$paid->getKey()],
            $query->invoke($component->instance(), [$paid->getKey(), $other->getKey()])->pluck('id')->all(),
        );
    }

    public function test_payment_entry_selectors_exclude_paid_only_candidates_and_forms_while_the_list_keeps_both(): void
    {
        $paidOnlyForm = $this->form('paid-only-selector-form');
        $paid = $this->entry($paidOnlyForm);
        $this->pay($paid);

        $unpaidCandidate = $this->user('unpaid_selector_candidate', 'student');
        $unpaidForm = $this->form('unpaid-selector-form');
        $unpaid = $this->entry($unpaidForm, candidate: $unpaidCandidate);

        $component = $this->paymentListWithoutDateFilters()
            ->assertCanSeeTableRecords([$paid, $unpaid]);
        $candidateOptions = new ReflectionMethod(PaymentForm::class, 'candidateUserOptions');
        $formOptions = new ReflectionMethod(PaymentForm::class, 'formOptions');

        $this->assertSame([$unpaidCandidate->getKey()], array_keys($candidateOptions->invoke(null, true)));
        $this->assertSame([$unpaidForm->getKey()], array_keys($formOptions->invoke(null, true)));

        $this->pay($unpaid);
        $component->call('$refresh')->assertCanSeeTableRecords([$paid, $unpaid]);
        $this->assertSame([], $candidateOptions->invoke(null, true));
        $this->assertSame([], $formOptions->invoke(null, true));
    }

    public function test_payment_status_filter_composes_with_form_filter_and_export_selection_and_can_be_cleared(): void
    {
        $form = $this->form('status-filter-form');
        $otherForm = $this->form('other-status-filter-form');
        $paid = $this->entry($form);
        $unpaid = $this->entry($form);
        $otherPaid = $this->entry($otherForm);
        $otherUnpaid = $this->entry($otherForm);
        $this->pay($paid);
        $this->pay($otherPaid);
        $query = new ReflectionMethod(ListPaymentStatusLists::class, 'selectedOrFilteredQuery');

        $component = $this->paymentListWithoutDateFilters()
            ->assertCanSeeTableRecords([$paid, $unpaid, $otherPaid, $otherUnpaid])
            ->set('tableFilters.payment_status_list_filters.status_payt', 'paid')
            ->assertCanSeeTableRecords([$paid, $otherPaid])
            ->assertCanNotSeeTableRecords([$unpaid, $otherUnpaid]);
        $this->assertEqualsCanonicalizing(
            [$paid->getKey(), $otherPaid->getKey()],
            $query->invoke($component->instance())->pluck('id')->all(),
        );

        $component->set('tableFilters.payment_status_list_filters.form_id', (string) $form->getKey())
            ->assertCanSeeTableRecords([$paid])
            ->assertCanNotSeeTableRecords([$unpaid, $otherPaid, $otherUnpaid]);
        $this->assertSame(
            [$paid->getKey()],
            $query->invoke($component->instance(), [$paid->getKey(), $unpaid->getKey(), $otherPaid->getKey()])->pluck('id')->all(),
        );

        $component->set('tableFilters.payment_status_list_filters.status_payt', 'unpaid')
            ->assertCanSeeTableRecords([$unpaid])
            ->assertCanNotSeeTableRecords([$paid, $otherPaid, $otherUnpaid]);
        $this->assertSame([$unpaid->getKey()], $query->invoke($component->instance())->pluck('id')->all());

        $component->set('tableFilters.payment_status_list_filters.status_payt', null)
            ->assertCanSeeTableRecords([$paid, $unpaid])
            ->assertCanNotSeeTableRecords([$otherPaid, $otherUnpaid])
            ->set('tableFilters.payment_status_list_filters.form_id', null)
            ->set('tableFilters.payment_status_list_filters.status_payt', '')
            ->assertCanSeeTableRecords([$paid, $unpaid, $otherPaid, $otherUnpaid]);
    }

    public function test_payment_status_filter_matches_latest_payment_badge_for_legacy_history(): void
    {
        // Emulate payment history saved before the one-payment-per-application constraint.
        Schema::table('payments', fn (Blueprint $table) => $table->dropUnique('payments_application_unique'));
        $form = $this->form('latest-status-filter-form');
        $latestUnpaid = $this->entry($form);
        $latestPaid = $this->entry($form);
        $nullStatus = $this->entry($form);
        $otherStatus = $this->entry($form);
        $missing = $this->entry($form);
        $this->pay($latestUnpaid, 'paid', '-old');
        $this->pay($latestUnpaid, 'unpaid', '-new');
        $this->pay($latestPaid, 'unpaid', '-old');
        $this->pay($latestPaid, 'PAID', '-new');
        $this->pay($nullStatus, null);
        $this->pay($otherStatus, 'pending');

        $component = $this->paymentListWithoutDateFilters()
            ->assertTableColumnStateSet('status_payt', 'unpaid', $latestUnpaid)
            ->assertTableColumnStateSet('status_payt', 'paid', $latestPaid)
            ->assertTableColumnStateSet('status_payt', 'unpaid', $nullStatus)
            ->assertTableColumnStateSet('status_payt', 'unpaid', $otherStatus)
            ->assertTableColumnStateSet('status_payt', 'unpaid', $missing)
            ->set('tableFilters.payment_status_list_filters.status_payt', 'paid')
            ->assertCanSeeTableRecords([$latestPaid])
            ->assertCanNotSeeTableRecords([$latestUnpaid, $nullStatus, $otherStatus, $missing]);

        $component->set('tableFilters.payment_status_list_filters.status_payt', 'unpaid')
            ->assertCanSeeTableRecords([$latestUnpaid, $nullStatus, $otherStatus, $missing])
            ->assertCanNotSeeTableRecords([$latestPaid])
            ->set('tableFilters.payment_status_list_filters.status_payt', null)
            ->assertCanSeeTableRecords([$latestUnpaid, $latestPaid, $nullStatus, $otherStatus, $missing]);
    }

    public function test_payment_detail_filters_include_date_boundaries_compose_and_clear_without_hiding_unpaid_by_default(): void
    {
        $form = $this->form('detail-filter-form');
        $receiver = $this->user('filter_receiver', 'admin');
        $excludedReceiver = $this->user('excluded_filter_receiver', 'admin');
        $before = $this->entry($form);
        $start = $this->entry($form);
        $end = $this->entry($form);
        $after = $this->entry($form);
        $unpaid = $this->entry($form);
        $otherForm = $this->entry($this->form('other-detail-filter-form'));
        $hidden = $this->entry($form, data: [PaymentStatusListResource::HIDDEN_FLAG => true]);

        foreach ([
            [$before, '2026-10-26 23:59:59', 'DETAIL-BEFORE'],
            [$start, '2026-10-27 00:00:00', 'DETAIL-START'],
            [$end, '2026-10-27 23:59:59', 'DETAIL-END'],
            [$after, '2026-10-28 00:00:00', 'DETAIL-AFTER'],
            [$otherForm, '2026-10-27 12:00:00', 'DETAIL-OTHER-FORM'],
            [$hidden, '2026-10-27 12:00:00', 'DETAIL-HIDDEN'],
        ] as [$entry, $date, $receipt]) {
            $this->pay($entry);
            Payment::query()->where('custom_form_entry_id', $entry->getKey())->update([
                'datetime_pay' => $date,
                'received_by' => $entry->is($hidden) ? $excludedReceiver->getKey() : $receiver->getKey(),
                'receipt_number' => $receipt,
            ]);
        }
        Payment::query()->create([
            'users_id' => $this->candidate->getKey(),
            'form_id' => $form->getKey(),
            'received_by' => $excludedReceiver->getKey(),
            'receipt_number' => 'DETAIL-UNRELATED',
            'status_payt' => 'paid',
            'amount_usd' => 5,
            'datetime_pay' => '2026-10-27 12:00:00',
        ]);

        $receivers = new ReflectionMethod(PaymentStatusListsTable::class, 'dynamicReceiverOptions');
        $receipts = new ReflectionMethod(PaymentStatusListsTable::class, 'dynamicReceiptOptions');
        $this->assertEqualsCanonicalizing([auth()->id(), $receiver->getKey()], array_keys($receivers->invoke(null)));
        $this->assertEqualsCanonicalizing(
            ['DETAIL-BEFORE', 'DETAIL-START', 'DETAIL-END', 'DETAIL-AFTER', 'DETAIL-OTHER-FORM'],
            array_keys($receipts->invoke(null)),
        );

        $component = $this->paymentListWithoutDateFilters()
            ->assertCanSeeTableRecords([$before, $start, $end, $after, $unpaid, $otherForm])
            ->set('tableFilters.payment_status_list_filters.from_date', '2026-10-27')
            ->set('tableFilters.payment_status_list_filters.to_date', '2026-10-27')
            ->assertCanSeeTableRecords([$start, $end, $otherForm])
            ->assertCanNotSeeTableRecords([$before, $after, $unpaid])
            ->set('tableFilters.payment_status_list_filters.to_date', null)
            ->assertCanSeeTableRecords([$start, $end, $after, $otherForm])
            ->assertCanNotSeeTableRecords([$before, $unpaid])
            ->set('tableFilters.payment_status_list_filters.from_date', null)
            ->set('tableFilters.payment_status_list_filters.to_date', '2026-10-27')
            ->assertCanSeeTableRecords([$before, $start, $end, $otherForm])
            ->assertCanNotSeeTableRecords([$after, $unpaid])
            ->set('tableFilters.payment_status_list_filters.to_date', null)
            ->assertCanSeeTableRecords([$before, $start, $end, $after, $unpaid, $otherForm])
            ->set('tableFilters.payment_status_list_filters.form_id', 'form:'.$form->getKey())
            ->set('tableFilters.payment_status_list_filters.status_payt', 'paid')
            ->set('tableFilters.payment_status_list_filters.received_by', (string) $receiver->getKey())
            ->set('tableFilters.payment_status_list_filters.receipt_number', 'DETAIL-END')
            ->assertCanSeeTableRecords([$end])
            ->assertCanNotSeeTableRecords([$before, $start, $after, $unpaid, $otherForm]);
        $query = new ReflectionMethod(ListPaymentStatusLists::class, 'selectedOrFilteredQuery');
        $this->assertSame([$end->getKey()], $query->invoke($component->instance())->pluck('id')->all());
        $this->assertSame(
            [$end->getKey()],
            $query->invoke($component->instance(), [$before->getKey(), $end->getKey(), $unpaid->getKey(), $otherForm->getKey()])->pluck('id')->all(),
        );

        $component->set('tableFilters.payment_status_list_filters.form_id', null)
            ->set('tableFilters.payment_status_list_filters.status_payt', null)
            ->set('tableFilters.payment_status_list_filters.received_by', null)
            ->set('tableFilters.payment_status_list_filters.receipt_number', null)
            ->assertCanSeeTableRecords([$before, $start, $end, $after, $unpaid, $otherForm]);
    }

    public function test_payment_detail_filters_and_options_use_latest_payment_in_legacy_history(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropUnique('payments_application_unique'));
        $form = $this->form('legacy-detail-filter-form');
        $first = $this->entry($form);
        $second = $this->entry($form);
        $oldReceiver = $this->user('old_detail_receiver', 'admin');
        $newReceiver = $this->user('new_detail_receiver', 'admin');

        foreach ([
            [$first, '-old', '2026-10-27 12:00:00', $oldReceiver],
            [$first, '-new', '2026-10-28 12:00:00', $newReceiver],
            [$second, '-old', '2026-10-28 12:00:00', $oldReceiver],
            [$second, '-new', '2026-10-27 12:00:00', $newReceiver],
        ] as [$entry, $suffix, $date, $receiver]) {
            $this->pay($entry, suffix: $suffix);
            Payment::query()->where('receipt_number', 'LIST-PAID-'.$entry->getKey().$suffix)->update([
                'datetime_pay' => $date,
                'received_by' => $receiver->getKey(),
            ]);
        }

        $receivers = new ReflectionMethod(PaymentStatusListsTable::class, 'dynamicReceiverOptions');
        $receipts = new ReflectionMethod(PaymentStatusListsTable::class, 'dynamicReceiptOptions');
        $this->assertEqualsCanonicalizing([auth()->id(), $newReceiver->getKey()], array_keys($receivers->invoke(null)));
        $this->assertEqualsCanonicalizing(
            ['LIST-PAID-'.$first->getKey().'-new', 'LIST-PAID-'.$second->getKey().'-new'],
            array_keys($receipts->invoke(null)),
        );

        $this->paymentListWithoutDateFilters()
            ->set('tableFilters.payment_status_list_filters.from_date', '2026-10-27')
            ->set('tableFilters.payment_status_list_filters.to_date', '2026-10-27')
            ->assertCanSeeTableRecords([$second])
            ->assertCanNotSeeTableRecords([$first])
            ->set('tableFilters.payment_status_list_filters.from_date', null)
            ->set('tableFilters.payment_status_list_filters.to_date', null)
            ->set('tableFilters.payment_status_list_filters.received_by', (string) $oldReceiver->getKey())
            ->assertCanNotSeeTableRecords([$first, $second])
            ->set('tableFilters.payment_status_list_filters.received_by', (string) $newReceiver->getKey())
            ->assertCanSeeTableRecords([$first, $second])
            ->set('tableFilters.payment_status_list_filters.receipt_number', 'LIST-PAID-'.$first->getKey().'-old')
            ->assertCanNotSeeTableRecords([$first, $second])
            ->set('tableFilters.payment_status_list_filters.receipt_number', 'LIST-PAID-'.$first->getKey().'-new')
            ->assertCanSeeTableRecords([$first])
            ->assertCanNotSeeTableRecords([$second]);
    }

    public function test_excel_and_pdf_downloads_follow_the_current_payment_status_filter(): void
    {
        $form = $this->form('export-payment-status');
        $paid = $this->entry($form, data: ['full_name_kh' => 'EXPORT-PAID-ROW']);
        $unpaid = $this->entry($form, data: ['full_name_kh' => 'EXPORT-UNPAID-ROW']);
        $this->pay($paid);
        $pdfHtml = '';
        Pdf::shouldReceive('loadHTML')->times(3)
            ->andReturnUsing(function (string $html) use (&$pdfHtml) {
                $pdfHtml = $html;

                return \Mockery::mock(['output' => '%PDF-filtered']);
            });

        $component = $this->paymentListWithoutDateFilters();

        foreach (['paid', 'unpaid', null] as $status) {
            $component->set('tableFilters.payment_status_list_filters.status_payt', $status);
            // A selection left over from the other status must still obey the active filter.
            $selected = $status === 'unpaid' ? [$paid->getKey(), $unpaid->getKey()] : [];
            $component->call('downloadExcelFromTableSelection', $selected)->assertFileDownloaded();
            $path = tempnam(sys_get_temp_dir(), 'payment-status-export-');

            try {
                file_put_contents($path, base64_decode($component->effects['download']['content'], true));
                $zip = new \ZipArchive;
                $this->assertTrue($zip->open($path) === true);
                $sheets = [$zip->getFromName('xl/worksheets/sheet1.xml'), $zip->getFromName('xl/worksheets/sheet2.xml')];
                $zip->close();

                foreach ($sheets as $sheet) {
                    foreach (['paid' => 'EXPORT-PAID-ROW', 'unpaid' => 'EXPORT-UNPAID-ROW'] as $rowStatus => $name) {
                        if ($status === null || $status === $rowStatus) {
                            $this->assertStringContainsString($name, $sheet);
                        } else {
                            $this->assertStringNotContainsString($name, $sheet);
                        }
                    }
                }
            } finally {
                unlink($path);
            }

            $component->call('downloadPdfFromTableSelection', $selected)
                ->assertFileDownloaded(content: '%PDF-filtered');

            foreach (['paid' => 'EXPORT-PAID-ROW', 'unpaid' => 'EXPORT-UNPAID-ROW'] as $rowStatus => $name) {
                if ($status === null || $status === $rowStatus) {
                    $this->assertStringContainsString($name, $pdfHtml);
                } else {
                    $this->assertStringNotContainsString($name, $pdfHtml);
                }
            }
        }
    }

    public function test_payment_date_filters_are_initially_blank_and_can_be_selected_or_cleared(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-27 12:00:00', config('app.timezone')));

        try {
            $form = $this->form('today-default-payments');
            $start = $this->entry($form);
            $end = $this->entry($form);
            $yesterday = $this->entry($form);
            $unpaid = $this->entry($form);

            foreach ([
                [$start, '2026-10-27 00:00:00'],
                [$end, '2026-10-27 23:59:59'],
                [$yesterday, '2026-10-26 23:59:59'],
            ] as [$entry, $date]) {
                $this->pay($entry);
                Payment::query()->where('custom_form_entry_id', $entry->getKey())->update(['datetime_pay' => $date]);
            }

            Livewire::test(ListPaymentStatusLists::class)
                ->assertSet('tableFilters.payment_status_list_filters.from_date', null)
                ->assertSet('tableFilters.payment_status_list_filters.to_date', null)
                ->assertSet('tableFilters.payment_status_list_filters.received_by', null)
                ->assertCanSeeTableRecords([$start, $end, $yesterday, $unpaid])
                ->set('tableFilters.payment_status_list_filters.from_date', '2026-10-27')
                ->set('tableFilters.payment_status_list_filters.to_date', '2026-10-27')
                ->assertCanSeeTableRecords([$start, $end])
                ->assertCanNotSeeTableRecords([$yesterday, $unpaid])
                ->set('tableFilters.payment_status_list_filters.from_date', '2026-10-26')
                ->assertCanSeeTableRecords([$start, $end, $yesterday])
                ->set('tableFilters.payment_status_list_filters.to_date', '2026-10-26')
                ->assertCanSeeTableRecords([$yesterday])
                ->assertCanNotSeeTableRecords([$start, $end])
                ->set('tableFilters.payment_status_list_filters.from_date', null)
                ->set('tableFilters.payment_status_list_filters.to_date', null)
                ->assertCanSeeTableRecords([$start, $end, $yesterday, $unpaid]);
        } finally {
            $this->travelBack();
        }
    }

    public function test_receiver_filter_is_initially_blank_and_can_be_selected_or_cleared_without_resetting_dates(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-27 12:00:00', config('app.timezone')));

        try {
            $form = $this->form('receiver-default-payments');
            $currentUser = auth()->user();
            $otherReceiver = $this->user('other_default_receiver', 'admin');
            $unrelatedReceiver = $this->user('unrelated_default_receiver', 'admin');
            $own = $this->entry($form);
            $other = $this->entry($form);
            $otherUnpaid = $this->entry($form);
            $yesterday = $this->entry($form);
            $missing = $this->entry($form);

            foreach ([[$other, 'paid'], [$otherUnpaid, 'unpaid'], [$yesterday, 'paid']] as [$entry, $status]) {
                $this->pay($entry, $status);
                Payment::query()->where('custom_form_entry_id', $entry->getKey())->update([
                    'received_by' => $otherReceiver->getKey(),
                    'datetime_pay' => $entry->is($yesterday) ? '2026-10-26 12:00:00' : '2026-10-27 12:00:00',
                ]);
            }
            Payment::query()->create([
                'users_id' => $this->candidate->getKey(),
                'form_id' => $form->getKey(),
                'received_by' => $unrelatedReceiver->getKey(),
                'receipt_number' => 'UNRELATED-DEFAULT-RECEIVER',
                'status_payt' => 'paid',
                'amount_usd' => 5,
                'datetime_pay' => now(),
            ]);

            // The current user remains selectable even before receiving any payment.
            $options = (new ReflectionMethod(PaymentStatusListsTable::class, 'dynamicReceiverOptions'))->invoke(null);
            $this->assertEqualsCanonicalizing([$currentUser->getKey(), $otherReceiver->getKey()], array_keys($options));
            $this->pay($own);

            Livewire::test(ListPaymentStatusLists::class)
                ->assertSet('tableFilters.payment_status_list_filters.from_date', null)
                ->assertSet('tableFilters.payment_status_list_filters.to_date', null)
                ->assertSet('tableFilters.payment_status_list_filters.received_by', null)
                ->assertCanSeeTableRecords([$own, $other, $otherUnpaid, $yesterday, $missing])
                ->set('tableFilters.payment_status_list_filters.from_date', '2026-10-27')
                ->set('tableFilters.payment_status_list_filters.to_date', '2026-10-27')
                ->assertCanSeeTableRecords([$own, $other, $otherUnpaid])
                ->assertCanNotSeeTableRecords([$yesterday, $missing])
                ->set('tableFilters.payment_status_list_filters.received_by', (string) $currentUser->getKey())
                ->assertCanSeeTableRecords([$own])
                ->assertCanNotSeeTableRecords([$other, $otherUnpaid, $yesterday, $missing])
                ->set('tableFilters.payment_status_list_filters.received_by', null)
                ->assertCanSeeTableRecords([$own, $other, $otherUnpaid])
                ->assertCanNotSeeTableRecords([$yesterday, $missing])
                ->set('tableFilters.payment_status_list_filters.received_by', 'all')
                ->assertCanSeeTableRecords([$own, $other, $otherUnpaid])
                ->assertCanNotSeeTableRecords([$yesterday, $missing])
                ->set('tableFilters.payment_status_list_filters.received_by', (string) $otherReceiver->getKey())
                ->assertCanSeeTableRecords([$other, $otherUnpaid])
                ->assertCanNotSeeTableRecords([$own, $yesterday, $missing])
                ->set('tableFilters.payment_status_list_filters.received_by', '')
                ->assertCanSeeTableRecords([$own, $other, $otherUnpaid])
                ->assertCanNotSeeTableRecords([$yesterday, $missing])
                ->assertSet('tableFilters.payment_status_list_filters.from_date', fn ($value): bool => \Illuminate\Support\Carbon::parse($value)->toDateString() === '2026-10-27')
                ->assertSet('tableFilters.payment_status_list_filters.to_date', fn ($value): bool => \Illuminate\Support\Carbon::parse($value)->toDateString() === '2026-10-27')
                ->set('tableFilters.payment_status_list_filters.from_date', null)
                ->set('tableFilters.payment_status_list_filters.to_date', null)
                ->assertCanSeeTableRecords([$own, $other, $otherUnpaid, $yesterday, $missing]);
        } finally {
            $this->travelBack();
        }
    }

    public function test_payment_totals_summary_follows_all_filters_and_search_including_empty_results(): void
    {
        $form = $this->form('summary-filter-form');
        $receiver = $this->user('summary_receiver', 'admin');
        $paid = $this->entry($form, data: ['full_name_kh' => 'SUMMARY-SEARCH-ROW']);
        $later = $this->entry($form);
        $unpaid = $this->entry($form);
        $missing = $this->entry($form);
        $other = $this->entry($this->form('summary-other-form'));
        $hidden = $this->entry($form, data: [PaymentStatusListResource::HIDDEN_FLAG => true]);
        $pending = $this->entry($form, 'pending');

        foreach ([[$paid, 'paid'], [$later, 'paid'], [$unpaid, 'unpaid'], [$other, 'paid'], [$hidden, 'paid'], [$pending, 'paid']] as [$entry, $status]) {
            $this->pay($entry, $status);
            Payment::query()->where('custom_form_entry_id', $entry->getKey())->update([
                'datetime_pay' => $entry->is($other) || $entry->is($later) ? '2026-10-28 12:00:00' : '2026-10-27 12:00:00',
                'received_by' => $entry->is($paid) ? $receiver->getKey() : auth()->id(),
                'amount_usd' => $entry->is($other) ? null : ($entry->is($paid) ? 12.5 : ($entry->is($later) ? 20 : 999)),
                'amount_kh' => $entry->is($other) ? 8000 : ($entry->is($paid) ? null : ($entry->is($later) ? 0 : 9999)),
            ]);
        }

        $component = $this->paymentListWithoutDateFilters()
            ->assertSee('data-payment-status-summary', false)
            ->assertSee('Summary')
            ->assertSee('Total USD')
            ->assertSee('Total KHR')
            ->assertSee('32.50')
            ->assertSee('8,000.00');
        $totals = fn (): array => PaymentStatusListsTable::summaryTotals($component->instance()->getFilteredTableQuery());
        $this->assertSame(['usd' => 32.5, 'khr' => 8000.0], $totals());

        $component->set('tableFilters.payment_status_list_filters.status_payt', 'paid');
        $this->assertSame(['usd' => 32.5, 'khr' => 8000.0], $totals());
        $component->set('tableFilters.payment_status_list_filters.status_payt', 'unpaid');
        $this->assertSame(['usd' => 0.0, 'khr' => 0.0], $totals());
        $component->set('tableFilters.payment_status_list_filters.status_payt', null)
            ->set('tableFilters.payment_status_list_filters.form_id', 'form:'.$form->getKey());
        $this->assertSame(['usd' => 32.5, 'khr' => 0.0], $totals());

        $component->set('tableSearch', 'SUMMARY-SEARCH-ROW');
        $this->assertSame(['usd' => 12.5, 'khr' => 0.0], $totals());
        $component->set('tableSearch', 'SUMMARY-NO-MATCH');
        $this->assertSame(['usd' => 0.0, 'khr' => 0.0], $totals());
        $component->set('tableSearch', '')
            ->set('tableFilters.payment_status_list_filters.from_date', '2026-10-27')
            ->set('tableFilters.payment_status_list_filters.to_date', '2026-10-27');
        $this->assertSame(['usd' => 12.5, 'khr' => 0.0], $totals());

        $component->set('tableFilters.payment_status_list_filters.received_by', (string) $receiver->getKey());
        $this->assertSame(['usd' => 12.5, 'khr' => 0.0], $totals());
        $component->set('tableFilters.payment_status_list_filters.receipt_number', 'LIST-PAID-'.$unpaid->getKey());
        $this->assertSame(['usd' => 0.0, 'khr' => 0.0], $totals());
        $component->set('tableFilters.payment_status_list_filters.received_by', null);
        $this->assertSame(['usd' => 0.0, 'khr' => 0.0], $totals());
        $component->set('tableFilters.payment_status_list_filters.receipt_number', 'LIST-PAID-'.$paid->getKey());
        $this->assertSame(['usd' => 12.5, 'khr' => 0.0], $totals());
    }

    public function test_payment_totals_summary_includes_every_page_using_only_latest_paid_payments(): void
    {
        // Legacy history must use the latest payment for each application in the summary too.
        Schema::table('payments', fn (Blueprint $table) => $table->dropUnique('payments_application_unique'));
        $form = $this->form('summary-all-pages');
        $entries = [];

        for ($index = 0; $index < 12; $index++) {
            $entries[$index] = $this->entry($form);
            if ($index >= 2 && $index <= 5) {
                $this->pay($entries[$index]);
            }
        }
        $this->pay($entries[0], 'paid', '-old');
        $this->pay($entries[0], 'unpaid', '-new');
        $this->pay($entries[1], 'unpaid', '-old');
        $this->pay($entries[1], 'PAID', '-new');
        Payment::query()->where('custom_form_entry_id', $entries[0]->getKey())->update(['amount_usd' => 1000, 'amount_kh' => 40000]);
        Payment::query()->where('receipt_number', 'LIST-PAID-'.$entries[1]->getKey().'-new')->update(['amount_usd' => 7.25, 'amount_kh' => 8000]);

        $component = $this->paymentListWithoutDateFilters()
            ->assertSee('data-payment-status-summary', false)
            ->assertSee('27.25')
            ->assertSee('8,000.00');
        $this->assertSame(10, $component->instance()->getTableRecords()->count());
        $this->assertSame(
            ['usd' => 27.25, 'khr' => 8000.0],
            PaymentStatusListsTable::summaryTotals($component->instance()->getFilteredTableQuery()),
        );

        $component->set('tableFilters.payment_status_list_filters.status_payt', 'paid');
        $this->assertSame(
            ['usd' => 27.25, 'khr' => 8000.0],
            PaymentStatusListsTable::summaryTotals($component->instance()->getFilteredTableQuery()),
        );
    }

    private function paymentListWithoutDateFilters()
    {
        return Livewire::test(ListPaymentStatusLists::class)
            ->set('tableFilters.payment_status_list_filters.from_date', null)
            ->set('tableFilters.payment_status_list_filters.to_date', null)
            ->set('tableFilters.payment_status_list_filters.received_by', null);
    }

    private function form(string $slug, bool $requiresPayment = true, bool $active = true): CustomForm
    {
        return CustomForm::query()->create([
            'name' => $slug,
            'slug' => $slug,
            'is_active' => $active,
            'requires_payment' => $requiresPayment,
        ]);
    }

    private function entry(CustomForm $form, string $status = 'approved', array $data = [], ?User $candidate = null): PaymentStatusList
    {
        return PaymentStatusList::query()->forceCreate([
            'custom_form_id' => $form->getKey(),
            'created_by' => ($candidate ?? $this->candidate)->getKey(),
            'review_status' => $status,
            'data' => ['registration_status' => $status, ...$data],
        ]);
    }

    private function pay(PaymentStatusList $entry, ?string $status = 'paid', string $suffix = ''): void
    {
        Payment::query()->create([
            'custom_form_entry_id' => $entry->getKey(),
            'users_id' => $entry->created_by,
            'form_id' => $entry->custom_form_id,
            'receipt_number' => 'LIST-PAID-'.$entry->getKey().$suffix,
            'status_payt' => $status,
            'amount_usd' => 5,
            'datetime_pay' => now(),
        ]);
    }

    private function user(string $username, string $type): User
    {
        return User::query()->forceCreate([
            'registration_type' => $type,
            'name' => $username,
            'username' => $username,
            'email' => $username.'@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => 'password',
            'is_active' => true,
        ]);
    }
}
