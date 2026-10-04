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
        ]);
        Payment::query()->create([
            'receipt_number' => 'TOTAL-002',
            'amount_usd' => '10.00',
            'amount_kh' => '41000.00',
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
        ]);
        Payment::query()->create([
            'receipt_number' => 'OTHER-001',
            'amount_usd' => '10.00',
            'amount_kh' => '41000.00',
        ]);

        $component = Livewire::test(ListPayments::class)
            ->set('tableSearch', 'MATCH-001')
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
        ]);
        Payment::query()->create([
            'receipt_number' => 'OTHER-001',
            'amount_usd' => '10.00',
            'amount_kh' => '41000.00',
        ]);

        Livewire::test(ListPayments::class)
            ->set('tableFilters.payment_filters.receipt_number', 'FILTER-001')
            ->assertTableColumnSummarySet('amount_usd', 'amount_usd_total', 5)
            ->assertTableColumnSummarySet('amount_kh', 'amount_khr_total', 20500);
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
