<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\MefInvoiceCode;
use App\Rules\ValidMefInvoiceReceipt;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PaymentIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_model_rejects_zero_payment_amount(): void
    {
        $this->expectException(ValidationException::class);

        Payment::query()->create([
            'receipt_number' => 'ZERO-001',
            'amount_kh' => '0',
        ]);
    }

    public function test_model_accepts_usd_amount_without_khr_amount(): void
    {
        $payment = Payment::query()->create([
            'receipt_number' => 'USD-ONLY-001',
            'amount_usd' => '29.27',
            'amount_kh' => null,
        ]);

        $this->assertSame('29.27', $payment->amount_usd);
        $this->assertNull($payment->amount_kh);
    }

    public function test_database_rejects_duplicate_receipt_number(): void
    {
        Payment::query()->create([
            'receipt_number' => 'DUP-001',
            'amount_kh' => '1000',
        ]);

        $this->expectException(QueryException::class);

        Payment::query()->create([
            'receipt_number' => 'DUP-001',
            'amount_kh' => '1000',
        ]);
    }

    public function test_invoice_code_resolved_from_mef_can_only_be_paid_once(): void
    {
        MefInvoiceCode::query()->create([
            'mef_code' => 'MEF-ONCE-001',
            'invoice_code' => '120--001',
        ]);
        MefInvoiceCode::query()->create([
            'mef_code' => 'MEF-ONCE-002',
            'invoice_code' => '120--001',
        ]);

        $invoiceCode = MefInvoiceCode::resolveReceiptNumber('MEF-ONCE-001');
        $this->assertSame('120--001', $invoiceCode);

        Payment::query()->create([
            'receipt_number' => $invoiceCode,
            'amount_kh' => '1000',
        ]);

        $this->expectException(QueryException::class);

        Payment::query()->create([
            'receipt_number' => MefInvoiceCode::resolveReceiptNumber('MEF-ONCE-002'),
            'amount_kh' => '1000',
        ]);
    }

    public function test_payment_receipt_validation_rejects_unknown_mef_codes(): void
    {
        $validator = Validator::make(
            ['receipt_number' => 'MEF-DOES-NOT-EXIST'],
            ['receipt_number' => [new ValidMefInvoiceReceipt]],
        );

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('receipt_number', $validator->errors()->toArray());
    }

    public function test_payment_receipt_validation_rejects_an_invoice_used_under_another_mef_code(): void
    {
        MefInvoiceCode::query()->create(['mef_code' => 'MEF-ALIAS-001', 'invoice_code' => '120--001']);
        MefInvoiceCode::query()->create(['mef_code' => 'MEF-ALIAS-002', 'invoice_code' => '120--001']);
        Payment::query()->create(['receipt_number' => '120--001', 'amount_kh' => '1000']);

        $validator = Validator::make(
            ['receipt_number' => 'MEF-ALIAS-001'],
            ['receipt_number' => [new ValidMefInvoiceReceipt]],
        );

        $this->assertFalse($validator->passes());
        $this->assertSame(
            __('payments.validation.receipt_number_unique'),
            $validator->errors()->first('receipt_number'),
        );
    }
}
