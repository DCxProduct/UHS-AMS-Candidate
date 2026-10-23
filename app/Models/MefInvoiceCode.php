<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MefInvoiceCode extends Model
{
    protected $fillable = [
        'mef_code',
        'invoice_code',
    ];

    public static function sanitizeInvoiceCode(mixed $value): string
    {
        return preg_replace('/[^\p{N}\p{P}\p{S}]/u', '', trim((string) $value)) ?? '';
    }

    public function setInvoiceCodeAttribute(mixed $value): void
    {
        $this->attributes['invoice_code'] = static::sanitizeInvoiceCode($value);
    }

    public static function resolveReceiptNumber(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $receiptNumber = trim((string) $value);

        $invoiceCode = static::query()
            ->where('mef_code', $receiptNumber)
            ->value('invoice_code');

        if ($invoiceCode !== null) {
            return $invoiceCode;
        }

        return static::query()
            ->where('invoice_code', $receiptNumber)
            ->value('invoice_code');
    }
}
