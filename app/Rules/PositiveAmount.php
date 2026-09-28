<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PositiveAmount implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $normalized = str_replace(',', '', (string) $value);

        if (! is_numeric($normalized) || (float) $normalized <= 0) {
            $fail(__('payments.validation.amount_positive'));
        }
    }
}
