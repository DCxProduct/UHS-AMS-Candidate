<?php

namespace Tests\Feature;

use App\Filament\Admin\Resources\PaymentTypes\Pages\CreatePaymentType;
use App\Filament\Admin\Resources\PaymentTypes\Pages\ListPaymentTypes;
use App\Filament\Admin\Resources\Payments\Pages\ListPayments;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentTypeClassificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::query()->create(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::query()->create([
            'registration_type' => 'admin',
            'name' => 'Payment Admin',
            'username' => 'payment_admin',
            'email' => 'payment-admin@example.test',
            'date_of_birth' => '2000-01-01',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $admin->assignRole('admin');
        $this->actingAs($admin);
    }

    public function test_payment_type_can_be_marked_as_cash_or_bank(): void
    {
        Livewire::test(CreatePaymentType::class)
            ->fillForm([
                'key' => 'aba',
                'abbreviation' => 'ABA',
                'name_en' => 'ABA',
                'name_kh' => 'ABA',
                'method' => 'bank',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $bankType = PaymentType::query()->where('key', 'aba')->sole();
        $this->assertSame('bank', $bankType->method);
        $this->assertSame('ABA', $bankType->abbreviation);

        Livewire::test(ListPaymentTypes::class)
            ->assertTableColumnExists('localized_method')
            ->assertTableColumnExists('abbreviation')
            ->assertTableColumnStateSet('abbreviation', 'ABA', $bankType)
            ->assertTableColumnStateSet('localized_method', __('payment_types.options.methods.bank'), $bankType);
    }

    public function test_payment_type_requires_abbreviation_but_allows_missing_names(): void
    {
        Livewire::test(CreatePaymentType::class)
            ->fillForm([
                'key' => 'wire',
                'name_en' => null,
                'name_kh' => null,
                'method' => 'bank',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['abbreviation' => 'required']);

        Livewire::test(CreatePaymentType::class)
            ->fillForm([
                'key' => 'wire',
                'abbreviation' => 'WIRE',
                'name_en' => null,
                'name_kh' => null,
                'method' => 'bank',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $paymentType = PaymentType::query()->where('key', 'wire')->sole();
        $this->assertSame('WIRE', $paymentType->abbreviation);
        $this->assertNull($paymentType->name_en);
        $this->assertNull($paymentType->name_kh);
    }

    public function test_payment_records_show_cash_or_bank_and_bank_name_separately(): void
    {
        $bank = PaymentType::query()->create([
            'key' => 'aba',
            'abbreviation' => 'ABA',
            'name_en' => 'ABA Bank Cambodia',
            'name_kh' => 'ធនាគារ ABA កម្ពុជា',
            'method' => 'bank',
            'is_active' => true,
        ]);
        $cash = PaymentType::query()->create([
            'key' => 'cash',
            'name_en' => 'Cash',
            'name_kh' => 'សាច់ប្រាក់',
            'method' => 'cash',
            'is_active' => true,
        ]);

        $this->assertSame([
            'bank' => __('payment_types.options.methods.bank'),
            'cash' => __('payment_types.options.methods.cash'),
        ], PaymentType::activeMethodOptions());
        $this->assertSame(['aba' => 'ABA'], PaymentType::activeOptionsForMethod('bank'));
        $this->assertSame(['cash' => $cash->localized_name], PaymentType::activeOptionsForMethod('cash'));
        $this->assertSame('cash', PaymentType::activeKeyForMethod('cash'));

        $bankPayment = new Payment(['type_payment' => 'aba']);
        $bankPayment->setRelation('paymentType', $bank);
        $cashPayment = new Payment(['type_payment' => 'cash']);
        $cashPayment->setRelation('paymentType', $cash);

        $this->assertSame(__('payment_types.options.methods.bank'), $bankPayment->payment_method_label);
        $this->assertSame('ABA', $bankPayment->bank_name);
        $this->assertSame(__('payment_types.options.methods.cash'), $cashPayment->payment_method_label);
        $this->assertSame('-', $cashPayment->bank_name);

        foreach ([$bankPayment, $cashPayment] as $index => $payment) {
            $payment->fill([
                'receipt_number' => 'BANK-COLUMN-'.$index,
                'amount_usd' => 5,
                'datetime_pay' => now(),
            ])->save();
        }

        Livewire::test(ListPayments::class)
            ->assertTableColumnExists('type_payment')
            ->assertTableColumnExists('bank_name')
            ->assertTableColumnStateSet('bank_name', '-', $cashPayment)
            ->assertTableColumnStateSet('bank_name', 'ABA', $bankPayment);
    }
}
