<?php

namespace App\Models;

use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use App\Support\PaymentValidation;
use Throwable;

class Payment extends Model
{
    protected $fillable = [
        'users_id',
        'form_id',
        'custom_form_entry_id',
        'receipt_number',
        'payment_slip_path',
        'type_payment',
        'exchange_rate',
        'status_payt',
        'amount_usd',
        'amount_kh',
        'datetime_pay',
        'status',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'exchange_rate' => 'decimal:2',
            'amount_usd' => 'decimal:2',
            'amount_kh' => 'decimal:2',
            'datetime_pay' => 'datetime',
            'status' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Payment $payment): void {
            $payment->receipt_number = filled($payment->receipt_number)
                ? trim((string) $payment->receipt_number)
                : null;

            PaymentValidation::validateAmounts($payment->getAttributes());
        });
    }

    public static function isReceiptNumberUniqueViolation(Throwable $exception): bool
    {
        if (! $exception instanceof QueryException) {
            return false;
        }

        $message = strtolower($exception->getMessage());

        return in_array((string) $exception->getCode(), ['23000', '23505'], true)
            && str_contains($message, 'receipt_number');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'users_id');
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(CustomForm::class, 'form_id');
    }

    public function customFormEntry(): BelongsTo
    {
        return $this->belongsTo(CustomFormEntry::class, 'custom_form_entry_id');
    }

    public function paymentSlipUrl(): ?string
    {
        if (blank($this->payment_slip_path)) {
            return null;
        }

        return route('protected.payment-slip', ['payment' => $this]);
    }
}
