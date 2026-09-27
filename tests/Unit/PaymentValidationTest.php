<?php

namespace Tests\Unit;

use App\Support\PaymentValidation;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaymentValidationTest extends TestCase
{
    public static function invalidAmounts(): array
    {
        return [
            'zero khmer amount' => [['amount_kh' => '0', 'amount_usd' => null]],
            'negative khmer amount' => [['amount_kh' => '-1', 'amount_usd' => null]],
            'negative usd amount' => [['amount_kh' => null, 'amount_usd' => '-100']],
            'malformed amount' => [['amount_kh' => 'abc', 'amount_usd' => null]],
            'blank amounts' => [['amount_kh' => null, 'amount_usd' => null]],
        ];
    }

    #[DataProvider('invalidAmounts')]
    public function test_invalid_payment_amounts_are_rejected(array $amounts): void
    {
        $this->expectException(ValidationException::class);

        PaymentValidation::validateAmounts($amounts);
    }

    public function test_positive_payment_amounts_are_accepted(): void
    {
        PaymentValidation::validateAmounts(['amount_kh' => '0.01', 'amount_usd' => null]);
        PaymentValidation::validateAmounts(['amount_kh' => '1,000', 'amount_usd' => '100.00']);

        $this->addToAssertionCount(2);
    }
}
