<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Payments\Pages\ListPayments;
use App\Filament\Admin\Resources\Payments\PaymentResource;
use App\Filament\Admin\Resources\Payments\Tables\PaymentsTable;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Support\LocalizedDate;
use App\Support\TablePdfExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use ReflectionMethod;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PaymentReceivedByTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $manager;

    private User $officerA;

    private User $officerB;

    protected function setUp(): void
    {
        parent::setUp();

        $permission = Permission::query()->create(['name' => 'ViewAny:Payment', 'guard_name' => 'web']);

        foreach (['admin', 'cashier_manager', 'cashier_officer'] as $name) {
            Role::query()->create(['name' => $name, 'guard_name' => 'web'])->givePermissionTo($permission);
        }

        $this->admin = $this->staff('admin_user', 'admin');
        $this->manager = $this->staff('cash_manager', 'cashier_manager');
        $this->officerA = $this->staff('officer_a', 'cashier_officer');
        $this->officerB = $this->staff('officer_b', 'cashier_officer');
    }

    public function test_a_payment_remembers_the_cashier_who_recorded_it(): void
    {
        $this->actingAs($this->officerA);
        $payment = $this->payment('RB-001', 5);

        $this->assertSame($this->officerA->id, $payment->received_by);
        $this->assertTrue($payment->receivedBy->is($this->officerA));


        $this->assertSame($this->officerB->id, Payment::query()->create([
            'receipt_number' => 'RB-002',
            'amount_usd' => 5,
            'received_by' => $this->officerB->id,
        ])->received_by);
    }

    public function test_a_cashier_officer_defaults_to_their_own_payments_and_can_filter_all_cashiers(): void
    {
        [$mine, $theirs] = $this->paymentsByBothOfficers();

        $this->actingAs($this->officerA);

        $this->assertEqualsCanonicalizing([$mine->id, $theirs->id], PaymentResource::getEloquentQuery()->pluck('id')->all());
        $receiverOptions = (new ReflectionMethod(PaymentsTable::class, 'dynamicReceiverOptions'))->invoke(null);
        $this->assertArrayNotHasKey('all', $receiverOptions);
        $this->assertArrayHasKey((string) $this->officerA->id, $receiverOptions);
        $this->assertArrayHasKey((string) $this->officerB->id, $receiverOptions);

        Livewire::test(ListPayments::class)
            ->assertTableFilterExists('payment_filters')
            ->assertSet('tableFilters.payment_filters.received_by', fn ($value): bool => (string) $value === (string) $this->officerA->id)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs])
            ->assertTableColumnSummarySet('amount_usd', 'amount_usd_total', 5)
            ->set('tableFilters.payment_filters.received_by', null)
            ->assertCanSeeTableRecords([$mine, $theirs])
            ->assertTableColumnSummarySet('amount_usd', 'amount_usd_total', 15)
            ->set('tableFilters.payment_filters.received_by', (string) $this->officerA->id)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs])
            ->set('tableFilters.payment_filters.received_by', '')
            ->assertCanSeeTableRecords([$mine, $theirs])
            ->assertTableColumnSummarySet('amount_usd', 'amount_usd_total', 15);
    }

    public function test_admin_and_cashier_manager_default_to_their_own_payments_and_can_clear_or_filter_by_cashier(): void
    {
        [$mine, $theirs] = $this->paymentsByBothOfficers();

        foreach ([$this->admin, $this->manager] as $user) {
            $this->actingAs($user);

            $receiverOptions = (new ReflectionMethod(PaymentsTable::class, 'dynamicReceiverOptions'))->invoke(null);
            $this->assertArrayHasKey((string) $user->id, $receiverOptions);

            Livewire::test(ListPayments::class)
                ->assertTableColumnExists('received_by')
                ->assertSet('tableFilters.payment_filters.received_by', fn ($value): bool => (string) $value === (string) $user->id)
                ->assertCanNotSeeTableRecords([$mine, $theirs])
                ->set('tableFilters.payment_filters.received_by', null)
                ->assertTableColumnStateSet('received_by', 'Officer B', $theirs)
                ->assertCanSeeTableRecords([$mine, $theirs])
                ->assertTableColumnSummarySet('amount_usd', 'amount_usd_total', 15)
                ->set('tableFilters.payment_filters.received_by', (string) $this->officerB->id)
                ->assertCanSeeTableRecords([$theirs])
                ->assertCanNotSeeTableRecords([$mine])
                ->assertTableColumnSummarySet('amount_usd', 'amount_usd_total', 10)
                ->set('tableFilters.payment_filters.received_by', '')
                ->assertCanSeeTableRecords([$mine, $theirs])
                ->assertTableColumnSummarySet('amount_usd', 'amount_usd_total', 15);
        }
    }

    public function test_the_export_has_a_cashier_column(): void
    {
        [, $theirs] = $this->paymentsByBothOfficers();
        $this->admin->forceFill(['name_latin' => 'SITHAN VIRATANA'])->save();
        $this->actingAs($this->admin);

        $exportRow = new ReflectionMethod(PaymentsTable::class, 'exportRow');
        $reportDefinitions = new ReflectionMethod(PaymentsTable::class, 'reportColumnDefinitions');
        $definitions = $reportDefinitions->invoke(null);

        $this->assertSame(['Officer B'], $exportRow->invoke(null, $theirs->load('receivedBy'), 1, ['received_by']));
        $this->assertArrayNotHasKey('received_by', $definitions);

        $reportSheet = new ReflectionMethod(PaymentsTable::class, 'reportSheet');
        $sheet = $reportSheet->invoke(null, [$theirs->load(['receivedBy', 'user'])]);
        $sheetRows = $sheet['rows'];
        $footerNameRow = end($sheetRows);
        $footerLabelRow = $sheetRows[count($sheetRows) - 2];
        $footerDateRow = $sheetRows[count($sheetRows) - 3];
        $this->assertSame('SITHAN VIRATANA', $footerNameRow[8]);
        $this->assertSame('អ្នកទទួល', $footerLabelRow[1]);
        $this->assertSame('អ្នកប្រគល់', $footerLabelRow[8]);
        $this->assertStringContainsString('រាជធានីភ្នំពេញ ថ្ងៃទី ', $footerDateRow[8]);
        $this->assertStringContainsString(' ខែ ', $footerDateRow[8]);
        $this->assertStringContainsString(' ឆ្នាំ ', $footerDateRow[8]);

        $worksheetXml = (new ReflectionMethod(PaymentsTable::class, 'worksheetXml'))
            ->invoke(null, $sheetRows, [], $sheet['options']);
        $this->assertStringContainsString('<mergeCell ref="I19:L19"/>', $worksheetXml);
        $this->assertStringContainsString('<t>អ្នកទទួល</t>', $worksheetXml);
        $this->assertStringContainsString('<t>អ្នកប្រគល់</t>', $worksheetXml);
        $this->assertStringContainsString('<t>SITHAN VIRATANA</t>', $worksheetXml);
        $this->assertStringContainsString('<rPr><b/></rPr><t xml:space="preserve">' . LocalizedDate::formalDayMonthYearParts(now())['day'] . '</t>', $worksheetXml);

        $pdfTemplate = new ReflectionMethod(TablePdfExporter::class, 'paymentRecordsHtml');
        $pdfHtml = $pdfTemplate->invoke(null, array_fill(0, 11, 'Column'), [], 'Payments', [
            'footer_text' => 'អ្នកប្រគល់',
            'footer_column' => 9,
            'footer_span' => 3,
            'footer_date_parts' => [
                ['text' => 'រាជធានីភ្នំពេញ'],
                ['text' => ' ថ្ងៃទី '],
                ['text' => '២២', 'bold' => true],
                ['text' => ' ខែ '],
                ['text' => 'តុលា', 'bold' => true],
                ['text' => ' ឆ្នាំ '],
                ['text' => '២០២៦', 'bold' => true],
            ],
            'footer_name' => 'SITHAN VIRATANA',
            'footer_receiver_text' => 'អ្នកទទួល',
        ]);
        $this->assertStringContainsString('>អ្នកទទួល</td>', $pdfHtml);
        $this->assertStringContainsString('>អ្នកប្រគល់</div>', $pdfHtml);
        $this->assertStringContainsString('>SITHAN VIRATANA</div>', $pdfHtml);
        $this->assertStringContainsString('រាជធានីភ្នំពេញ ថ្ងៃទី <strong class="report-date-bold">២២</strong> ខែ <strong class="report-date-bold">តុលា</strong> ឆ្នាំ <strong class="report-date-bold">២០២៦</strong>', $pdfHtml);
        $this->assertStringContainsString('width: 27.272727272727%', $pdfHtml);
        $this->assertStringContainsString('font-family: khmermuol, khmerbattambang, sans-serif; font-size: 10pt; font-weight: bold;', $pdfHtml);
    }

    public function test_payment_report_handed_over_name_prefers_khmer_and_falls_back_to_latin(): void
    {
        $this->admin->forceFill([
            'name_latin' => 'SYSTEM ADMIN',
            'first_name_kh' => 'ប្រព័ន្ធ',
            'last_name_kh' => 'អ្នកគ្រប់គ្រង',
        ])->save();
        $this->actingAs($this->admin);

        $exportingUserName = new ReflectionMethod(PaymentsTable::class, 'exportingUserName');

        $this->assertSame('ប្រព័ន្ធ អ្នកគ្រប់គ្រង', $exportingUserName->invoke(null));

        $this->admin->forceFill([
            'first_name_kh' => null,
            'last_name_kh' => null,
        ])->save();

        $this->assertSame('SYSTEM ADMIN', $exportingUserName->invoke(null));
    }

    /**
     * @return array{0: Payment, 1: Payment}
     */
    private function paymentsByBothOfficers(): array
    {
        $this->actingAs($this->officerA);
        $mine = $this->payment('RB-A', 5);

        $this->actingAs($this->officerB);
        $theirs = $this->payment('RB-B', 10);

        return [$mine, $theirs];
    }

    private function payment(string $receipt, float $usd): Payment
    {
        return Payment::query()->create([
            'receipt_number' => $receipt,
            'type_payment' => 'cash',
            'status_payt' => 'paid',
            'amount_usd' => $usd,
            'amount_kh' => $usd * 4100,
            'datetime_pay' => now(),
        ]);
    }

    private function staff(string $username, string $role): User
    {
        $user = User::query()->forceCreate([
            'registration_type' => 'admin',
            'name' => ucwords(str_replace('_', ' ', $username)),
            'username' => $username,
            'email' => $username.'@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }
}
