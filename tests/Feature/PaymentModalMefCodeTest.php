<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\PaymentStatusLists\Pages\ListPaymentStatusLists;
use App\Models\MefInvoiceCode;
use App\Models\Payment;
use App\Models\PaymentStatusList;
use App\Models\PaymentType;
use App\Models\Role;
use App\Models\User;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PaymentModalMefCodeTest extends TestCase
{
    use RefreshDatabase;

    private PaymentStatusList $entry;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('app');
        app()->setLocale('en');

        $role = Role::findOrCreate('admin', 'web');
        foreach (['ViewAny', 'Pay'] as $action) {
            $role->givePermissionTo(Permission::findOrCreate($action.':PaymentStatusList', 'web'));
        }
        $admin = $this->user('mef_payment_admin', 'admin');
        $admin->assignRole($role);
        $this->actingAs($admin);

        PaymentType::query()->create([
            'key' => 'cash',
            'name_en' => 'Cash',
            'name_kh' => 'សាច់ប្រាក់',
            'method' => 'cash',
            'is_active' => true,
        ]);
        $form = CustomForm::query()->create([
            'name' => 'MEF Payment Form',
            'slug' => 'mef-payment-form',
            'is_active' => true,
            'requires_payment' => true,
        ]);
        $this->entry = PaymentStatusList::query()->forceCreate([
            'custom_form_id' => $form->getKey(),
            'created_by' => $this->user('mef_payment_candidate', 'student')->getKey(),
            'review_status' => 'approved',
            'data' => ['registration_status' => 'approved'],
        ]);
    }

    public function test_numeric_mef_suffix_records_the_canonical_invoice_for_the_eligible_application(): void
    {
        $this->map('MEF1', '120--0001');

        $component = Livewire::test(ListPaymentStatusLists::class)
            ->assertTableActionVisible('pay', $this->entry)
            ->callTableAction('pay', $this->entry, data: $this->paymentData('1'))
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', [
            'custom_form_entry_id' => $this->entry->getKey(),
            'users_id' => $this->entry->created_by,
            'form_id' => $this->entry->custom_form_id,
            'receipt_number' => '120--0001',
            'type_payment' => 'cash',
            'status_payt' => 'paid',
            'received_by' => auth()->id(),
        ]);
        $this->assertSame('1000.00', Payment::query()->sole()->amount_kh);
        $component->call('$refresh')->assertTableActionHidden('pay', $this->entry);
    }

    public function test_numeric_input_keeps_leading_zeroes_when_resolving_the_mef_code(): void
    {
        $this->map('MEF001', '120--0001');
        $this->map('MEF1', '120--1');

        Livewire::test(ListPaymentStatusLists::class)
            ->callTableAction('pay', $this->entry, data: $this->paymentData('001'))
            ->assertHasNoTableActionErrors();

        $this->assertSame('120--0001', Payment::query()->sole()->receipt_number);
    }

    public function test_blur_keeps_the_numeric_input_and_shows_the_matching_invoice_separately(): void
    {
        $this->map('MEF001', '120--0001');

        $component = Livewire::test(ListPaymentStatusLists::class)
            ->mountTableAction('pay', $this->entry)
            ->setTableActionData(['receipt_number' => '001'])
            ->assertTableActionDataSet(['receipt_number' => '001']);

        $instance = $component->instance();
        $receipt = $instance->{$instance->getMountedActionSchemaName()}->getFlatFields()['receipt_number'];
        $this->assertSame('MEF', $receipt->getPrefixLabel());
        $this->assertStringContainsString('120--0001', $receipt->toHtml());

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_server_rejects_letters_punctuation_and_prefixed_or_invoice_input(): void
    {
        $this->map('MEF1', '120--0001');

        foreach (['ABC', '1A', '1-2', '1.5', 'MEF1', '120--0001'] as $value) {
            Livewire::test(ListPaymentStatusLists::class)
                ->callTableAction('pay', $this->entry, data: $this->paymentData($value))
                ->assertHasTableActionErrors(['receipt_number' => 'regex']);
        }

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_unknown_and_empty_mef_numbers_are_rejected(): void
    {
        $component = Livewire::test(ListPaymentStatusLists::class)
            ->callTableAction('pay', $this->entry, data: $this->paymentData('999'))
            ->assertHasTableActionErrors(['receipt_number']);
        $this->assertContains(
            __('payments.validation.receipt_number_mef_invalid'),
            $component->instance()->getErrorBag()->all(),
        );

        Livewire::test(ListPaymentStatusLists::class)
            ->callTableAction('pay', $this->entry, data: $this->paymentData(''))
            ->assertHasTableActionErrors(['receipt_number' => 'required']);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_an_invoice_already_paid_under_another_mef_number_is_rejected(): void
    {
        $this->map('MEF1', '120--0001');
        $this->map('MEF2', '120--0001');
        Payment::query()->create(['receipt_number' => '120--0001', 'amount_kh' => 1000]);

        $component = Livewire::test(ListPaymentStatusLists::class)
            ->callTableAction('pay', $this->entry, data: $this->paymentData('2'))
            ->assertHasTableActionErrors(['receipt_number']);
        $this->assertContains(
            __('payments.validation.receipt_number_unique'),
            $component->instance()->getErrorBag()->all(),
        );

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseMissing('payments', ['custom_form_entry_id' => $this->entry->getKey()]);
    }

    public function test_numeric_suffix_respects_the_mef_code_length_limit(): void
    {
        Livewire::test(ListPaymentStatusLists::class)
            ->callTableAction('pay', $this->entry, data: $this->paymentData(str_repeat('1', 98)))
            ->assertHasTableActionErrors(['receipt_number' => 'max']);

        $this->assertDatabaseCount('payments', 0);
    }

    private function map(string $mef, string $invoice): void
    {
        MefInvoiceCode::query()->create(['mef_code' => $mef, 'invoice_code' => $invoice]);
    }

    private function paymentData(string $number): array
    {
        return ['receipt_number' => $number, 'payment_method' => 'cash', 'amount_kh' => '1000'];
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
