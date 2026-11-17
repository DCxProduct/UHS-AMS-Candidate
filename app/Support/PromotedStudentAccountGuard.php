<?php

namespace App\Support;

use App\Models\AccountUser;
use App\Models\NewStudentAccount;
use App\Models\SystemUser;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Keep promoted identities out of the candidate/staff account mirror. */
final class PromotedStudentAccountGuard implements ValidationRule
{
    public function __construct(private readonly string $field) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::canUseContact($this->field, $value)) {
            $fail(self::message($this->field));
        }
    }

    public static function canUseContact(string $field, mixed $value): bool
    {
        if (! in_array($field, ['email', 'phone'], true) || ! self::available()) {
            return true;
        }

        $value = is_scalar($value) ? (string) $value : null;
        $normalized = $field === 'email' ? AccountContacts::email($value) : AccountContacts::phone($value);
        if ($normalized === null) {
            return true;
        }

        $contacts = [$field => $normalized];
        if (AccountContacts::matching(self::promoted(), $contacts)->exists()) {
            return false;
        }

        // The login account can retain earlier contacts or change its Profile
        // after promotion. Those identifiers must not create a second mirror.
        $logins = AccountContacts::matching(self::logins(), $contacts);
        $logins->select($logins->getModel()->qualifyColumn('id'));

        return ! self::promoted()->whereIn(self::ownerColumn(), $logins)->exists();
    }

    public static function assertCanSave(SystemUser $account): void
    {
        if (! self::available()) {
            return;
        }

        $errors = [];
        foreach (['email', 'phone'] as $field) {
            if (! self::canUseContact($field, $account->getAttribute($field))) {
                $errors[$field] = self::message($field);
            }
        }

        $username = mb_strtolower(trim((string) $account->username));
        if ($username !== '') {
            $logins = self::logins()->whereRaw('LOWER(TRIM(username)) = ?', [$username]);
            $logins->select($logins->getModel()->qualifyColumn('id'));
            if (self::promoted()->whereIn(self::ownerColumn(), $logins)->exists()) {
                $errors['username'] = self::message('username');
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private static function available(): bool
    {
        return Schema::hasColumn('new_student_accounts', self::ownerColumn())
            && Schema::hasColumn('new_student_accounts', 'source_candidate_id');
    }

    private static function promoted(): Builder
    {
        return NewStudentAccount::query()->where(fn (Builder $origin): Builder => $origin
            ->whereNotNull(self::ownerColumn())->orWhereNotNull('source_candidate_id'));
    }

    private static function ownerColumn(): string
    {
        return Schema::hasColumn('new_student_accounts', 'auth_id') ? 'auth_id' : 'user_id';
    }

    private static function logins(): Builder
    {
        return AccountStorage::ready() ? AccountUser::withTrashed() : User::withTrashed();
    }

    private static function message(string $field): string
    {
        return __('validation.unique', ['attribute' => $field === 'username'
            ? __('system_users.fields.username')
            : __('candidate_lists.fields.'.$field)]);
    }
}
