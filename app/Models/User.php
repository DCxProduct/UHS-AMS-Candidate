<?php

namespace App\Models;

use App\Support\AccountContactUniqueness;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAvatar, HasName
{
    use HasRoles;
    use Notifiable;
    use SoftDeletes {
        forceDelete as private forceDeleteUserRecord;
    }

    protected $guard_name = 'web';

    protected $fillable = [
        'registration_type',
        'academic_year',
        'name',
        'first_name_kh',
        'last_name_kh',
        'name_latin',
        'username',
        'email',
        'email_verified_at',
        'phone',
        'date_of_birth',
        'seat_number',
        'avatar',
        'is_active',
        'password',
        'remember_token',
        'candidate_roles',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'candidate_roles',
        'source_candidate_id',
        'auth_id',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date',
            'is_active' => 'boolean',
            'password' => 'hashed',
            'candidate_roles' => 'array',
        ];
    }

    public function delete()
    {
        $this->ensureAccountCanBeDeleted();

        return parent::delete();
    }

    public function forceDelete()
    {
        $this->ensureAccountCanBeDeleted();

        return $this->forceDeleteUserRecord();
    }

    protected function ensureAccountCanBeDeleted(): void
    {
        // An object loaded before AMS promotion still carries the same auth ID.
        // Protect it before deletion events can detach that student's roles.
        if ($this->linkedNewStudentAccount() !== null
            || ($this->getKey() !== null && Schema::hasTable('account_identities')
                && AccountIdentity::query()->whereKey($this->getKey())->where('storage', 'student')->exists())) {
            throw ValidationException::withMessages([
                'account' => __('new_student_accounts.notifications.deletion_blocked'),
            ]);
        }
    }

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if (! $user instanceof AccountUser) {
                AccountContactUniqueness::assertForAuth($user);
            }
        });

        static::creating(function (User $user): void {
            if ($user instanceof StudentLogin || $user instanceof AccountUser
                || ! Schema::hasTable('account_identities')) {
                return;
            }

            if ($user->getKey() !== null) {
                $identity = AccountIdentity::query()->find($user->getKey());
                if ($identity !== null && $identity->storage !== 'candidate') {
                    throw ValidationException::withMessages(['id' => 'This account identity belongs to a student account.']);
                }
                AccountIdentity::query()->firstOrCreate(['id' => $user->getKey()], ['storage' => 'candidate']);

                return;
            }

            $identity = AccountIdentity::query()->create(['storage' => 'candidate']);
            $user->setAttribute($user->getKeyName(), $identity->getKey());
        });

        static::deleted(function (User $user): void {
            if (! $user->isForceDeleting() || ! Schema::hasTable('account_identities')) {
                return;
            }

            // Transfer uses a query-builder delete and deliberately keeps the
            // shared identity. Normal account deletion keeps its old FK behavior.
            DB::table('account_identities')->where('id', $user->getKey())
                ->where('storage', $user instanceof StudentLogin ? 'student' : 'candidate')->delete();
        });
    }

    public function save(array $options = [])
    {
        // Identity reservation and its credential row must commit together.
        return $this->getConnection()->transaction(fn () => parent::save($options));
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() !== 'app') {
            return false;
        }

        if (! $this->is_active) {
            return false;
        }

        return $this->roles()->exists()
            || $this->permissions()->exists()
            || $this->linkedSystemUser()?->isActiveAccount()
            || $this->effectiveRoleNames()->isNotEmpty()
            || in_array($this->registration_type, ['admin', 'student'], true);
    }

    public function hasEffectiveRole(array|string $roles): bool
    {
        $roles = collect(is_array($roles) ? $roles : [$roles])
            ->map(fn ($role): string => Str::lower(trim((string) $role)))
            ->all();

        return $this->effectiveRoleNames()
            ->intersect($roles)
            ->isNotEmpty();
    }

    public function effectiveRoleNames(): Collection
    {
        $loginRoles = $this->getRoleNames()
            ->map(fn (string $role): string => Str::lower(trim($role)));

        $systemRoles = collect($this->linkedSystemUser()?->roles ?? [])
            ->when(
                is_string($this->linkedSystemUser()?->roles),
                function (Collection $collection): Collection {
                    $rawRoles = $this->linkedSystemUser()?->roles;
                    $decoded = json_decode((string) $rawRoles, true);

                    return collect(is_array($decoded) ? $decoded : [$rawRoles]);
                }
            )
            ->filter(fn ($role): bool => filled($role))
            ->map(fn ($role): string => Str::lower(trim((string) $role)));

        $studentRoles = collect($this->linkedNewStudentAccount()?->roles ?? [])
            ->filter(fn ($role): bool => filled($role))
            ->map(fn ($role): string => Str::lower(trim((string) $role)));

        $candidateRoles = collect($this->candidate_roles ?? [])
            ->filter(fn ($role): bool => filled($role))
            ->map(fn ($role): string => Str::lower(trim((string) $role)));

        return $loginRoles
            ->merge($systemRoles)
            ->merge($studentRoles)
            ->merge($candidateRoles)
            ->unique()
            ->values();
    }

    public function getFilamentAvatarUrl(): ?string
    {
        $avatar = $this->normalizeAvatarPath($this->avatar);

        if (blank($avatar)) {
            return null;
        }

        if (Str::startsWith($avatar, ['http://', 'https://'])) {
            return $avatar;
        }

        if (! Storage::disk('public')->exists($avatar)) {
            return null;
        }

        return Storage::disk('public')->url($avatar);
    }

    public function getFilamentName(): string
    {
        $khmerName = collect([$this->first_name_kh, $this->last_name_kh])
            ->filter(fn ($part): bool => filled($part))
            ->implode(' ');

        if (filled($khmerName)) {
            return $khmerName;
        }

        $linkedSystemUser = $this->linkedSystemUser();
        $linkedKhmerName = collect([$linkedSystemUser?->first_name_kh, $linkedSystemUser?->last_name_kh])
            ->filter(fn ($part): bool => filled($part))
            ->implode(' ');

        return $linkedKhmerName ?: $this->name_latin ?: $this->name ?: $this->username ?: $this->email ?: 'User';
    }

    public function studentUser(): HasOne
    {
        return $this->hasOne(SystemUser::class);
    }

    public function linkedSystemUser(): ?SystemUser
    {
        $identifiers = array_filter([
            'username' => filled($this->username) ? $this->username : null,
            'email' => filled($this->email) ? $this->email : null,
            'phone' => filled($this->phone) ? $this->phone : null,
        ], fn ($value): bool => filled($value));

        if ($identifiers === []) {
            return null;
        }

        return SystemUser::query()
            ->where(function (Builder $query) use ($identifiers): void {
                foreach ($identifiers as $column => $value) {
                    $query->orWhere($column, $value);
                }
            })
            ->first();
    }

    public function linkedNewStudentAccount(): ?NewStudentAccount
    {
        if (! $this->exists) {
            return null;
        }

        if (Schema::hasColumn('new_student_accounts', 'auth_id')) {
            return NewStudentAccount::query()->where('auth_id', $this->getAuthIdentifier())->first();
        }
        if (! Schema::hasColumn('new_student_accounts', 'user_id')) {
            return null;
        }

        return NewStudentAccount::query()->where('user_id', $this->getKey())->first();
    }

    private function normalizeAvatarPath(mixed $avatar): ?string
    {
        if (blank($avatar)) {
            return null;
        }

        if (is_string($avatar) && Str::startsWith(trim($avatar), ['[', '{'])) {
            $decoded = json_decode($avatar, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $avatar = $decoded;
            }
        }

        if (is_array($avatar)) {
            $avatar = collect($avatar)
                ->flatten()
                ->filter()
                ->first();
        }

        $avatar = trim((string) $avatar);

        if ($avatar === '' || $avatar === 'Array') {
            return null;
        }

        if (Str::startsWith($avatar, ['http://', 'https://'])) {
            $path = parse_url($avatar, PHP_URL_PATH);

            if (! is_string($path) || ! Str::startsWith($path, ['/storage/', '/public/'])) {
                return $avatar;
            }

            $avatar = $path;
        }

        return Str::of($avatar)
            ->replaceStart('/storage/', '')
            ->replaceStart('storage/', '')
            ->replaceStart('/public/', '')
            ->replaceStart('public/', '')
            ->replaceStart('/', '')
            ->toString();
    }
}
