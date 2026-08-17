<?php

namespace App\Models;

use App\Support\UserTypeOptions;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SystemUser extends Authenticatable implements FilamentUser, HasAvatar, HasName, CanResetPasswordContract
{
    use Notifiable;
    use SoftDeletes;
    use CanResetPassword;

    protected $table = 'system_users';

    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'password',
        'avatar',
        'roles',
        'permissions',
        'is_active',
        'email_verified_at',
        'last_login_at',
        'remember_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'roles' => 'array',
            'permissions' => 'array',
            'is_active' => 'boolean',
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (SystemUser $systemUser): void {
            $systemUser->deleteLinkedLoginUsers($systemUser->isForceDeleting());
        });
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class, 'system_user_id');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() !== 'app') {
            return false;
        }

        if (! $this->isActiveAccount()) {
            return false;
        }

        return $this->hasJsonRole([
            'Developer',
            'Admin',
            'Finance',
            'Cashier',
            'Registrar',
            'Team UHS',
            'Processing',
            ...UserTypeOptions::candidateManagedRoleKeys(),
        ]);
    }

    public function hasJsonRole(array | string $roles): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];

        $userRoles = $this->roles;

        if (is_string($userRoles)) {
            $decoded = json_decode($userRoles, true);
            $userRoles = is_array($decoded) ? $decoded : [$userRoles];
        }

        if (! is_array($userRoles)) {
            $userRoles = [];
        }

        return collect($userRoles)
            ->map(fn ($role): string => strtolower(trim((string) $role)))
            ->intersect(
                collect($roles)->map(fn ($role): string => strtolower(trim((string) $role)))
            )
            ->isNotEmpty();
    }

    public function activateAccount(): void
    {
        $this->forceFill([
            'is_active' => true,
        ])->save();

        $this->syncLoginUser();
    }

    public function deactivateAccount(): void
    {
        $this->forceFill([
            'is_active' => false,
        ])->save();

        $this->syncLoginUser();
    }

    public function isActiveAccount(): bool
    {
        return (bool) $this->is_active;
    }

    public function syncLoginUser(): void
    {
        $lookup = $this->getLoginUserLookup();

        if (empty($lookup)) {
            return;
        }

        $loginUser = $this->linkedLoginUsersQuery(withTrashed: true)->first();

        if (! $loginUser) {
            $loginUser = new User();
        } elseif (method_exists($loginUser, 'trashed') && $loginUser->trashed()) {
            $loginUser->restore();
        }

        $loginUser->fill([
            'registration_type' => $this->getLoginRegistrationType(),
            'academic_year' => $loginUser->academic_year,
            'name' => $this->name ?: $this->username ?: $this->email ?: 'System User',
            'name_latin' => $loginUser->name_latin,
            'username' => $this->username,
            'email' => $this->email,
            'phone' => $this->phone,
            'date_of_birth' => $loginUser->date_of_birth ?? '2000-01-01',
            'seat_number' => $loginUser->seat_number,
            'avatar' => $this->avatar,
            'password' => $this->password,
            'email_verified_at' => $this->email_verified_at ?? now(),
            'is_active' => (bool) $this->is_active,
        ]);

        $loginUser->save();

        $this->syncLoginUserAuthorizations($loginUser);
    }

    public function deleteWithLinkedLoginUsers(bool $forceDelete = true): void
    {
        DB::transaction(function () use ($forceDelete): void {
            $forceDelete ? $this->forceDelete() : $this->delete();
        });
    }

    protected function getLoginUserLookup(): array
    {
        if (filled($this->username)) {
            return [
                'username' => $this->username,
            ];
        }

        if (filled($this->email)) {
            return [
                'email' => $this->email,
            ];
        }

        if (filled($this->phone)) {
            return [
                'phone' => $this->phone,
            ];
        }

        return [];
    }

    protected function getLoginRegistrationType(): string
    {
        $staffRoles = ['admin', 'cashier', 'finance', 'developer', 'registrar', 'processing', 'team uhs'];

        if ($this->hasJsonRole($staffRoles)) {
            return 'admin';
        }

        return $this->hasJsonRole(UserTypeOptions::candidateManagedRoleKeys()) ? 'student' : 'admin';
    }

    protected function syncLoginUserAuthorizations(User $loginUser): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roleNames = collect($this->normalizeStringArray($this->roles))
            ->flatMap(fn (string $role): array => UserTypeOptions::assignableWebRoles($role))
            ->filter()
            ->values()
            ->all();

        $allPermissions = Permission::query()
            ->where('guard_name', 'web')
            ->get();

        $permissionNames = collect($this->normalizeStringArray($this->permissions))
            ->map(fn (string $permission): ?string => $allPermissions
                ->first(fn (Permission $candidate): bool => strcasecmp($candidate->name, $permission) === 0)
                ?->name
            )
            ->filter()
            ->values()
            ->all();

        $loginUser->syncRoles($roleNames);
        $loginUser->syncPermissions($permissionNames);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function normalizeStringArray(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [$value];
        }

        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn ($item): bool => filled($item))
            ->map(fn ($item): string => trim((string) $item))
            ->values()
            ->all();
    }

    public function findLinkedLoginUser(): ?User
    {
        return $this->linkedLoginUsersQuery()
            ->first();
    }

    protected function deleteLinkedLoginUsers(bool $forceDelete): void
    {
        $this->linkedLoginUsersQuery(withTrashed: $forceDelete)
            ->get()
            ->each(function (User $loginUser) use ($forceDelete): void {
                $forceDelete ? $loginUser->forceDelete() : $loginUser->delete();
            });
    }

    protected function linkedLoginUsersQuery(bool $withTrashed = false): Builder
    {
        $identifiers = array_filter([
            'username' => filled($this->username) ? $this->username : null,
            'email' => filled($this->email) ? $this->email : null,
            'phone' => filled($this->phone) ? $this->phone : null,
        ], fn ($value): bool => filled($value));

        $query = $withTrashed ? User::withTrashed() : User::query();

        if ($identifiers === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $query) use ($identifiers): void {
            foreach ($identifiers as $column => $value) {
                $query->orWhere($column, $value);
            }
        });
    }

    public static function syncStaffLoginUsers(): void
    {
        User::query()
            ->where('registration_type', 'admin')
            ->when(
                method_exists(User::class, 'getDeletedAtColumn'),
                fn ($query) => $query->whereNull((new User)->getQualifiedDeletedAtColumn())
            )
            ->get()
            ->each(function (User $loginUser): void {
                $lookup = static::loginUserLookup($loginUser);

                if ($lookup === []) {
                    return;
                }

                $roles = $loginUser->getRoleNames()
                    ->filter(fn (string $role): bool => filled($role))
                    ->values()
                    ->all();

                if ($roles === []) {
                    $roles = ['admin'];
                }

                $permissions = $loginUser->getDirectPermissions()
                    ->pluck('name')
                    ->filter(fn (string $permission): bool => filled($permission))
                    ->values()
                    ->all();

                $systemUser = static::withTrashed()->firstOrNew($lookup);

                $systemUser->fill([
                    'name' => $loginUser->name ?: $loginUser->username ?: $loginUser->email ?: 'System User',
                    'username' => $loginUser->username,
                    'email' => $loginUser->email,
                    'phone' => $loginUser->phone,
                    'password' => $loginUser->password,
                    'avatar' => $loginUser->avatar,
                    'roles' => $roles,
                    'permissions' => $permissions,
                    'is_active' => (bool) $loginUser->is_active,
                    'email_verified_at' => $loginUser->email_verified_at ?? now(),
                ]);

                if ($systemUser->trashed()) {
                    $systemUser->restore();
                }

                $systemUser->save();
            });
    }

    protected static function loginUserLookup(User $loginUser): array
    {
        if (filled($loginUser->username)) {
            return ['username' => $loginUser->username];
        }

        if (filled($loginUser->email)) {
            return ['email' => $loginUser->email];
        }

        if (filled($loginUser->phone)) {
            return ['phone' => $loginUser->phone];
        }

        return [];
    }

    public function getFilamentName(): string
    {
        return $this->name
            ?: $this->username
                ?: $this->email
                    ?: 'System User';
    }

    public function getFilamentAvatarUrl(): ?string
    {
        $avatar = $this->avatar;

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
            return $avatar;
        }

        $avatar = Str::of($avatar)
            ->replaceStart('/storage/', '')
            ->replaceStart('storage/', '')
            ->replaceStart('/public/', '')
            ->replaceStart('public/', '')
            ->replaceStart('/', '')
            ->toString();

        if (! Storage::disk('public')->exists($avatar)) {
            return null;
        }

        return Storage::disk('public')->url($avatar);
    }
}
