<?php

namespace App\Support;

use App\Models\Role;
use App\Models\SystemUser;
use App\Models\User;
use App\Models\UserType;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Application eligibility is independent of account authorization roles. */
final class CandidateApplicationLevels
{
    public static function options(): array
    {
        $staffKeys = self::staffKeys();

        return UserTypeOptions::customQuery()->get()
            ->reject(fn (UserType $type): bool => in_array(strtolower($type->key), [...$staffKeys, 'candidate', 'student'], true))
            ->mapWithKeys(fn (UserType $type): array => [strtolower(trim($type->key)) => $type->getLocalizedLabel()])
            ->all();
    }

    public static function currentType(User $user): ?string
    {
        $categories = self::categoryKeys();
        $linked = self::linkedAccount($user);

        foreach ([self::roleValues($linked?->roles), $user->getRoleNames()->all()] as $roles) {
            foreach ($roles as $role) {
                if (is_string($role) && in_array(strtolower(trim($role)), $categories, true)) {
                    return strtolower(trim($role));
                }
            }
        }

        return null;
    }

    public static function switchOptions(User $user): array
    {
        $current = self::currentType($user);

        return array_diff_key(self::options(), $current === null ? [] : [$current => true]);
    }

    /** Form eligibility follows the current category, not previously selected levels. */
    public static function formRoleKeys(?User $user): array
    {
        if (! $user || ! DashboardUserAccess::isCandidate($user)) {
            return [];
        }

        $current = self::currentType($user);

        if ($current !== null) {
            return array_key_exists($current, self::options()) ? [$current] : [];
        }

        // Accounts predating candidate categories keep their actual role-based access.
        return $user->effectiveRoleNames()
            ->map(fn (string $role): string => strtolower(trim($role)))
            ->filter(fn (string $role): bool => UserTypeOptions::isActiveCandidateTypeRole($role))
            ->unique()
            ->values()
            ->all();
    }

    public static function canSwitch(?User $user): bool
    {
        return $user
            && $user->is_active
            && DashboardUserAccess::isCandidate($user)
            && self::switchOptions($user) !== [];
    }

    public static function switchTo(User $user, string $type): void
    {
        abort_unless(auth()->check() && (string) auth()->id() === (string) $user->getKey()
            && $user->is_active && DashboardUserAccess::isCandidate($user), 403);

        $type = strtolower(trim($type));
        Validator::make(['type' => $type], [
            'type' => ['required', 'string', Rule::in(array_keys(self::options()))],
        ])->validate();

        DB::transaction(function () use ($user, $type): void {
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($locked->is_active && DashboardUserAccess::isCandidate($locked), 403);
            $linked = self::linkedAccount($locked, lock: true);
            if (! $linked || ! $linked->isActiveAccount()) {
                throw ValidationException::withMessages(['type' => __('dashboard.application_levels.missing_account')]);
            }

            $current = self::currentType($locked);
            if ($current === $type) {
                return;
            }

            self::freezeHistory($locked, $linked);

            $categories = self::categoryKeys();
            $roles = array_filter(self::roleValues($linked->roles), fn (mixed $role): bool => ! is_string($role)
                || ! in_array(strtolower(trim($role)), $categories, true));
            $linked->forceFill(['roles' => [$type, ...array_values($roles)]])->saveQuietly();
        }, 3);

        $user->refresh();
    }

    private static function freezeHistory(User $user, SystemUser $linked): void
    {
        $model = new CustomFormEntry;
        $table = $model->getTable();
        $owners = [];
        foreach (['created_by', 'user_id', 'created_by_id', 'system_user_id'] as $column) {
            if (Schema::hasColumn($table, $column)) {
                $owners[$column] = $column === 'system_user_id' ? $linked->getKey() : $user->getKey();
            }
        }

        $entries = CustomFormEntry::query()->where(function (Builder $query) use ($owners): void {
            $query->whereRaw('1 = 0');
            $preceding = [];
            foreach ($owners as $column => $id) {
                $query->orWhere(function (Builder $owner) use ($preceding, $column, $id): void {
                    foreach ($preceding as $earlier) {
                        $owner->whereNull($earlier);
                    }
                    $owner->where($column, $id);
                });
                $preceding[] = $column;
            }
        })->with('customForm')->orderBy($model->getQualifiedKeyName())->lockForUpdate()->get();

        foreach ($entries as $entry) {
            if ($entry->customForm?->isProfileForm()) {
                continue;
            }
            $data = $entry->data ?? [];
            if (! is_array($data)) {
                throw ValidationException::withMessages(['type' => __('validation.array', ['attribute' => 'application data'])]);
            }
            if (array_key_exists(CandidateApplicationContext::DATA_KEY, $data)) {
                continue;
            }

            $entry->setRelation('creator', $user);
            $data[CandidateApplicationContext::DATA_KEY] = CandidateTypeResolver::forEntry($entry);
            DB::table($table)->where($model->getKeyName(), $entry->getKey())->update([
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]);
        }
    }

    private static function linkedAccount(User $user, bool $lock = false): ?SystemUser
    {
        $query = SystemUser::query();
        if (filled($user->username)) {
            $query->where('username', $user->username);
        } else {
            $identifiers = array_filter(['email' => $user->email, 'phone' => $user->phone], fn (mixed $value): bool => filled($value));
            if ($identifiers === []) {
                return null;
            }
            $query->where(function (Builder $query) use ($identifiers): void {
                foreach ($identifiers as $column => $value) {
                    $query->orWhere($column, $value);
                }
            });
        }

        $matches = $query->when($lock, fn (Builder $query): Builder => $query->lockForUpdate())->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private static function categoryKeys(): array
    {
        return UserTypeOptions::allQuery()->pluck('key')
            ->map(fn (string $key): string => strtolower(trim($key)))
            ->diff([...self::staffKeys(), 'candidate', 'student'])
            ->values()->all();
    }

    private static function staffKeys(): array
    {
        $keys = StaffRoles::STAFF;
        if (Schema::hasColumn('roles', 'role_type_key')) {
            $keys = [...$keys, ...Role::query()->where('guard_name', 'web')->where('role_type_key', 'staff')->pluck('name')->all()];
        }

        return array_map(fn (string $key): string => strtolower(trim($key)), $keys);
    }

    private static function roleValues(mixed $roles): array
    {
        if (is_string($roles)) {
            $decoded = json_decode($roles, true);
            $roles = is_array($decoded) ? $decoded : [$roles];
        }

        return is_array($roles) ? $roles : [];
    }
}
