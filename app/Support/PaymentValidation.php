<?php

namespace App\Support;

use Illuminate\Support\Facades\Validator;

class PaymentValidation
{
    public static function amountKhRules(bool $required = true): array
    {
        return array_values(array_filter([
            $required ? 'required' : 'nullable',
            static function (string $attribute, mixed $value, \Closure $fail): void {
                if (blank($value)) {
                    return;
                }

                $normalized = str_replace(',', '', (string) $value);

                if (! is_numeric($normalized) || (float) $normalized <= 0) {
                    $fail(__('payments.validation.amount_positive'));
                }
            },
        ]));
    }

    public static function amountUsdRules(): array
    {
        return ['nullable', 'numeric', 'gt:0'];
    }

    public static function validateAmounts(array $attributes): void
    {
        Validator::make([
            ...$attributes,
            'amount_kh' => filled($attributes['amount_kh'] ?? null)
                ? str_replace(',', '', (string) $attributes['amount_kh'])
                : ($attributes['amount_kh'] ?? null),
        ], [
            'amount_kh' => ['nullable', 'numeric', 'gt:0'],
            'amount_usd' => self::amountUsdRules(),
        ])->after(function ($validator) use ($attributes): void {
            if (blank($attributes['amount_kh'] ?? null) && blank($attributes['amount_usd'] ?? null)) {
                $validator->errors()->add('amount_kh', __('payments.validation.amount_kh_required'));
            }
        })->validate();
    }
}
