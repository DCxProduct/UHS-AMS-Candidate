<?php

namespace App\Support;

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
        $roles = static::availableRoleNames();

        if ($roles !== []) {
            return collect($roles)
                ->mapWithKeys(fn (string $role): array => [$role => static::formatLabel($role)])
                ->all();
        }

        return static::defaultOption();
    }

    public static function systemOptions(): array
    {
        $roles = static::availableSystemRoleNames();

        if ($roles !== []) {
            return collect($roles)
                ->mapWithKeys(fn (string $role): array => [$role => static::formatLabel($role)])
                ->all();
        }

        return [
            'admin' => __('system_users.roles.admin'),
            'cashier' => __('system_users.roles.cashier'),
        ];
    }

    public static function colors(): array
    {
        return collect(static::options())
            ->mapWithKeys(fn (string $_label, string $role): array => [
                $role => match (Str::lower($role)) {
                    'admin' => 'danger',
                    'cashier' => 'success',
                    'associate' => 'warning',
                    default => 'primary',
                },
            ])
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

        $selectedRole = static::resolve($selectedRole);
        $normalized = Str::lower(trim($selectedRole));
        $selectedStoredRole = in_array($normalized, ['student', 'candidate'], true)
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

    public static function assignableSystemRoles(string $selectedRole): array
    {
        $availableRoles = Role::query()
            ->where('guard_name', 'web')
            ->pluck('name')
            ->all();

        $selectedRole = static::resolve($selectedRole);
        $normalized = Str::lower(trim($selectedRole));
        $selectedStoredRole = in_array($normalized, ['student', 'candidate'], true)
            ? (static::findRoleCaseInsensitive($availableRoles, 'candidate')
                ?? static::findRoleCaseInsensitive($availableRoles, 'student')
                ?? self::BASE_ROLE)
            : static::findRoleCaseInsensitive($availableRoles, $selectedRole);

        return collect([
            $selectedStoredRole,
        ])
            ->filter(fn (?string $role): bool => filled($role))
            ->unique(fn (string $role): string => Str::lower($role))
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
            'admin' => __('system_users.roles.admin'),
            'cashier' => __('system_users.roles.cashier'),
            'associate' => app()->getLocale() === 'km' ? 'បរិញ្ញាបត្ររង' : 'Associate',
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
        $roles = collect(static::availableRoleNames());
        $userTypeKeys = Schema::hasTable('user_types')
            ? UserType::query()->pluck('key')->map(fn (string $key): string => Str::lower(trim($key)))->all()
            : [];

        return $roles
            ->reject(function (string $role) use ($userTypeKeys): bool {
                $normalized = Str::lower(trim($role));

                if (in_array($normalized, ['candidate', 'student'], true)) {
                    return true;
                }

                return in_array($normalized, $userTypeKeys, true);
            })
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
