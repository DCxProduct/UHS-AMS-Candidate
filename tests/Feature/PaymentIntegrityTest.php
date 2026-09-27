<?php

namespace Tests\Feature;

use App\Models\Payment;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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
}
