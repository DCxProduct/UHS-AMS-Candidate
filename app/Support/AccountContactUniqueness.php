<?php

namespace App\Support;

use App\Models\AccountUser;
use App\Models\NewStudentAccount;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class AccountContactUniqueness
{
    public static function assertForAuth(Model $account): void
    {
        if (! AccountStorage::ready()) {
            return;
        }
        $id = $account instanceof NewStudentAccount ? $account->auth_id : $account->getKey();
        $errors = [];
        foreach (['email', 'phone'] as $field) {
            $value = $account->getAttribute($field);
            $query = AccountContacts::matching(AccountUser::withTrashed(), [$field => $value]);
            if ($id !== null) {
                $query->where('account_users.id', '!=', $id);
            }
            if ($query->exists()) {
                $errors[$field] = __('validation.unique', ['attribute' => $field]);
            }
        }
        if (filled($account->username)) {
            $query = AccountUser::withTrashed()->whereRaw('LOWER(TRIM(username)) = ?', [mb_strtolower(trim($account->username))]);
            if ($id !== null) {
                $query->where('account_users.id', '!=', $id);
            }
            if ($query->exists()) {
                $errors['username'] = __('validation.unique', ['attribute' => 'username']);
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
