<?php

namespace App\Support;

use App\Filament\Admin\Resources\NewStudentAccounts\NewStudentAccountResource;
use App\Models\AccountIdentity;
use App\Models\NewStudentAccount;
use App\Models\StudentLogin;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class StudentAccountArchive
{
    public static function archive(NewStudentAccount $account): void
    {
        self::change($account, 'archiveAccount', static function (NewStudentAccount $locked): array {
            if ($locked->archived_at !== null) {
                return $locked->is_active ? ['is_active' => false] : [];
            }

            return ['archived_at' => now(), 'is_active' => false];
        });
    }

    public static function restore(NewStudentAccount $account): void
    {
        self::change($account, 'restoreAccount', static fn (NewStudentAccount $locked): array => $locked->archived_at === null
            ? []
            : ['archived_at' => null, 'is_active' => true]);
    }

    public static function setActive(NewStudentAccount $account, bool $active): void
    {
        self::change($account, $active ? 'activateAccount' : 'deactivateAccount', static function (NewStudentAccount $locked) use ($active): array {
            if ($active && $locked->archived_at !== null) {
                throw ValidationException::withMessages([
                    'account' => __('new_student_accounts.notifications.restore_before_activation'),
                ]);
            }

            return $locked->is_active === $active ? [] : ['is_active' => $active];
        });
    }

    /** Keep authoritative archive state while the surrounding save holds its locks. */
    public static function protectState(Model $account): void
    {
        if ((! $account instanceof NewStudentAccount && ! $account instanceof StudentLogin)
            || ! Schema::hasColumn('new_student_accounts', 'archived_at')
            || ! Schema::hasColumn('new_student_accounts', 'auth_id')) {
            return;
        }
        if (! $account->exists) {
            if ($account->getAttribute('archived_at') !== null) {
                $account->setAttribute('is_active', false);
            }

            return;
        }

        $query = DB::table('new_student_accounts');
        if ($account instanceof StudentLogin) {
            $query->where('auth_id', $account->getKey());
        } else {
            $query->where('id', $account->getKey());
        }
        $authId = (clone $query)->value('auth_id');
        self::lockIdentity($authId);
        $stored = $query->lockForUpdate()->first(['archived_at']);
        if ($stored === null) {
            return;
        }

        $account->setAttribute('archived_at', $stored->archived_at);
        if ($stored->archived_at !== null) {
            $account->setAttribute('is_active', false);
        }
    }

    private static function change(NewStudentAccount $account, string $action, Closure $changes): void
    {
        DB::transaction(static function () use ($account, $action, $changes): void {
            $authId = DB::table('new_student_accounts')->where('id', $account->getKey())->value('auth_id');
            self::lockIdentity($authId);
            $locked = NewStudentAccount::query()->whereKey($account->getKey())->lockForUpdate()->firstOrFail();
            abort_unless(auth()->user()?->registration_type === 'admin'
                && NewStudentAccountResource::can($action, $locked), 403);

            $attributes = $changes($locked);
            if ($attributes !== []) {
                DB::table('new_student_accounts')->where('id', $locked->getKey())
                    ->update([...$attributes, 'updated_at' => now()]);
            }
        });
        $account->refresh();
    }

    private static function lockIdentity(mixed $authId): void
    {
        if ($authId !== null) {
            AccountIdentity::query()->whereKey($authId)->lockForUpdate()->firstOrFail();
        }
    }
}
