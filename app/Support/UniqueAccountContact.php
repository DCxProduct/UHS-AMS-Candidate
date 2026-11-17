<?php

namespace App\Support;

use App\Models\AccountUser;
use App\Models\SystemUser;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Reserve identifiers across both login stores and the staff management mirror. */
final class UniqueAccountContact implements ValidationRule
{
    public function __construct(private readonly string $field, private readonly ?int $exceptAccountId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $contacts = [$this->field => is_scalar($value) ? (string) $value : null];
        $accounts = AccountContacts::matching(AccountUser::withTrashed(), $contacts);
        if ($this->exceptAccountId !== null) {
            $accounts->whereKeyNot($this->exceptAccountId);
        }

        if ($accounts->exists() || AccountContacts::matching(SystemUser::withTrashed(), $contacts)->exists()) {
            $fail(__('validation.unique', ['attribute' => __('candidate_lists.fields.'.$this->field)]));
        }
    }
}
