<?php

namespace App\Services;

use App\Models\AccountIdentity;
use App\Models\AccountUser;
use App\Models\NewStudentAccount;
use App\Models\Role;
use App\Models\StudentLogin;
use App\Models\SystemUser;
use App\Models\User;
use App\Support\AccountContacts;
use App\Support\CandidateLatinName;
use App\Support\StaffRoles;
use App\Support\UserTypeOptions;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

final class AmsStudentAccountTransfer
{
    public function receive(array $data): NewStudentAccount
    {
        $data['student_id'] = trim($data['student_id']);
        $data['email'] = AccountContacts::email($data['email'] ?? null);
        $data['phone'] = AccountContacts::phone($data['phone'] ?? null);
        $data['first_name_latin'] = trim($data['first_name_latin']);
        $data['last_name_latin'] = trim($data['last_name_latin']);

        return DB::transaction(function () use ($data): NewStudentAccount {
            $legacy = AccountContacts::matching(SystemUser::query(), $data)
                ->orderBy('id')->lockForUpdate()->get();
            $this->ensure($legacy->count() <= 1);
            $mirror = $legacy->first();
            if ($mirror !== null) {
                $this->ensureCandidateMirror($mirror);
            }

            $existing = NewStudentAccount::query()->where('student_id', $data['student_id'])->first();
            $matches = AccountUser::withTrashed()->where(function (Builder $query) use ($data, $mirror, $existing): void {
                $query->where(fn (Builder $contacts): Builder => AccountContacts::matching($contacts, $data));
                if ($mirror !== null) {
                    $query->orWhere(fn (Builder $contacts): Builder => AccountContacts::matching($contacts, $mirror->only(['email', 'phone'])));
                    if (filled($mirror->username)) {
                        $query->orWhere('username', $mirror->username);
                    }
                }
                if ($existing?->auth_id !== null) {
                    $query->orWhereKey($existing->auth_id);
                }
            })->get();
            $this->ensure($matches->count() <= 1);
            $user = $matches->first();
            if ($user !== null) {
                // Lock the identity and concrete row, since PostgreSQL cannot lock a UNION view.
                AccountIdentity::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
                $user = AccountUser::withTrashed()->findOrFail($user->getKey());
                $user = $user->newQuery()->withTrashed()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
                $this->ensure(! $user->trashed() && $user->registration_type === 'student'
                    && ! $user->hasEffectiveRole($this->staffRoles()));
                $this->ensure($mirror === null || $this->sameIdentity($mirror, $user));
                if ($user instanceof StudentLogin) {
                    $this->ensure($user->student_id === $data['student_id']);
                }
            }

            $account = NewStudentAccount::query()->where('student_id', $data['student_id'])
                ->lockForUpdate()->first() ?? new NewStudentAccount;
            $this->ensure(! AccountContacts::matching(NewStudentAccount::query(), $data)
                ->where('student_id', '!=', $data['student_id'])->lockForUpdate()->exists());
            if ($account->exists) {
                $this->ensure($account->auth_id === null || (string) $account->auth_id === (string) $user?->getKey());
                $this->ensure($account->source_candidate_id === null || $user?->source_candidate_id === null
                    || (string) $account->source_candidate_id === (string) $user->source_candidate_id);
                $this->ensure($mirror === null || $account->source_candidate_id === null
                    || (string) $account->source_candidate_id === (string) $mirror->id);
                if ($account->auth_id === null && ($user !== null || $mirror !== null)) {
                    $this->ensure($this->sharesContact($account, $user ?? $mirror)
                        || (! filled($account->email) && ! filled($account->phone)));
                }
            }

            $originId = $account->source_candidate_id;
            $originalRoles = $this->roles($account->candidate_roles);
            $originalContacts = $account->only(['email', 'phone']);
            // AMS may split a family-first full name using the opposite name
            // convention. Correct only an exact reversal of a known account name.
            $knownName = CandidateLatinName::normalize($user?->name_latin ?? $account->name_latin);
            $incomingName = CandidateLatinName::join($data['first_name_latin'], $data['last_name_latin']);
            $reversedName = CandidateLatinName::join($data['last_name_latin'], $data['first_name_latin']);
            if ($knownName !== '' && mb_strtolower($incomingName) !== mb_strtolower($knownName)
                && mb_strtolower($reversedName) === mb_strtolower($knownName)) {
                [$data['first_name_latin'], $data['last_name_latin']] = [$data['last_name_latin'], $data['first_name_latin']];
            }
            if ($user !== null && ! $user instanceof StudentLogin) {
                $profile = array_intersect_key($user->getRawOriginal(), array_flip(Schema::getColumnListing('new_student_accounts')));
                unset($profile['id'], $profile['created_at'], $profile['updated_at']);
                $account->forceFill($profile);
                $account->forceFill(['auth_id' => $user->getKey(), 'user_id' => $user->getKey()]);
            } elseif ($user === null && $mirror !== null) {
                // Older management-only candidates never had a logical login ID.
                $account->forceFill([
                    'name' => $mirror->name, 'username' => $mirror->username,
                    'first_name_kh' => $mirror->first_name_kh, 'last_name_kh' => $mirror->last_name_kh,
                    'avatar' => $mirror->avatar, 'password' => $mirror->password,
                    'email_verified_at' => $mirror->email_verified_at,
                    'date_of_birth' => '2000-01-01',
                ]);
            }

            $roles = $this->roles($user?->candidate_roles ?? []);
            // Earlier deliveries already moved candidate classification into
            // the destination, before canonical users gained that metadata.
            if ($originId !== null && $user?->source_candidate_id === null && $originalRoles !== []) {
                $roles = $originalRoles;
            }
            if ($roles === []) {
                $roles = $mirror !== null ? $this->roles($mirror->roles) : $this->roles($user?->getRoleNames()->all() ?? []);
            }
            $account->forceFill([
                'source_candidate_id' => $originId ?? $user?->source_candidate_id ?? $mirror?->id,
                'candidate_roles' => $roles !== [] ? $roles : ($account->candidate_roles ?? []),
                'is_active' => ($account->exists ? (bool) $account->getRawOriginal('is_active') : true)
                    && (bool) ($user?->is_active ?? $mirror?->is_active ?? $account->is_active),
                'name_latin' => trim($data['first_name_latin'].' '.$data['last_name_latin']),
            ]);
            foreach (['email', 'phone'] as $field) {
                // Absent AMS contacts must not erase an existing login identifier.
                $data[$field] ??= ($originalContacts[$field] ?? null) ?: $user?->{$field} ?: $mirror?->{$field};
            }
            $account->fill($data)->save();

            if ($user !== null && ! $user instanceof StudentLogin) {
                // Keep ownership, permissions, sessions and notifications under the same logical ID.
                DB::table('users')->where('id', $user->getKey())->delete();
                AccountIdentity::query()->whereKey($user->getKey())->update(['storage' => 'student']);
            }
            if ($user === null && $mirror !== null) {
                $login = AccountUser::query()->findOrFail($account->auth_id);
                $roleNames = collect($this->roles($mirror->roles))->flatMap(fn (string $role): array => UserTypeOptions::assignableWebRoles($role))->unique();
                foreach ($roleNames as $role) {
                    $login->assignRole(Role::findOrCreate($role, 'web'));
                }
                $login->syncPermissions(Permission::query()->where('guard_name', 'web')
                    ->whereIn('name', $this->permissionNames($mirror->permissions))->pluck('name'));
            }
            if ($mirror !== null) {
                DB::table($mirror->getTable())->where('id', $mirror->id)->delete();
            }

            return $account;
        }, attempts: 3);
    }

    private function ensureCandidateMirror(SystemUser $source): void
    {
        $roles = $this->roles($source->roles);
        $this->ensure(! $source->isProtectedAdminAccount()
            && array_intersect($roles, UserTypeOptions::candidateManagedRoleKeys()) !== []
            && array_intersect($roles, $this->staffRoles()) === []);
    }

    private function staffRoles(): array
    {
        $roles = StaffRoles::STAFF;
        if (Schema::hasColumn('roles', 'role_type_key')) {
            $roles = [...$roles, ...Role::query()->where('guard_name', 'web')
                ->where('role_type_key', 'staff')->pluck('name')->all()];
        }

        return $this->roles($roles);
    }

    private function sharesContact(NewStudentAccount $account, User|SystemUser $source): bool
    {
        return (AccountContacts::email($account->email) !== null
                && AccountContacts::email($account->email) === AccountContacts::email($source->email))
            || (AccountContacts::phone($account->phone) !== null
                && AccountContacts::phone($account->phone) === AccountContacts::phone($source->phone));
    }

    private function sameIdentity(SystemUser $source, User $user): bool
    {
        return (filled($source->username) && $source->username === $user->username)
            || (AccountContacts::email($source->email) !== null && AccountContacts::email($source->email) === AccountContacts::email($user->email))
            || (AccountContacts::phone($source->phone) !== null && AccountContacts::phone($source->phone) === AccountContacts::phone($user->phone));
    }

    private function permissionNames(mixed $values): array
    {
        if (is_string($values)) {
            $values = json_decode($values, true) ?: [$values];
        }

        return collect(is_array($values) ? $values : [])->filter(fn ($value): bool => is_string($value) && filled($value))->values()->all();
    }

    private function roles(mixed $roles): array
    {
        return collect($this->permissionNames($roles))->map(fn (string $role): string => mb_strtolower(trim($role)))->unique()->values()->all();
    }

    private function ensure(bool $condition): void
    {
        if (! $condition) {
            throw new DomainException('Account identity conflicts with an existing account. No records were moved.');
        }
    }
}
