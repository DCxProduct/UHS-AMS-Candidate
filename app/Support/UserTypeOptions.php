<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use App\Models\UserType;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class UserTypeOptions
{
    public const BASE_ROLE = 'candidate';
    public const DEFAULT_KEY = 'candidate';
    private const PREFERRED_ROLE_ORDER = [
        'admin',
        'cashier',
        'associate',
        'candidate',
        'student',
    ];

    public static function customQuery()
    {
        static::ensureDefaultUserType();

        return UserType::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('id');
    }

    public static function allQuery()
    {
        static::ensureDefaultUserType();

        return UserType::query()
            ->orderBy('display_order')
            ->orderBy('id');
    }

    public static function options(): array
    {
        $options = static::customQuery()
            ->get()
            ->mapWithKeys(fn (UserType $userType): array => [
                $userType->key => $userType->getLocalizedLabel(),
            ])
            ->all();

        if ($options !== []) {
            return $options;
        }

        return static::defaultOption();
    }

    public static function colors(): array
    {
        $colors = static::customQuery()
            ->get()
            ->mapWithKeys(fn (UserType $userType): array => [
                $userType->key => static::normalizeColor($userType->color),
            ])
            ->all();

        if ($colors !== []) {
            return $colors;
        }

        return collect(static::defaultOption())
            ->mapWithKeys(fn (string $_label, string $role): array => [
                $role => match (Str::lower($role)) {
                    'associate' => 'warning',
                    default => 'primary',
                },
            ])
                ->all();
    }

    public static function systemOptions(): array
    {
        $roles = static::availableSystemRoleNames();

        return collect($roles)
            ->mapWithKeys(fn (string $role): array => [$role => static::formatLabel($role)])
            ->all();
    }

    public static function resolve(?string $roleName): string
    {
        $options = static::options();

        if (is_string($roleName) && array_key_exists($roleName, $options)) {
            return $roleName;
        }

        if (is_string($roleName)) {
            $matched = collect(array_keys($options))
                ->first(fn (string $option): bool => strcasecmp($option, $roleName) === 0);

            if ($matched) {
                return $matched;
            }
        }

        return array_key_first($options) ?? self::BASE_ROLE;
    }

    public static function resolveSystemRole(?string $roleName): string
    {
        $options = static::systemOptions();

        if (is_string($roleName) && array_key_exists($roleName, $options)) {
            return $roleName;
        }

        if (is_string($roleName)) {
            $matched = collect(array_keys($options))
                ->first(fn (string $option): bool => strcasecmp($option, $roleName) === 0);

            if ($matched) {
                return $matched;
            }
        }

        return array_key_first($options) ?? 'admin';
    }

    public static function formatLabel(string $roleName): string
    {
        $normalized = Str::lower(trim($roleName));

        if ($userType = static::findByNormalizedKey($normalized)) {
            return $userType->getLocalizedLabel();
        }

        if ($normalized === 'admin') {
            return __('system_users.roles.admin');
        }

        if ($normalized === 'cashier') {
            return __('system_users.roles.cashier');
        }

        if ($normalized === 'associate') {
            return app()->getLocale() === 'km' ? 'បរិញ្ញាបត្ររង' : 'Associate';
        }

        return (string) Str::of($roleName)
            ->replace(['_', '-'], ' ')
            ->title();
    }

    public static function formatPreviewLabel(string $roleName): string
    {
        return static::formatLabel($roleName);
    }

    public static function findByKey(?string $key): ?UserType
    {
        if (blank($key) || ! Schema::hasTable('user_types')) {
            return null;
        }

        static::ensureDefaultUserType();

        return UserType::query()
            ->where('key', $key)
            ->first();
    }

    public static function assignableWebRoles(string $selectedRole): array
    {
        $availableRoles = Role::query()
            ->where('guard_name', 'web')
            ->pluck('name')
            ->all();

        $normalized = Str::lower(trim($selectedRole));

        if (in_array($normalized, ['student', 'candidate'], true) || static::findByNormalizedKey($normalized)) {
            $selectedRole = static::resolve($selectedRole);
            $normalized = Str::lower(trim($selectedRole));
        } else {
            $selectedRole = static::resolveSystemRole($selectedRole);
            $normalized = Str::lower(trim($selectedRole));
        }

        $selectedStoredRole = in_array($normalized, ['student', 'candidate'], true) || static::findByNormalizedKey($normalized)
            ? (static::findRoleCaseInsensitive($availableRoles, 'candidate')
                ?? static::findRoleCaseInsensitive($availableRoles, 'student')
                ?? self::BASE_ROLE)
            : static::findRoleCaseInsensitive($availableRoles, $selectedRole);

        return collect([
            $selectedStoredRole,
        ])
            ->filter(fn (?string $role): bool => filled($role))
            ->filter(fn (string $role): bool => in_array($role, $availableRoles, true))
            ->unique(fn (string $role): string => Str::lower($role))
            ->values()
            ->all();
    }

    public static function assignableUserRoles(string $selectedRole): array
    {
        $selectedRole = static::resolve($selectedRole);

        return collect([$selectedRole])
            ->filter(fn (?string $role): bool => filled($role))
            ->unique(fn (string $role): string => Str::lower($role))
            ->values()
            ->all();
    }

    public static function assignableSystemRoles(string $selectedRole): array
    {
        $availableRoles = static::availableSystemRoleNames();
        $selectedRole = static::resolveSystemRole($selectedRole);
        $normalized = Str::lower(trim($selectedRole));
        $selectedStoredRole = in_array($normalized, ['student', 'candidate'], true)
            ? (static::findRoleCaseInsensitive($availableRoles, 'candidate')
                ?? static::findRoleCaseInsensitive($availableRoles, 'student')
                ?? self::BASE_ROLE)
            : (static::findByNormalizedKey($normalized)?->key
                ?? static::findRoleCaseInsensitive($availableRoles, $selectedRole));

        return collect([
            $selectedStoredRole,
        ])
            ->filter(fn (?string $role): bool => filled($role))
            ->unique(fn (string $role): string => Str::lower($role))
            ->values()
            ->all();
    }

    public static function isCandidateManagedRole(?string $role): bool
    {
        if (! is_string($role) || trim($role) === '') {
            return false;
        }

        return in_array(Str::lower(trim($role)), static::candidateManagedRoleKeys(), true);
    }

    public static function userHasCandidateBasePermission(?Authenticatable $user, string $permission): bool
    {
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'can') && $user->can($permission)) {
            return true;
        }

        if (($user->registration_type ?? null) !== 'student') {
            return false;
        }

        $candidateRole = Role::query()
            ->where('guard_name', 'web')
            ->whereRaw('LOWER(name) = ?', [self::BASE_ROLE])
            ->first();

        return $candidateRole?->hasPermissionTo($permission) ?? false;
    }

    public static function candidateManagedRoleKeys(): array
    {
        return static::customQuery()
            ->pluck('key')
            ->map(fn (string $key): string => Str::lower(trim($key)))
            ->push('candidate', 'student')
            ->unique()
            ->values()
            ->all();
    }

    public static function colorOptions(): array
    {
        return [
            'blue' => __('user_types.colors.blue'),
            'green' => __('user_types.colors.green'),
            'orange' => __('user_types.colors.orange'),
            'red' => __('user_types.colors.red'),
            'black' => __('user_types.colors.black'),
        ];
    }

    public static function canonicalColor(?string $color): string
    {
        return match ((string) $color) {
            'primary', 'blue' => 'blue',
            'success', 'green' => 'green',
            'warning', 'orange' => 'orange',
            'danger', 'red' => 'red',
            'gray', 'black' => 'black',
            default => 'blue',
        };
    }

    public static function normalizeColor(?string $color): string
    {
        return match (static::canonicalColor($color)) {
            'blue' => 'primary',
            'green' => 'success',
            'orange' => 'warning',
            'red' => 'danger',
            'black' => 'gray',
            default => 'primary',
        };
    }

    protected static function defaultOption(): array
    {
        return [
            self::DEFAULT_KEY => __('app.candidate'),
        ];
    }

    protected static function availableRoleNames(): array
    {
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->pluck('name')
            ->all();

        if ($roles === []) {
            return [];
        }

        $byNormalized = collect($roles)
            ->mapWithKeys(fn (string $role): array => [Str::lower(trim($role)) => $role]);

        $ordered = [];

        foreach (self::PREFERRED_ROLE_ORDER as $normalizedRole) {
            if ($normalizedRole === 'student' && $byNormalized->has('candidate')) {
                continue;
            }

            if ($byNormalized->has($normalizedRole)) {
                $ordered[] = $byNormalized->get($normalizedRole);
            }
        }

        $remaining = collect($roles)
            ->reject(function (string $role) use ($ordered, $byNormalized): bool {
                $normalized = Str::lower(trim($role));

                if ($normalized === 'student' && $byNormalized->has('candidate')) {
                    return true;
                }

                return collect($ordered)
                    ->contains(fn (string $orderedRole): bool => strcasecmp($orderedRole, $role) === 0);
            })
            ->sortBy(fn (string $role): string => Str::lower(trim($role)))
            ->values()
            ->all();

        return collect($ordered)
            ->merge($remaining)
            ->unique(fn (string $role): string => Str::lower(trim($role)))
            ->values()
            ->all();
    }

    protected static function availableSystemRoleNames(): array
    {
        $candidateManagedRoles = static::candidateManagedRoleKeys();

        return collect(Role::query()
            ->where('guard_name', 'web')
            ->when(
                $candidateManagedRoles !== [],
                fn ($query) => $query->whereNotIn('name', $candidateManagedRoles),
            )
            ->pluck('name')
            ->all())
            ->map(fn (string $role): string => trim($role))
            ->unique(fn (string $role): string => Str::lower($role))
            ->sortBy(fn (string $role): string => Str::lower($role))
            ->values()
            ->all();
    }

    protected static function findRoleCaseInsensitive(array $availableRoles, string $needle): ?string
    {
        return collect($availableRoles)
            ->first(fn (string $role): bool => strcasecmp($role, $needle) === 0);
    }

    protected static function findByNormalizedKey(string $normalizedKey): ?UserType
    {
        if ($normalizedKey === '' || ! Schema::hasTable('user_types')) {
            return null;
        }

        static::ensureDefaultUserType();

        return UserType::query()
            ->whereRaw('LOWER(key) = ?', [$normalizedKey])
            ->first();
    }

    protected static function ensureDefaultUserType(): void
    {
        if (! Schema::hasTable('user_types')) {
            return;
        }

        UserType::query()->updateOrCreate(
            ['key' => 'master'],
            [
                'label_en' => 'Master',
                'label_kh' => 'អនុបណ្ឌិត',
                'color' => 'blue',
                'display_order' => 1,
                'is_active' => true,
            ],
        );
    }
}
