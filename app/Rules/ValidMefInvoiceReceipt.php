<?php

namespace App\Rules;

use App\Models\MefInvoiceCode;
use App\Models\Payment;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidMefInvoiceReceipt implements ValidationRule
{
    public function __construct(
        private readonly ?int $ignorePaymentId = null,
        private readonly ?string $existingReceiptNumber = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $invoiceCode = MefInvoiceCode::resolveReceiptNumber($value);

        if ($invoiceCode === null && $this->existingReceiptNumber !== null
            && trim((string) $value) === $this->existingReceiptNumber) {
            $invoiceCode = $this->existingReceiptNumber;
        }

        if ($invoiceCode === null) {
            $fail(__('payments.validation.receipt_number_mef_invalid'));

            return;
        }

        $alreadyUsed = Payment::query()
            ->where('receipt_number', $invoiceCode)
            ->when($this->ignorePaymentId, fn ($query) => $query->whereKeyNot($this->ignorePaymentId))
            ->exists();

        if ($alreadyUsed) {
            $fail(__('payments.validation.receipt_number_unique'));
        }
    }
}
