<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\PaymentStatusLists\Tables\PaymentStatusListsTable;
use App\Filament\Admin\Resources\Payments\Pages\ListPayments;
use App\Filament\Admin\Resources\Payments\Tables\PaymentsTable;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\Role;
use App\Models\User;
use App\Support\OfficialReport;
use App\Support\TablePdfExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as Pdf;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PaymentTotalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_status_list_exports_omit_the_record_count_summary(): void
    {
        $exportColumns = $this->invokeProtectedStatic(PaymentStatusListsTable::class, 'exportColumnDefinitions', []);
        $reportColumnKeys = array_keys($exportColumns);
        $sheet = $this->invokeProtectedStatic(PaymentStatusListsTable::class, 'reportSheet', [[], $reportColumnKeys]);
        $spreadsheetRows = serialize($sheet['rows']);

        $this->assertStringNotContainsString(__('payments.export.total_records'), $spreadsheetRows);
        $this->assertNotContains(__('payment_status_lists.columns.academic_year'), $sheet['rows'][10]);
        $this->assertNotContains(__('payment_status_lists.columns.phone_number'), $sheet['rows'][10]);
        $this->assertNotContains(__('payment_status_lists.columns.payment_status'), $sheet['rows'][10]);
        $this->assertContains(__('payment_status_lists.columns.date_of_birth'), $sheet['rows'][10]);
        $this->assertArrayNotHasKey('academic_year', $exportColumns);
        $this->assertArrayNotHasKey('phone_number', $exportColumns);
        $this->assertArrayNotHasKey('status_payt', $exportColumns);

        $dateOfBirthColumn = array_search('date_of_birth', $reportColumnKeys, true);
        $this->assertSame(
            7,
            $sheet['options']['cellStyles'][OfficialReport::columnName($dateOfBirthColumn + 1).'11'],
        );

        $pdfRows = $this->invokeProtectedStatic(PaymentStatusListsTable::class, 'excelRows', [[], null]);
        $this->assertNotContains(__('payment_status_lists.columns.academic_year'), $pdfRows[0]);
        $this->assertNotContains(__('payment_status_lists.columns.phone_number'), $pdfRows[0]);
        $this->assertNotContains(__('payment_status_lists.columns.payment_status'), $pdfRows[0]);

        $cleanRows = $this->invokeProtectedStatic(PaymentStatusListsTable::class, 'cleanDataRows', [[], $reportColumnKeys]);
        $this->assertNotContains('phone_number', $cleanRows[0]);
        $this->assertNotContains('status_payt', $cleanRows[0]);

        $pdfHtml = $this->invokeProtectedStatic(TablePdfExporter::class, 'html', [
            ['Application'],
            [['Candidate']],
            'Payment Status List',
            [],
            ['layout' => 'official-report', 'hide_summary' => true],
        ]);

        $this->assertStringContainsString('<td>Candidate</td>', $pdfHtml);
        $this->assertStringNotContainsString('<table class="summary-table">', $pdfHtml);
    }

    public function test_payment_table_summarizes_usd_and_khr_amounts(): void
    {
        $this->createPaymentViewer();

        $firstPayment = Payment::query()->create([
            'receipt_number' => 'TOTAL-001',
            'amount_usd' => '5.00',
            'amount_kh' => '20500.00',
            'datetime_pay' => now(),
        ]);
        Payment::query()->create([
            'receipt_number' => 'TOTAL-002',
            'amount_usd' => '10.00',
            'amount_kh' => '41000.00',
            'datetime_pay' => now(),
        ]);

        Livewire::test(ListPayments::class)
            ->set('tableFilters.payment_filters.received_by', null)
            ->assertSeeText('5.00$')
            ->assertTableColumnSummarizerExists('amount_usd', 'amount_usd_total')
            ->assertTableColumnSummarizerExists('amount_kh', 'amount_khr_total')
            ->assertTableColumnSummarySet('amount_usd', 'amount_usd_total', 15)
            ->assertTableColumnSummarySet('amount_kh', 'amount_khr_total', 61500)
            ->assertSeeText('15.00$');
    }

    public function test_payment_totals_follow_table_search(): void
    {
        $this->createPaymentViewer();

        Payment::query()->create([
            'receipt_number' => 'MATCH-001',
            'amount_usd' => '5.00',
            'amount_kh' => '20500.00',
            'datetime_pay' => now(),
        ]);
        Payment::query()->create([
            'receipt_number' => 'OTHER-001',
            'amount_usd' => '10.00',
            'amount_kh' => '41000.00',
            'datetime_pay' => now(),
        ]);

        $component = Livewire::test(ListPayments::class)
            ->set('tableFilters.payment_filters.received_by', null)
            ->set('tableSearch', 'match-001')
            ->assertTableColumnSummarySet('amount_usd', 'amount_usd_total', 5)
            ->assertTableColumnSummarySet('amount_kh', 'amount_khr_total', 20500);

        $this->assertFalse($component->instance()->getTable()->hasPageSummary());
        $this->assertTrue($component->instance()->getTable()->hasAllTableSummary());
    }

    public function test_payment_totals_follow_receipt_filter(): void
    {
        $this->createPaymentViewer();

        Payment::query()->create([
            'receipt_number' => 'FILTER-001',
            'amount_usd' => '5.00',
            'amount_kh' => '20500.00',
            'datetime_pay' => now(),
        ]);
        Payment::query()->create([
            'receipt_number' => 'OTHER-001',
            'amount_usd' => '10.00',
            'amount_kh' => '41000.00',
            'datetime_pay' => now(),
        ]);

        Livewire::test(ListPayments::class)
            ->set('tableFilters.payment_filters.received_by', null)
            ->set('tableFilters.payment_filters.receipt_number', 'FILTER-001')
            ->assertTableColumnSummarySet('amount_usd', 'amount_usd_total', 5)
            ->assertTableColumnSummarySet('amount_kh', 'amount_khr_total', 20500);
    }

    public function test_payment_date_filter_defaults_to_today_and_accepts_an_inclusive_range(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 21)->startOfDay());
        $this->createPaymentViewer();

        $beforeRange = Payment::query()->create([
            'receipt_number' => 'DATE-018',
            'amount_usd' => '1.00',
            'datetime_pay' => '2026-10-18',
        ]);
        $startDate = Payment::query()->create([
            'receipt_number' => 'DATE-019',
            'amount_usd' => '2.00',
            'datetime_pay' => '2026-10-19',
        ]);
        $today = Payment::query()->create([
            'receipt_number' => 'DATE-021',
            'amount_usd' => '3.00',
            'datetime_pay' => '2026-10-21',
        ]);
        $endDate = Payment::query()->create([
            'receipt_number' => 'DATE-023',
            'amount_usd' => '4.00',
            'datetime_pay' => '2026-10-23',
        ]);
        $afterRange = Payment::query()->create([
            'receipt_number' => 'DATE-024',
            'amount_usd' => '5.00',
            'datetime_pay' => '2026-10-24',
        ]);

        $component = Livewire::test(ListPayments::class)
            ->set('tableFilters.payment_filters.received_by', null)
            ->assertCanSeeTableRecords([$today])
            ->assertCanNotSeeTableRecords([$beforeRange, $startDate, $endDate, $afterRange])
            ->set('tableFilters.payment_filters.from_date', '2026-10-19')
            ->set('tableFilters.payment_filters.to_date', '2026-10-23')
            ->assertCanSeeTableRecords([$startDate, $today, $endDate])
            ->assertCanNotSeeTableRecords([$beforeRange, $afterRange]);

        $selectedOrFilteredQuery = new ReflectionMethod(ListPayments::class, 'selectedOrFilteredQuery');
        $exportAndClearRecords = $selectedOrFilteredQuery
            ->invoke($component->instance())
            ->pluck('receipt_number')
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['DATE-019', 'DATE-021', 'DATE-023'], $exportAndClearRecords);

        $component
            ->set('tableFilters.payment_filters.from_date', '2026-10-21')
            ->set('tableFilters.payment_filters.to_date', '2026-10-19')
            ->assertCanNotSeeTableRecords([$beforeRange, $startDate, $today, $endDate, $afterRange]);
    }

    public function test_payment_report_time_fields_filter_rows_and_format_the_export_subtitle_without_saving(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 24)->setTime(18, 0));
        $this->createPaymentViewer();

        $early = Payment::query()->create([
            'receipt_number' => 'REPORT-TIME-EARLY',
            'amount_usd' => '1.00',
            'datetime_pay' => '2026-10-24 06:00:00',
        ]);
        $late = Payment::query()->create([
            'receipt_number' => 'REPORT-TIME-LATE',
            'amount_usd' => '2.00',
            'datetime_pay' => '2026-10-24 22:00:00',
        ]);
        $inside = Payment::query()->create([
            'receipt_number' => 'REPORT-TIME-INSIDE',
            'amount_usd' => '3.00',
            'datetime_pay' => '2026-10-24 12:00:00',
        ]);

        $component = Livewire::test(ListPayments::class)
            ->set('tableFilters.payment_filters.received_by', null)
            ->set('tableFilters.payment_filters.from_time', '08:00')
            ->set('tableFilters.payment_filters.to_time', '21:00')
            ->assertCanSeeTableRecords([$inside])
            ->assertCanNotSeeTableRecords([$early, $late]);

        $timeRange = $this->invokeProtected($component->instance(), 'exportReportTimeRange');
        $subtitle = $this->invokeProtectedStatic(PaymentsTable::class, 'reportSubtitle', [
            '2026-10-24',
            $timeRange,
        ]);

        $savedTimesKey = 'payments.report_times.'.auth()->id();
        $this->assertFalse(Cache::has($savedTimesKey));
        $this->assertSame('០៨:០០ ព្រឹក - ២១:០០ ល្ងាច', $timeRange);
        $this->assertSame('គិតត្រឹមថ្ងៃទី៖ ២៤-តុលា-២០២៦ ម៉ោង ០៨:០០ ព្រឹក - ២១:០០ ល្ងាច', $subtitle);

        $component
            ->set('tableFilters.payment_filters.from_time', null)
            ->set('tableFilters.payment_filters.to_time', null)
            ->assertCanSeeTableRecords([$early, $inside, $late]);

        $this->assertNull($this->invokeProtected($component->instance(), 'exportReportTimeRange'));

        Livewire::test(ListPayments::class)
            ->set('tableFilters.payment_filters.received_by', null)
            ->assertSet('tableFilters.payment_filters.from_time', null)
            ->assertSet('tableFilters.payment_filters.to_time', null);
    }

    public function test_payment_exports_use_current_time_filters_and_ignore_old_saved_times(): void
    {
        $this->createPaymentViewer();

        $component = Livewire::test(ListPayments::class)
            ->set('tableFilters.payment_filters.received_by', null)
            ->set('tableFilters.payment_filters.from_time', '08:00')
            ->set('tableFilters.payment_filters.to_time', '16:00');

        $timeRange = $this->invokeProtected($component->instance(), 'exportReportTimeRange');

        $this->assertSame('០៨:០០ ព្រឹក - ១៦:០០ ល្ងាច', $timeRange);

        $component
            ->set('tableFilters.payment_filters.from_time', '09:00')
            ->set('tableFilters.payment_filters.to_time', '17:00')
            ->call('saveReportTimes');

        $timeRange = $this->invokeProtected($component->instance(), 'exportReportTimeRange');

        $this->assertSame('០៩:០០ ព្រឹក - ១៧:០០ ល្ងាច', $timeRange);

        $component
            ->set('tableFilters.payment_filters.from_time', '10:00')
            ->set('tableFilters.payment_filters.to_time', '18:00');

        $this->assertSame(
            '១០:០០ ព្រឹក - ១៨:០០ ល្ងាច',
            $this->invokeProtected($component->instance(), 'exportReportTimeRange'),
        );

        $component
            ->set('tableFilters.payment_filters.from_time', null)
            ->set('tableFilters.payment_filters.to_time', null);

        $this->assertNull($this->invokeProtected($component->instance(), 'exportReportTimeRange'));

        Livewire::test(ListPayments::class)
            ->set('tableFilters.payment_filters.received_by', null)
            ->assertSet('tableFilters.payment_filters.from_time', null)
            ->assertSet('tableFilters.payment_filters.to_time', null);
        $this->assertFalse(Cache::has('payments.report_times.'.auth()->id()));
    }

    public function test_payment_export_time_range_supports_single_bounds_and_rejects_invalid_values(): void
    {
        $this->createPaymentViewer();

        $component = Livewire::test(ListPayments::class)
            ->set('tableFilters.payment_filters.received_by', null)
            ->set('tableFilters.payment_filters.from_time', '09:00');

        $this->assertSame('០៩:០០ ព្រឹក - ២៣:៥៩ ល្ងាច', $this->invokeProtected($component->instance(), 'exportReportTimeRange'));

        $component
            ->set('tableFilters.payment_filters.from_time', null)
            ->set('tableFilters.payment_filters.to_time', '16:00');

        $this->assertSame('០០:០០ ព្រឹក - ១៦:០០ ល្ងាច', $this->invokeProtected($component->instance(), 'exportReportTimeRange'));

        $component->set('tableFilters.payment_filters.from_time', 'invalid-time');
        $this->assertNull($this->invokeProtected($component->instance(), 'exportReportTimeRange'));

        $component
            ->set('tableFilters.payment_filters.from_time', '17:00')
            ->set('tableFilters.payment_filters.to_time', '16:00');
        $this->assertNull($this->invokeProtected($component->instance(), 'exportReportTimeRange'));
    }

    public function test_payment_export_subtitle_shows_one_report_date_for_a_continuous_multi_day_interval(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 27)->setTime(12, 0));
        $this->createPaymentViewer();
        app()->setLocale('km');

        $component = Livewire::test(ListPayments::class)
            ->set('tableFilters.payment_filters.received_by', null)
            ->set('tableFilters.payment_filters.from_date', '2026-10-25')
            ->set('tableFilters.payment_filters.to_date', '2026-10-26')
            ->set('tableFilters.payment_filters.from_time', '18:00')
            ->set('tableFilters.payment_filters.to_time', '09:00');

        $this->assertSame(
            '១៨:០០ ល្ងាច - ០៩:០០ ព្រឹក',
            $this->invokeProtected($component->instance(), 'exportReportTimeRange'),
        );
        $this->assertSame(
            'គិតត្រឹមថ្ងៃទី៖ ២៦-តុលា-២០២៦ ម៉ោង ១៨:០០ ល្ងាច - ០៩:០០ ព្រឹក',
            $this->invokeProtectedStatic(PaymentsTable::class, 'reportSubtitle', [
                $this->invokeProtected($component->instance(), 'exportReportDate'),
                $this->invokeProtected($component->instance(), 'exportReportTimeRange'),
            ]),
        );

        app()->setLocale('en');
        $this->assertSame(
            '18:00 PM - 09:00 AM',
            $this->invokeProtected($component->instance(), 'exportReportTimeRange'),
        );

        $component->set('tableFilters.payment_filters.to_date', '2026-10-25');
        $this->assertNull($this->invokeProtected($component->instance(), 'exportReportTimeRange'));

        $component->set('tableFilters.payment_filters.to_date', '2026-10-24');
        $this->assertNull($this->invokeProtected($component->instance(), 'exportReportTimeRange'));

        $component
            ->set('tableFilters.payment_filters.to_date', '2026-10-26')
            ->set('tableFilters.payment_filters.from_time', null);
        app()->setLocale('km');
        $this->assertSame(
            '០០:០០ ព្រឹក - ០៩:០០ ព្រឹក',
            $this->invokeProtected($component->instance(), 'exportReportTimeRange'),
        );
    }

    public function test_payment_report_time_range_rejects_an_end_time_before_the_start_time(): void
    {
        $user = $this->createPaymentViewer();
        $savedTimesKey = 'payments.report_times.'.$user->id;
        Cache::forget($savedTimesKey);

        $this->assertFalse(PaymentsTable::isValidReportTimeRange('16:00', '15:00'));
        $this->assertTrue(PaymentsTable::isValidReportTimeRange('16:00', '16:00'));
        $this->assertTrue(PaymentsTable::isValidReportTimeRange('08:00', '21:00'));

        Livewire::test(ListPayments::class)
            ->set('tableFilters.payment_filters.received_by', null)
            ->set('tableFilters.payment_filters.from_time', '16:00')
            ->set('tableFilters.payment_filters.to_time', '15:00')
            ->call('saveReportTimes');

        $this->assertFalse(Cache::has($savedTimesKey));
    }

    public function test_payment_report_discards_a_previously_saved_invalid_time_range(): void
    {
        $user = $this->createPaymentViewer();
        $savedTimesKey = 'payments.report_times.'.$user->id;
        Cache::put($savedTimesKey, [
            'from_time' => '16:00',
            'to_time' => '15:00',
            'saved_at' => now()->timestamp,
        ], now()->addDay());

        Livewire::test(ListPayments::class)
            ->set('tableFilters.payment_filters.received_by', null)
            ->assertSet('tableFilters.payment_filters.from_time', null)
            ->assertSet('tableFilters.payment_filters.to_time', null);

        $this->assertFalse(Cache::has($savedTimesKey));
    }

    public function test_payment_report_does_not_restore_times_from_the_old_auto_saved_cache(): void
    {
        $user = $this->createPaymentViewer();
        $savedTimesKey = 'payments.report_times.'.$user->id;
        Cache::put($savedTimesKey, [
            'from_time' => '09:00',
            'to_time' => '16:00',
            'saved_at' => now()->timestamp,
        ], now()->addDay());

        Livewire::test(ListPayments::class)
            ->set('tableFilters.payment_filters.received_by', null)
            ->assertSet('tableFilters.payment_filters.from_time', null)
            ->assertSet('tableFilters.payment_filters.to_time', null);

        $this->assertFalse(Cache::has($savedTimesKey));
    }

    public function test_payment_report_clears_unsaved_times_from_the_filters_url_on_a_fresh_visit(): void
    {
        $this->createPaymentViewer();

        Livewire::withQueryParams([
            'filters' => [
                'payment_filters' => [
                    'from_time' => '09:00',
                    'to_time' => '16:00',
                ],
            ],
        ])->test(ListPayments::class)
            ->assertSet('tableFilters.payment_filters.from_time', null)
            ->assertSet('tableFilters.payment_filters.to_time', null);
    }

    public function test_resetting_payment_report_times_clears_only_the_time_filters_and_saved_range(): void
    {
        $user = $this->createPaymentViewer();
        $savedTimesKey = 'payments.report_times.'.$user->id;
        Cache::put($savedTimesKey, [
            'from_time' => '08:00',
            'to_time' => '21:00',
            'saved_at' => now()->timestamp,
            'saved_manually' => true,
        ], now()->addDay());

        Livewire::test(ListPayments::class)
            ->set('tableFilters.payment_filters.received_by', null)
            ->set('tableFilters.payment_filters.from_date', '2026-10-20')
            ->set('tableFilters.payment_filters.to_date', '2026-10-24')
            ->set('tableFilters.payment_filters.from_time', '08:00')
            ->set('tableFilters.payment_filters.to_time', '21:00')
            ->call('resetReportTimes')
            ->assertSet('tableFilters.payment_filters.from_time', null)
            ->assertSet('tableFilters.payment_filters.to_time', null)
            ->assertSet('tableFilters.payment_filters.from_date', '2026-10-20')
            ->assertSet('tableFilters.payment_filters.to_date', '2026-10-24');

        $this->assertFalse(Cache::has($savedTimesKey));
    }

    public function test_payment_total_labels_are_localized(): void
    {
        $this->createPaymentViewer();
        $originalLocale = app()->getLocale();

        app()->setLocale('en');

        try {
            $englishComponent = Livewire::test(ListPayments::class)
                ->set('tableFilters.payment_filters.received_by', null);
            $englishTable = $englishComponent->instance()->getTable();

            $this->assertSame(
                'Total USD',
                $englishTable->getColumn('amount_usd')->getSummarizer('amount_usd_total')->getLabel(),
            );
            $this->assertSame(
                'Total KHR',
                $englishTable->getColumn('amount_kh')->getSummarizer('amount_khr_total')->getLabel(),
            );

            app()->setLocale('km');

            $khmerComponent = Livewire::test(ListPayments::class)
                ->set('tableFilters.payment_filters.received_by', null);
            $khmerTable = $khmerComponent->instance()->getTable();

            $this->assertSame(
                'សរុបដុល្លារ',
                $khmerTable->getColumn('amount_usd')->getSummarizer('amount_usd_total')->getLabel(),
            );
            $this->assertSame(
                'សរុបរៀល',
                $khmerTable->getColumn('amount_kh')->getSummarizer('amount_khr_total')->getLabel(),
            );
        } finally {
            app()->setLocale($originalLocale);
        }
    }

    public function test_export_summary_uses_the_records_being_exported(): void
    {
        $originalLocale = app()->getLocale();
        app()->setLocale('en');

        try {
            $included = Payment::query()->create([
                'receipt_number' => 'EXPORT-001',
                'amount_usd' => '5.00',
                'amount_kh' => '20500.00',
            ]);
            Payment::query()->create([
                'receipt_number' => 'EXPORT-002',
                'amount_usd' => '10.00',
                'amount_kh' => '41000.00',
            ]);

            $columnKeys = ['row_number', 'amount_usd', 'amount_kh'];
            $summaryRow = $this->invokeProtectedStatic(
                PaymentsTable::class,
                'summaryRow',
                [[$included], $columnKeys],
            );

            $this->assertSame([
                'Total',
                'Total USD: $5.00',
                'Total KHR: 20,500.00 ៛',
            ], $summaryRow);

            $pdfHtml = $this->invokeProtectedStatic(
                TablePdfExporter::class,
                'html',
                [['No', 'Amount USD', 'Amount KHR'], [['1', '5.00$', '20,500.00 ៛']], 'Payment Records', [$summaryRow]],
            );

            $this->assertStringContainsString('<tfoot><tr><td>Total</td><td>Total USD: $5.00</td><td>Total KHR: 20,500.00 ៛</td></tr></tfoot>', $pdfHtml);
        } finally {
            app()->setLocale($originalLocale);
        }
    }

    public function test_payment_type_summary_shows_a_dash_when_a_currency_has_no_amount(): void
    {
        $originalLocale = app()->getLocale();
        app()->setLocale('en');

        try {
            $cashType = new PaymentType([
                'key' => 'cash',
                'name_en' => 'Cash',
                'name_kh' => 'សាច់ប្រាក់',
                'method' => 'cash',
            ]);
            $bankType = new PaymentType([
                'key' => 'aba',
                'name_en' => 'ABA',
                'name_kh' => 'ABA',
                'method' => 'bank',
            ]);

            $cashPayment = new Payment([
                'type_payment' => 'cash',
                'amount_usd' => '25.00',
                'amount_kh' => null,
            ]);
            $cashPayment->setRelation('paymentType', $cashType);

            $bankPayment = new Payment([
                'type_payment' => 'aba',
                'amount_usd' => null,
                'amount_kh' => '120000.00',
            ]);
            $bankPayment->setRelation('paymentType', $bankType);

            $summary = $this->invokeProtectedStatic(
                PaymentsTable::class,
                'paymentTypeSummaryTable',
                [[$cashPayment, $bankPayment]],
            );

            $this->assertSame([
                ['Cash', '1', '25.00 $', '-'],
                ['Bank', '1', '-', '120,000.00 ៛'],
            ], $summary['rows']);
        } finally {
            app()->setLocale($originalLocale);
        }
    }

    public function test_payment_totals_use_the_shared_visual_style(): void
    {
        $this->createPaymentViewer();

        $component = Livewire::test(ListPayments::class)
            ->set('tableFilters.payment_filters.received_by', null);
        $table = $component->instance()->getTable();

        $this->assertSame(
            ['class' => 'payment-total-summary'],
            $table->getColumn('amount_usd')->getSummarizer('amount_usd_total')->getExtraAttributes(),
        );
        $this->assertSame(
            ['class' => 'payment-total-summary'],
            $table->getColumn('amount_kh')->getSummarizer('amount_khr_total')->getExtraAttributes(),
        );

        $pdfHtml = $this->invokeProtectedStatic(
            TablePdfExporter::class,
            'html',
            [['No'], [['1']], 'Payment Records', [['Total']]],
        );

        $this->assertStringContainsString(
            'tfoot td { background-color: #e8eef5; color: #1f2937; font-weight: bold; border-top: 1.5pt solid #94a3b8; }',
            $pdfHtml,
        );

        $worksheetXml = $this->invokeProtectedStatic(
            PaymentsTable::class,
            'worksheetXml',
            [[['Heading'], ['Total']], [2]],
        );
        $stylesXml = $this->invokeProtectedStatic(PaymentsTable::class, 'stylesXml', []);

        $this->assertStringContainsString('<c r="A2" s="1" t="inlineStr">', $worksheetXml);
        $this->assertStringContainsString('rgb="FFE8EEF5"', $stylesXml);
        $this->assertStringContainsString('rgb="FF1F2937"', $stylesXml);
        $this->assertStringContainsString('rgb="FF94A3B8"', $stylesXml);
    }

    public function test_payment_exports_use_the_compact_report_template(): void
    {
        $originalLocale = app()->getLocale();
        app()->setLocale('en');

        try {
            $user = $this->createPaymentViewer();
            $user->update(['academic_year' => '2025-2026']);

            PaymentType::query()->create([
                'key' => 'aba',
                'name_en' => 'ABA',
                'name_kh' => 'ABA',
                'method' => 'bank',
                'is_active' => true,
            ]);
            PaymentType::query()->create([
                'key' => 'cash',
                'name_en' => 'Cash',
                'name_kh' => 'សាច់ប្រាក់',
                'method' => 'cash',
                'is_active' => true,
            ]);

            $payment = Payment::query()->create([
                'users_id' => $user->id,
                'receipt_number' => 'REPORT-001',
                'type_payment' => 'aba',
                'amount_usd' => '5.00',
                'amount_kh' => '20500.00',
                'datetime_pay' => '2026-09-09',
                'description' => 'Sample payment',
            ]);
            $payment->load('user');

            $headings = $this->invokeProtectedStatic(PaymentsTable::class, 'reportHeadings', []);
            $reportDefinitions = $this->invokeProtectedStatic(PaymentsTable::class, 'reportColumnDefinitions', []);
            $exportDefinitions = $this->invokeProtectedStatic(PaymentsTable::class, 'exportColumnDefinitions', []);
            $rows = $this->invokeProtectedStatic(PaymentsTable::class, 'reportRows', [[$payment]]);
            $summary = $this->invokeProtectedStatic(PaymentsTable::class, 'reportSummary', [[$payment]]);

            $this->assertArrayNotHasKey('academic_year', $reportDefinitions);
            $this->assertSame(array_search('amount_kh', array_keys($exportDefinitions), true) + 1, array_search('type_payment', array_keys($exportDefinitions), true));

            $this->assertSame([
                'No',
                'Name',
                'Name Latin',
                'Gender',
                'Date of Birth',
                'Payment Date',
                'Receipt',
                'USD',
                'KHR',
                'Payment Type',
            ], $headings);
            $this->assertSame('Payment Viewer', $rows[0][1]);
            $this->assertSame('PAYMENT VIEWER', $rows[0][2]);
            $this->assertSame('09-Sep-2026', $rows[0][5]);
            $this->assertSame('REPORT-001', $rows[0][6]);
            $this->assertSame(['count' => '1', 'usd' => '5.00', 'khr' => '20,500.00'], $summary);

            $pdfHtml = $this->invokeProtectedStatic(
                TablePdfExporter::class,
                'html',
                [$headings, $rows, 'Payment Records', [], [
                    'layout' => 'payment-records',
                    'subtitle' => 'Date: 09-Sep-2026',
                    'summary' => $summary,
                    'summary_table' => $this->invokeProtectedStatic(PaymentsTable::class, 'paymentTypeSummaryTable', [[$payment]]),
                ]],
            );

            $this->assertStringContainsString('background-color: #003b95', $pdfHtml);
            $this->assertStringContainsString('font-family: khmermuol, khmerbattambang, calibri, sans-serif', $pdfHtml);
            $this->assertStringContainsString('font-family: khmerbattambang, calibri, sans-serif', $pdfHtml);
            $this->assertStringContainsString('class="report-title report-title-latin"', $pdfHtml);
            $this->assertStringContainsString('class="report-subtitle"', $pdfHtml);
            $this->assertStringContainsString('ព្រះរាជាណាចក្រកម្ពុជា', $pdfHtml);
            $this->assertStringContainsString('ជាតិ សាសនា ព្រះមហាក្សត្រ', $pdfHtml);
            $this->assertStringContainsString('សាកលវិទ្យាល័យវិទ្យាសាស្ត្រសុខាភិបាល', $pdfHtml);
            $this->assertStringContainsString('class="official-logo"', $pdfHtml);
            $this->assertStringContainsString('data:image/png;base64,', $pdfHtml);
            $this->assertStringContainsString('REPORT-001', $pdfHtml);
            $this->assertStringContainsString('<td>5.00 $</td>', $pdfHtml);
            $this->assertStringContainsString('<th>Payment Type</th>', $pdfHtml);
            $this->assertStringContainsString('<th>KHR</th><th>Payment Type</th>', $pdfHtml);
            $this->assertStringNotContainsString('<th>Academic Year</th>', $pdfHtml);
            $this->assertStringContainsString('<th style="white-space: nowrap; font-size: 7pt">Date of Birth</th>', $pdfHtml);
            $this->assertStringContainsString('<th>Count</th>', $pdfHtml);
            $this->assertStringNotContainsString('<th>Bank</th>', $pdfHtml);
            $this->assertStringNotContainsString('<td>ABA</td>', $pdfHtml);
            $this->assertStringContainsString('<td>Bank</td><td>1</td><td>5.00 $</td><td>20,500.00 ៛</td>', $pdfHtml);
            $this->assertStringContainsString('Date: 09-Sep-2026', $pdfHtml);
            $this->assertStringContainsString('Name Latin', $pdfHtml);
            $this->assertStringNotContainsString('Payment Status', $pdfHtml);
            $this->assertStringNotContainsString('Candidate ID', $pdfHtml);
            $this->assertStringNotContainsString('Summary', $pdfHtml);
            $this->assertStringNotContainsString('Prepared by', $pdfHtml);
            $this->assertStringNotContainsString('Approved by', $pdfHtml);
            $this->assertStringNotContainsString('signature-table', $pdfHtml);

            $sheet = $this->invokeProtectedStatic(PaymentsTable::class, 'reportSheet', [[$payment]]);
            $worksheetXml = $this->invokeProtectedStatic(
                PaymentsTable::class,
                'worksheetXml',
                [$sheet['rows'], [], $sheet['options']],
            );

            $this->assertStringNotContainsString('Prepared by', serialize($sheet['rows']));
            $this->assertStringNotContainsString('Approved by', serialize($sheet['rows']));
            $this->assertStringNotContainsString('Summary', serialize($sheet['rows']));
            $this->assertStringContainsString('Payment Type', serialize($sheet['rows']));
            $this->assertStringContainsString('Cash', serialize($sheet['rows']));
            $this->assertStringContainsString('Bank', serialize($sheet['rows']));
            $this->assertStringNotContainsString('ABA', serialize($sheet['rows']));
            foreach (['A1:J1', 'A2:J2', 'A7:B7', 'A8:J8', 'A9:J9'] as $range) {
                $this->assertStringContainsString('<mergeCell ref="'.$range.'"/>', $worksheetXml);
            }
            $this->assertStringContainsString('xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"', $worksheetXml);
            $this->assertStringContainsString('<drawing r:id="rId1"/>', $worksheetXml);
            $this->assertStringNotContainsString('<mergeCell ref="A11:B11"/>', $worksheetXml);
            $this->assertStringNotContainsString('<mergeCell ref="B11:C11"/>', $worksheetXml);
            $this->assertStringNotContainsString('<mergeCell ref="A12:B12"/>', $worksheetXml);
            $this->assertStringNotContainsString('<mergeCell ref="B12:C12"/>', $worksheetXml);
            $this->assertStringContainsString('<c r="A1" s="9"', $worksheetXml);
            $this->assertStringContainsString('<c r="A2" s="9"', $worksheetXml);
            $this->assertStringContainsString('<c r="A7" s="10"', $worksheetXml);
            $this->assertStringContainsString('<c r="A8" s="2"', $worksheetXml);
            $this->assertStringContainsString('<c r="A12" s="11"', $worksheetXml);
            $this->assertStringContainsString('<c r="B12" s="12"', $worksheetXml);
            $this->assertStringContainsString('<c r="A14" s="4"', $worksheetXml);
            $this->assertStringContainsString('<c r="B14" s="4"', $worksheetXml);
            $this->assertStringContainsString('<c r="C14" s="4"', $worksheetXml);
            $this->assertStringContainsString('<c r="D14" s="4"', $worksheetXml);
            $this->assertStringContainsString('<c r="A15" s="12"', $worksheetXml);
            $this->assertStringContainsString('<t>5.00 $</t>', $worksheetXml);
            $this->assertStringContainsString('<t>20,500.00៛</t>', $worksheetXml);
            $this->assertStringContainsString('<c r="B15" s="11"', $worksheetXml);
            $this->assertStringContainsString('<c r="C15" s="11"', $worksheetXml);
            $this->assertStringNotContainsString('showGridLines="0"', $worksheetXml);
            $this->assertStringContainsString('orientation="landscape"', $worksheetXml);
            $this->assertStringContainsString('numberStoredAsText="1"', $worksheetXml);
            $this->assertStringContainsString('<c r="A11" s="4"', $worksheetXml);
            $this->assertStringContainsString('<c r="E11" s="7"', $worksheetXml);
            $this->assertStringContainsString('សាកលវិទ្យាល័យវិទ្យាសាស្ត្រសុខាភិបាល', $worksheetXml);
            $this->assertStringContainsString('ព្រះរាជាណាចក្រកម្ពុជា', $worksheetXml);
            $this->assertStringContainsString('ជាតិ សាសនា ព្រះមហាក្សត្រ', $worksheetXml);
            $this->assertStringContainsString('<col min="1" max="1" width="8"', $worksheetXml);
            $this->assertStringContainsString('<col min="4" max="4" width="18"', $worksheetXml);
            $this->assertStringContainsString('<col min="5" max="5" width="24"', $worksheetXml);
            $this->assertStringContainsString('<c r="A11" s="4"', $worksheetXml);
            $this->assertStringContainsString('width="29"', $worksheetXml);

            $stylesXml = $this->invokeProtectedStatic(PaymentsTable::class, 'stylesXml', []);
            $this->assertStringContainsString('<name val="Khmer OS Battambang"/>', $stylesXml);
            $this->assertStringContainsString('<name val="Khmer OS Muol Light"/>', $stylesXml);
        } finally {
            app()->setLocale($originalLocale);
        }
    }

    public function test_payment_excel_report_embeds_the_uhs_logo(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'payment-report-');

        try {
            $sheet = $this->invokeProtectedStatic(PaymentsTable::class, 'reportSheet', [[]]);

            $this->invokeProtectedStatic(PaymentsTable::class, 'writeXlsx', [$path, [$sheet]]);

            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            $this->assertNotFalse($zip->locateName('xl/media/image1.png'));
            $this->assertNotFalse($zip->locateName('xl/drawings/drawing1.xml'));
            $this->assertNotFalse($zip->locateName('xl/drawings/_rels/drawing1.xml.rels'));
            $this->assertNotFalse($zip->locateName('xl/worksheets/_rels/sheet1.xml.rels'));
            $zip->close();
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_payment_downloads_are_always_in_khmer(): void
    {
        $originalLocale = app()->getLocale();
        app()->setLocale('en');

        try {
            $user = $this->createPaymentViewer();
            $payment = Payment::query()->create([
                'users_id' => $user->id,
                'receipt_number' => 'KM-001',
                'amount_usd' => '5.00',
                'amount_kh' => '20500.00',
                'datetime_pay' => '2026-09-09',
            ]);

            $this->travelTo(now()->setDate(2026, 10, 19));
            $expectedName = rawurlencode('បញ្ជីឈ្មោះបេក្ខជនបង់លុយ 19-10-2026');

            $response = PaymentsTable::downloadExcel([$payment->load('user')], null, '2026-10-22');
            $this->assertStringContainsString("filename*=utf-8''".$expectedName.'.xlsx', $response->headers->get('Content-Disposition'));
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($response->getFile()->getPathname()) === true);
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();

            $this->assertStringContainsString('គិតត្រឹមថ្ងៃទី៖ ២២-តុលា-២០២៦', $sheet);
            preg_match_all('/<t(?:\s[^>]*)?>(.*?)<\/t>/s', $sheet, $textNodes);
            $sheetText = implode('', array_map('html_entity_decode', $textNodes[1]));
            $this->assertStringContainsString('រាជធានីភ្នំពេញ ថ្ងៃទី ២២ ខែ តុលា ឆ្នាំ ២០២៦', $sheetText);

            $this->assertStringContainsString('បញ្ជីឈ្មោះបេក្ខជនបង់លុយ', $sheet);
            $this->assertStringContainsString('ល.រ', $sheet);
            $this->assertStringContainsString('ប្រភេទទូទាត់', $sheet);
            $this->assertStringContainsString('សាច់ប្រាក់', $sheet);
            $this->assertStringContainsString('ធនាគារ', $sheet);
            $this->assertStringContainsString('<c r="B16" s="11" t="inlineStr"><is><t>1</t>', $sheet);
            $this->assertStringNotContainsString('Payment Records', $sheet);
            $this->assertSame('en', app()->getLocale());

            Pdf::shouldReceive('loadHTML')
                ->once()
                ->withArgs(fn (string $html, array $pdfOptions): bool => $pdfOptions['format'] === 'A4'
                    && $pdfOptions['orientation'] === 'P'
                    && str_contains($html, 'បញ្ជីឈ្មោះបេក្ខជនបង់លុយ')
                    && str_contains($html, '<th style="width: 18mm">ភេទ</th>')
                    && str_contains($html, '<th style="width: 22mm; white-space: nowrap; font-size: 7pt">ថ្ងៃខែឆ្នាំកំណើត</th>')
                    && str_contains($html, '<th style="width: 22mm; white-space: nowrap; font-size: 7pt">ថ្ងៃខែឆ្នាំកំណើត</th>')
                    && str_contains($html, '.compact-table th, .compact-table td { font-size: 7pt; }')
                    && str_contains($html, '<table class="data-table compact-table">')
                    && str_contains($html, '<table class="summary-table compact-table">')
                    && str_contains($html, 'nth-child(4), .data-table td:nth-child(4) { white-space: nowrap; }')
                    && str_contains($html, 'ល.រ')
                    && str_contains($html, 'គិតត្រឹមថ្ងៃទី៖ ២២-តុលា-២០២៦')
                    && str_contains($html, 'រាជធានីភ្នំពេញ ថ្ងៃទី ')
                    && str_contains($html, '<strong class="report-date-bold">២២</strong> ខែ <strong class="report-date-bold">តុលា</strong> ឆ្នាំ <strong class="report-date-bold">២០២៦</strong>')
                    && ! str_contains($html, 'Payment Records'))
                ->andReturn(\Mockery::mock(['output' => '%PDF']));

            $pdfResponse = PaymentsTable::downloadPdf([$payment], null, '2026-10-22');
            $this->assertStringContainsString("filename*=utf-8''".$expectedName.'.pdf', $pdfResponse->headers->get('Content-Disposition'));
            $this->assertSame('en', app()->getLocale());
        } finally {
            app()->setLocale($originalLocale);
        }
    }

    public function test_pdf_export_allows_empty_results_and_uses_excel_report_columns(): void
    {
        $user = $this->createPaymentViewer();
        $downloadPermission = Permission::query()->create([
            'name' => 'DownloadPdf:Payment',
            'guard_name' => 'web',
        ]);
        $user->givePermissionTo($downloadPermission);

        Pdf::shouldReceive('loadHTML')
            ->once()
            ->withArgs(fn (string $html): bool => str_contains($html, __('payments.export.columns.no'))
                && str_contains($html, 'គិតត្រឹមថ្ងៃទី៖ ២២-តុលា-២០២៦')
                && str_contains($html, 'រាជធានីភ្នំពេញ ថ្ងៃទី ')
                && str_contains($html, '<strong class="report-date-bold">២២</strong> ខែ <strong class="report-date-bold">តុលា</strong> ឆ្នាំ <strong class="report-date-bold">២០២៦</strong>')
                && ! str_contains($html, __('payments.table.status_payt')))
            ->andReturn(\Mockery::mock(['output' => '%PDF']));

        Livewire::test(ListPayments::class)
            ->set('tableFilters.payment_filters.received_by', null)
            ->set('tableFilters.payment_filters.from_date', '2026-10-22')
            ->set('tableFilters.payment_filters.to_date', '2026-10-22')
            ->call('downloadPdfFromTableSelection');
    }

    public function test_payment_records_and_payment_status_list_pdfs_use_a4_portrait(): void
    {
        Pdf::shouldReceive('loadHTML')
            ->twice()
            ->withArgs(fn (string $html, array $pdfOptions): bool => $pdfOptions['format'] === 'A4'
                && $pdfOptions['orientation'] === 'P'
                && str_contains($html, 'white-space: nowrap; font-size: 7pt')
                && ! str_contains($html, 'ឆ្នាំសិក្សា'))
            ->andReturn(\Mockery::mock(['output' => '%PDF']));

        PaymentStatusListsTable::downloadPdf([]);
        PaymentsTable::downloadPdf([]);
    }

    private function invokeProtectedStatic(string $class, string $method, array $arguments): mixed
    {
        $reflection = new ReflectionMethod($class, $method);

        return $reflection->invokeArgs(null, $arguments);
    }

    private function invokeProtected(object $object, string $method, array $arguments = []): mixed
    {
        $reflection = new ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }

    private function createPaymentViewer(): User
    {
        $permission = Permission::query()->create([
            'name' => 'ViewAny:Payment',
            'guard_name' => 'web',
        ]);
        $role = Role::query()->create([
            'name' => 'payment-viewer',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo($permission);

        $user = User::query()->create([
            'registration_type' => 'admin',
            'name' => 'Payment Viewer',
            'username' => 'payment_viewer',
            'email' => 'payment-viewer@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        $this->actingAs($user);

        return $user;
    }
}
