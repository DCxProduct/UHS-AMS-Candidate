<?php

namespace App\Console\Commands;

use App\Models\AccountUser;
use App\Models\Role;
use App\Models\StudentLogin;
use App\Models\SystemUser;
use App\Models\User;
use App\Models\UserType;
use App\Support\AccountContacts;
use App\Support\AccountStorage;
use App\Support\CandidateLatinName;
use App\Support\StaffRoles;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

class CanonicalizeCandidateAccounts extends Command
{
    protected $signature = 'accounts:canonicalize-candidates
        {--apply : Save the canonical candidate accounts and remove their legacy mirrors}';

    protected $description = 'Preview or apply candidate account canonicalization while retaining existing login credentials and history';

    public function handle(): int
    {
        if (! AccountStorage::ready()) {
            $this->error('Run the account storage migrations before canonicalizing candidates.');

            return self::FAILURE;
        }

        DB::beginTransaction();
        try {
            $staffRoles = $this->staffRoles();
            $candidateRoles = array_values(array_diff($this->normalizeRoles([
                'candidate', 'student', ...UserType::query()->pluck('key')->all(),
            ]), $staffRoles));
            $moved = 0;
            $skipped = 0;
            foreach (SystemUser::withTrashed()->orderBy('id')->lockForUpdate()->get() as $source) {
                if ($source->isProtectedAdminAccount()
                    || ! $this->isCandidateOnly($this->normalizeRoles($source->roles), $candidateRoles)) {
                    $skipped++;

                    continue;
                }
                $this->canonicalize($source, $candidateRoles, $staffRoles);
                $moved++;
            }

            $backfilled = 0;
            foreach (User::withTrashed()->where('registration_type', 'student')->orderBy('id')->lockForUpdate()->get() as $user) {
                if ($this->normalizeRoles($user->candidate_roles) !== []
                    || $user->hasEffectiveRole($staffRoles) || $this->isProtectedLogin($user)) {
                    continue;
                }
                $roles = $this->normalizeRoles($user->getRoleNames()->all());
                if ($roles !== [] && ! $this->isCandidateOnly($roles, $candidateRoles)) {
                    continue;
                }
                $user->forceFill(['candidate_roles' => $this->categoryRoles($roles)])->save();
                $backfilled++;
            }

            if ($this->option('apply')) {
                DB::commit();
                $this->info("Canonicalized {$moved} candidate mirrors; backfilled {$backfilled} candidate accounts; left {$skipped} staff or unclassified mirrors unchanged.");
            } else {
                DB::rollBack();
                $this->info("Preview: {$moved} candidate mirrors would be canonicalized; {$backfilled} accounts would be backfilled; {$skipped} staff or unclassified mirrors would remain. No changes saved. Use --apply to save.");
            }
        } catch (DomainException|ValidationException|UniqueConstraintViolationException $exception) {
            DB::rollBack();
            $this->error($exception instanceof DomainException
                ? $exception->getMessage().' No changes saved.'
                : 'Account identifiers conflict. No changes saved.');

            return self::FAILURE;
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        return self::SUCCESS;
    }

    private function canonicalize(SystemUser $source, array $candidateRoles, array $staffRoles): void
    {
        $sourceRoles = $this->normalizeRoles($source->roles);
        $this->ensure($this->matches(SystemUser::withTrashed()->whereKeyNot($source->id), $source, false)->doesntExist(), $source, 'has conflicting legacy account identifiers');
        $matches = $this->matches(AccountUser::withTrashed(), $source)->get();
        $this->ensure($matches->count() <= 1, $source, 'matches more than one login account');
        $user = $matches->first();
        if ($user !== null) {
            $this->ensure(! $user instanceof StudentLogin, $source, 'already belongs to New Student Accounts');
            $user = User::withTrashed()->whereKey($user->getKey())->lockForUpdate()->sole();
            $this->ensure(strtolower(trim((string) $user->registration_type)) === 'student', $source, 'matches a staff login account');
            $this->ensure(! $this->isProtectedLogin($user), $source, 'matches a protected admin login');
            $this->ensure(! $user->hasEffectiveRole($staffRoles), $source, 'matches a login with staff roles');
            $roles = $this->normalizeRoles($user->getRoleNames()->all());
            $this->ensure($roles === [] || $this->isCandidateOnly($roles, $candidateRoles), $source, 'matches a login with staff roles');
            $this->ensure($user->source_candidate_id === null || (int) $user->source_candidate_id === (int) $source->id, $source, 'matches a login linked to another legacy candidate');
        } else {
            $this->ensure(filled($source->username) || AccountContacts::email($source->email) !== null || AccountContacts::phone($source->phone) !== null, $source, 'has no usable login identifier');
            $this->ensure(filled($source->password), $source, 'has no usable password');
            $legacy = clone $source;
            $legacy->email = AccountContacts::email($source->email);
            $legacy->phone = AccountContacts::phone($source->phone);
            $this->ensure($legacy->phone === null || strlen($legacy->phone) <= 10, $source, 'has a phone number that cannot fit candidate storage');
            $legacy->username = filled(trim((string) $source->username))
                ? trim((string) $source->username)
                : CandidateLatinName::generateUsername($legacy->phone);
            $legacy->roles = array_values(array_unique([...$sourceRoles, 'candidate']));
            $legacy->syncLoginUser();
            $user = User::withTrashed()->where('username', $legacy->username)->sole();
            if ($source->trashed()) {
                $user->forceFill(['deleted_at' => $source->deleted_at])->save();
            }
        }

        // Only classification/origin metadata comes from the mirror. Existing
        // passwords, contacts, profile values and permission pivots stay current.
        $user->forceFill([
            'candidate_roles' => $this->normalizeRoles($user->candidate_roles) ?: $this->categoryRoles($sourceRoles),
            'source_candidate_id' => $source->id,
        ])->save();

        // SystemUser's model deletion hook also deletes its login and history.
        $this->ensure(DB::table('system_users')->where('id', $source->id)->delete() === 1, $source, 'could not be removed after canonicalization');
    }

    private function matches(Builder $query, SystemUser $source, bool $includeOrigin = true): Builder
    {
        return $query->where(function (Builder $identifiers) use ($source, $includeOrigin): void {
            AccountContacts::matching($identifiers, $source->only(['email', 'phone']));
            $username = strtolower(trim((string) $source->username));
            if ($username !== '') {
                $identifiers->orWhereRaw('LOWER(TRIM(username)) = ?', [$username]);
            }
            if ($includeOrigin) {
                $identifiers->orWhere('source_candidate_id', $source->id);
            }
        });
    }

    private function normalizeRoles(mixed $roles): array
    {
        if (is_string($roles)) {
            $decoded = json_decode($roles, true);
            $roles = is_array($decoded) ? $decoded : [$roles];
        }

        return collect(is_array($roles) ? $roles : [])
            ->filter(fn ($role): bool => is_scalar($role) && filled(trim((string) $role)))
            ->map(fn ($role): string => strtolower(trim((string) $role)))
            ->unique()->values()->all();
    }

    private function isCandidateOnly(array $roles, array $candidateRoles): bool
    {
        return $roles !== [] && array_diff($roles, $candidateRoles) === [];
    }

    private function categoryRoles(array $roles): array
    {
        $specific = array_values(array_diff($roles, ['candidate', 'student']));

        return $specific ?: ($roles ?: ['candidate']);
    }

    private function staffRoles(): array
    {
        $roles = StaffRoles::STAFF;
        if (Schema::hasColumn('roles', 'role_type_key')) {
            $roles = [...$roles, ...Role::query()->where('guard_name', 'web')
                ->where('role_type_key', 'staff')->pluck('name')->all()];
        }

        return $this->normalizeRoles($roles);
    }

    private function isProtectedLogin(User $user): bool
    {
        return strtolower(trim((string) $user->username)) === 'admin'
            || strtolower(trim((string) $user->email)) === 'admin@gmail.com';
    }

    private function ensure(bool $condition, SystemUser $source, string $reason): void
    {
        if (! $condition) {
            throw new DomainException("Legacy candidate #{$source->id} {$reason}.");
        }
    }
}
