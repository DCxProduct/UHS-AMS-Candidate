<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Payments\Pages\ListPayments;
use App\Filament\Admin\Resources\Payments\Tables\PaymentsTable;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Support\TablePdfExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as Pdf;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PaymentTotalsTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_table_summarizes_usd_and_khr_amounts(): void
    {
        $this->createPaymentViewer();

        Payment::query()->create([
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
            ->assertTableColumnSummarizerExists('amount_usd', 'amount_usd_total')
            ->assertTableColumnSummarizerExists('amount_kh', 'amount_khr_total')
            ->assertTableColumnSummarySet('amount_usd', 'amount_usd_total', 15)
            ->assertTableColumnSummarySet('amount_kh', 'amount_khr_total', 61500);
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
    }

    public function test_payment_total_labels_are_localized(): void
    {
        $this->createPaymentViewer();
        $originalLocale = app()->getLocale();

        app()->setLocale('en');

        try {
            $englishComponent = Livewire::test(ListPayments::class);
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

            $khmerComponent = Livewire::test(ListPayments::class);
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
                'Total KHR: 20,500.00 KHR',
            ], $summaryRow);

            $pdfHtml = $this->invokeProtectedStatic(
                TablePdfExporter::class,
                'html',
                [['No', 'Amount USD', 'Amount KHR'], [['1', '5.00$', '20,500.00 KHR']], 'Payment Records', [$summaryRow]],
            );

            $this->assertStringContainsString('<tfoot><tr><td>Total</td><td>Total USD: $5.00</td><td>Total KHR: 20,500.00 KHR</td></tr></tfoot>', $pdfHtml);
        } finally {
            app()->setLocale($originalLocale);
        }
    }

    public function test_payment_totals_use_the_shared_visual_style(): void
    {
        $this->createPaymentViewer();

        $component = Livewire::test(ListPayments::class);
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

            $payment = Payment::query()->create([
                'users_id' => $user->id,
                'receipt_number' => 'REPORT-001',
                'amount_usd' => '5.00',
                'amount_kh' => '20500.00',
                'datetime_pay' => '2026-09-09',
                'description' => 'Sample payment',
            ]);
            $payment->load('user');

            $headings = $this->invokeProtectedStatic(PaymentsTable::class, 'reportHeadings', []);
            $rows = $this->invokeProtectedStatic(PaymentsTable::class, 'reportRows', [[$payment]]);
            $summary = $this->invokeProtectedStatic(PaymentsTable::class, 'reportSummary', [[$payment]]);

            $this->assertSame([
                'No',
                'Name',
                'Name Latin',
                'Gender',
                'Date of Birth',
                'Academic Year',
                'Payment Date',
                'Receipt',
                'Payment Type',
                'USD',
                'KHR',
            ], $headings);
            $this->assertSame('Payment Viewer', $rows[0][1]);
            $this->assertSame('PAYMENT VIEWER', $rows[0][2]);
            $this->assertSame('2025-2026', $rows[0][5]);
            $this->assertSame('REPORT-001', $rows[0][7]);
            $this->assertSame(['count' => '1', 'usd' => '5.00', 'khr' => '20,500.00'], $summary);

            $pdfHtml = $this->invokeProtectedStatic(
                TablePdfExporter::class,
                'html',
                [$headings, $rows, 'Payment Records', [], [
                    'layout' => 'payment-records',
                    'subtitle' => 'Date: 09-Sep-2026',
                    'summary' => $summary,
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
            $this->assertStringContainsString('Total Records', $pdfHtml);
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
            $this->assertStringContainsString('Total Records', serialize($sheet['rows']));
            foreach (['A1:K1', 'A2:K2', 'A7:B7', 'A8:K8', 'A9:K9'] as $range) {
                $this->assertStringContainsString('<mergeCell ref="' . $range . '"/>', $worksheetXml);
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
            $this->assertStringContainsString('<c r="A14" s="7"', $worksheetXml);
            $this->assertStringContainsString('<c r="B14" s="7"', $worksheetXml);
            $this->assertStringContainsString('<c r="C14" s="7"', $worksheetXml);
            $this->assertStringContainsString('<c r="A15" s="13"', $worksheetXml);
            $this->assertStringContainsString('<t>5.00 $</t>', $worksheetXml);
            $this->assertStringContainsString('<c r="B15" s="13"', $worksheetXml);
            $this->assertStringContainsString('<c r="C15" s="13"', $worksheetXml);
            $this->assertStringNotContainsString('showGridLines="0"', $worksheetXml);
            $this->assertStringContainsString('orientation="landscape"', $worksheetXml);
            $this->assertStringContainsString('numberStoredAsText="1"', $worksheetXml);
            $this->assertStringContainsString('<c r="A11" s="4"', $worksheetXml);
            $this->assertStringContainsString('សាកលវិទ្យាល័យវិទ្យាសាស្ត្រសុខាភិបាល', $worksheetXml);
            $this->assertStringContainsString('ព្រះរាជាណាចក្រកម្ពុជា', $worksheetXml);
            $this->assertStringContainsString('ជាតិ សាសនា ព្រះមហាក្សត្រ', $worksheetXml);
            $this->assertStringContainsString('<col min="1" max="1" width="16"', $worksheetXml);
            $this->assertStringContainsString('<col min="5" max="5" width="24"', $worksheetXml);
            $this->assertStringContainsString('<c r="A11" s="4"', $worksheetXml);
            $this->assertStringContainsString('width="25"', $worksheetXml);

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

            $zip = new \ZipArchive();
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

            $response = PaymentsTable::downloadExcel([$payment->load('user')]);
            $this->assertStringContainsString("filename*=utf-8''" . $expectedName . '.xlsx', $response->headers->get('Content-Disposition'));
            $zip = new \ZipArchive();
            $this->assertTrue($zip->open($response->getFile()->getPathname()) === true);
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();

            $this->assertStringContainsString('បញ្ជីឈ្មោះបេក្ខជនបង់លុយ', $sheet);
            $this->assertStringContainsString('ល.រ', $sheet);
            $this->assertStringContainsString('ចំនួនសរុប', $sheet);
            $this->assertStringContainsString('<c r="A15" s="13" t="inlineStr"><is><t>1</t>', $sheet);
            $this->assertStringNotContainsString('Payment Records', $sheet);
            $this->assertSame('en', app()->getLocale());

            Pdf::shouldReceive('loadHTML')
                ->once()
                ->withArgs(fn (string $html): bool => str_contains($html, 'បញ្ជីឈ្មោះបេក្ខជនបង់លុយ')
                    && str_contains($html, 'ល.រ')
                    && ! str_contains($html, 'Payment Records'))
                ->andReturn(\Mockery::mock(['output' => '%PDF']));

            $pdfResponse = PaymentsTable::downloadPdf([$payment]);
            $this->assertStringContainsString("filename*=utf-8''" . $expectedName . '.pdf', $pdfResponse->headers->get('Content-Disposition'));
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
                && ! str_contains($html, __('payments.table.status_payt')))
            ->andReturn(\Mockery::mock(['output' => '%PDF']));

        Livewire::test(ListPayments::class)
            ->call('downloadPdfFromTableSelection');
    }

    private function invokeProtectedStatic(string $class, string $method, array $arguments): mixed
    {
        $reflection = new ReflectionMethod($class, $method);

        return $reflection->invokeArgs(null, $arguments);
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
