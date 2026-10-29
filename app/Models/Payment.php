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
        'received_by',
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

        static::creating(function (Payment $payment): void {
            if ($payment->integration_source !== 'ams' && blank($payment->received_by) && auth()->check() && auth()->id() !== $payment->users_id) {
                $payment->received_by = auth()->id();
            }
        });

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

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function paidViaAms(): bool
    {
        return strtolower((string) $this->status_payt) === 'paid'
            && strtolower((string) $this->integration_source) === 'ams';
    }

    public function paymentSourceLabel(): string
    {
        if (strtolower((string) $this->status_payt) !== 'paid') {
            return '-';
        }

        return __('payments.payment_sources.'.($this->paidViaAms() ? 'ams' : 'candidate'));
    }

    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(PaymentType::class, 'type_payment', 'key');
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        if (blank($this->type_payment)) {
            return '-';
        }

        return $this->paymentType?->localized_method ?? PaymentType::localizedMethodFor($this->type_payment);
    }

    public function getPaymentMethodKeyAttribute(): string
    {
        return $this->paymentType?->method ?? PaymentType::methodForKey($this->type_payment);
    }

    public function getBankNameAttribute(): string
    {
        if (blank($this->type_payment) || $this->payment_method_key === 'cash') {
            return '-';
        }

        $paymentType = $this->paymentType;

        if ($paymentType?->method === 'bank') {
            return (string) ($paymentType->abbreviation ?: $paymentType->localized_name);
        }

        return $paymentType?->localized_name ?? PaymentType::localizedLabelFor($this->type_payment);
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
