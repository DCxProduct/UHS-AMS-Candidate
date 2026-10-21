<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\Payments\Pages\ListPayments;
use App\Filament\Admin\Resources\Payments\PaymentResource;
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
        $this->assertSame(__('payments.options.all_cashiers'), $receiverOptions['all']);
        $this->assertArrayHasKey((string) $this->officerA->id, $receiverOptions);
        $this->assertArrayHasKey((string) $this->officerB->id, $receiverOptions);

        Livewire::test(ListPayments::class)
            ->assertTableFilterExists('payment_filters')
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs])
            ->assertTableColumnSummarySet('amount_usd', 'amount_usd_total', 5)
            ->set('tableFilters.payment_filters.received_by', 'all')
            ->assertCanSeeTableRecords([$mine, $theirs])
            ->assertTableColumnSummarySet('amount_usd', 'amount_usd_total', 15);
    }

    public function test_admin_and_cashier_manager_see_all_and_can_filter_by_cashier(): void
    {
        [$mine, $theirs] = $this->paymentsByBothOfficers();

        foreach ([$this->admin, $this->manager] as $user) {
            $this->actingAs($user);

            Livewire::test(ListPayments::class)
                ->assertTableColumnExists('received_by')
                ->assertTableColumnStateSet('received_by', 'Officer B', $theirs)
                ->assertCanSeeTableRecords([$mine, $theirs])
                ->assertTableColumnSummarySet('amount_usd', 'amount_usd_total', 15)
                ->set('tableFilters.payment_filters.received_by', (string) $this->officerB->id)
                ->assertCanSeeTableRecords([$theirs])
                ->assertCanNotSeeTableRecords([$mine])
                ->assertTableColumnSummarySet('amount_usd', 'amount_usd_total', 10);
        }
    }

    public function test_the_export_has_a_cashier_column(): void
    {
        [, $theirs] = $this->paymentsByBothOfficers();
        $this->actingAs($this->admin);

        $exportRow = new ReflectionMethod(PaymentsTable::class, 'exportRow');
        $reportDefinitions = new ReflectionMethod(PaymentsTable::class, 'reportColumnDefinitions');
        $definitions = $reportDefinitions->invoke(null);

        $this->assertSame(['Officer B'], $exportRow->invoke(null, $theirs->load('receivedBy'), 1, ['received_by']));
        $this->assertArrayNotHasKey('received_by', $definitions);

        $reportSheet = new ReflectionMethod(PaymentsTable::class, 'reportSheet');
        $sheet = $reportSheet->invoke(null, [$theirs->load(['receivedBy', 'user'])]);
        $sheetRows = $sheet['rows'];
        $footerRow = end($sheetRows);
        $this->assertSame('អ្នកទទួល', $footerRow[8]);

        $pdfTemplate = new ReflectionMethod(TablePdfExporter::class, 'paymentRecordsHtml');
        $pdfHtml = $pdfTemplate->invoke(null, array_fill(0, 11, 'Column'), [], 'Payments', [
            'footer_text' => 'អ្នកទទួល',
            'footer_column' => 9,
        ]);
        $this->assertStringContainsString('>អ្នកទទួល</div>', $pdfHtml);
        $this->assertStringContainsString('margin-left: 72.727272727273%', $pdfHtml);
        $this->assertStringContainsString('font-family: khmermuol, khmerbattambang, sans-serif; font-size: 10pt; font-weight: bold;', $pdfHtml);
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
