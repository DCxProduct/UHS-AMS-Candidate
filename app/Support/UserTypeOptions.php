<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use App\Models\DegreeLevel;
use App\Models\Role;
use App\Models\SystemUser;
use App\Models\UserType;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->get(['name', 'label_en', 'name_kh']);

        $hiddenUserTypeRoles = UserType::query()
            ->pluck('key')
            ->filter(fn (string $key): bool => strcasecmp($key, self::BASE_ROLE) !== 0)
            ->map(fn (string $key): string => Str::lower(trim($key)))
            ->values()
            ->all();

        $roleLabels = $roles
            ->mapWithKeys(function (Role $role): array {
                $name = trim((string) $role->name);

                if ($name === '') {
                    return [];
                }

                $label = trim((string) ($role->localized_name ?: $role->name));

                return [$name => ($label !== '' ? $label : $name)];
            });

        return collect(static::availableSystemRoleNames())
            ->reject(function (string $role) use ($hiddenUserTypeRoles): bool {
                $normalized = Str::lower(trim($role));

                if ($normalized === Str::lower(self::BASE_ROLE)) {
                    return true;
                }

                return in_array($normalized, $hiddenUserTypeRoles, true);
            })
            ->mapWithKeys(fn (string $role): array => [
                $role => $roleLabels[$role] ?? static::formatLabel($role),
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

        if (is_string($roleName) && trim($roleName) !== '') {
            return trim($roleName);
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
            $selectedStoredRole ?? trim($selectedRole),
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
            'blue' => __('candidate_types.colors.blue'),
            'green' => __('candidate_types.colors.green'),
            'orange' => __('candidate_types.colors.orange'),
            'red' => __('candidate_types.colors.red'),
            'black' => __('candidate_types.colors.black'),
        ];
    }

    public static function groupOptions(): array
    {
        return DegreeLevel::options();
    }

    public static function normalizeGroupName(mixed $groupName): ?string
    {
        $groupName = trim((string) $groupName);

        if ($groupName === '') {
            return null;
        }

        return Str::of($groupName)
            ->lower()
            ->replaceMatches('/[^a-z0-9_-]+/', '_')
            ->replaceMatches('/_+/', '_')
            ->trim('_')
            ->toString() ?: null;
    }

    public static function formatGroupLabel(?string $groupName): string
    {
        $normalizedGroup = static::normalizeGroupName($groupName);

        if ($normalizedGroup === null) {
            return '';
        }

        if ($degreeLevel = DegreeLevel::findByKey($normalizedGroup)) {
            return $degreeLevel->localized_label;
        }

        return (string) Str::of($normalizedGroup)
            ->replace(['_', '-'], ' ')
            ->title();
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
        $hiddenUserTypeRoles = UserType::query()
            ->pluck('key')
            ->filter(fn (string $key): bool => strcasecmp($key, self::BASE_ROLE) !== 0)
            ->values()
            ->all();

        return collect(Role::query()
            ->where('guard_name', 'web')
            ->when(
                $hiddenUserTypeRoles !== [],
                fn ($query) => $query->whereNotIn('name', $hiddenUserTypeRoles),
            )
            ->pluck('name')
            ->all())
            ->merge(static::storedSystemRoleNames())
            ->map(fn (string $role): string => trim($role))
            ->reject(fn (string $role): bool => strcasecmp($role, self::BASE_ROLE) === 0)
            ->unique(fn (string $role): string => Str::lower($role))
            ->sortBy(fn (string $role): string => Str::lower($role))
            ->values()
            ->all();
    }

    protected static function storedSystemRoleNames(): array
    {
        if (! Schema::hasTable('system_users')) {
            return [];
        }

        return SystemUser::query()
            ->pluck('roles')
            ->flatMap(function (mixed $roles): array {
                if (is_string($roles)) {
                    $decoded = json_decode($roles, true);
                    $roles = is_array($decoded) ? $decoded : [$roles];
                }

                if (! is_array($roles)) {
                    return [];
                }

                return $roles;
            })
            ->filter(fn ($role): bool => filled($role))
            ->map(fn ($role): string => trim((string) $role))
            ->reject(fn (string $role): bool => static::isCandidateManagedRole($role))
            ->unique(fn (string $role): string => Str::lower($role))
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

        foreach (static::defaultRecords() as $record) {
            UserType::query()->updateOrCreate(
                ['key' => $record['key']],
                $record,
            );
        }
    }

    public static function defaultRecords(): array
    {
        return [
            [
                'key' => 'national_entrance_exam_application_bachelor',
                'label_en' => 'National Entrance Exam Application-Bachelor',
                'label_kh' => 'ប្រឡងចូលថ្នាក់ជាតិ-បរិញ្ញាបត្រ',
                'group_name' => 'bachelor',
                'color' => 'blue',
                'display_order' => 1,
                'is_active' => true,
            ],
            [
                'key' => 'national_entrance_exam_application_associate',
                'label_en' => 'National Entrance Exam Application-Associate',
                'label_kh' => 'ប្រឡងចូលថ្នាក់ជាតិ-បរិញ្ញាបត្ររង',
                'group_name' => 'associate',
                'color' => 'green',
                'display_order' => 2,
                'is_active' => true,
            ],
            [
                'key' => 'national_entrance_exam_application_dental_surgeon',
                'label_en' => 'National Entrance Exam Application-Dental Surgeon',
                'label_kh' => 'ប្រឡងចូលថ្នាក់ជាតិ-ទន្តបណ្ឌិត',
                'group_name' => 'dental_surgeon',
                'color' => 'orange',
                'display_order' => 3,
                'is_active' => true,
            ],
            [
                'key' => 'national_entrance_exam_application_doctor_of_medicine',
                'label_en' => 'National Entrance Exam Application-Doctor of Medicine',
                'label_kh' => 'ប្រឡងចូលថ្នាក់ជាតិ-វេជ្ជបណ្ឌិត',
                'group_name' => 'doctor_of_medicine',
                'color' => 'red',
                'display_order' => 4,
                'is_active' => true,
            ],
            [
                'key' => 'national_exit_exam_application_bachelor',
                'label_en' => 'National Exit Exam Application-Bachelor',
                'label_kh' => 'ប្រឡងចេញថ្នាក់ជាតិ-បរិញ្ញាបត្រ',
                'group_name' => 'bachelor',
                'color' => 'blue',
                'display_order' => 5,
                'is_active' => true,
            ],
            [
                'key' => 'national_exit_exam_application_associate',
                'label_en' => 'National Exit Exam Application-Associate',
                'label_kh' => 'ប្រឡងចេញថ្នាក់ជាតិ-បរិញ្ញាបត្ររង',
                'group_name' => 'associate',
                'color' => 'green',
                'display_order' => 6,
                'is_active' => true,
            ],
            [
                'key' => 'national_exit_exam_application_master_of_science',
                'label_en' => 'National Exit Exam Application-Master\'s Degree',
                'label_kh' => 'ប្រឡងចេញថ្នាក់ជាតិ-បរិញ្ញាបត្រជាន់ខ្ពស់',
                'group_name' => 'master_of_science',
                'color' => 'orange',
                'display_order' => 7,
                'is_active' => true,
            ],
            [
                'key' => 'national_exit_exam_application_dental_surgeon',
                'label_en' => 'National Exit Exam Application-Dental Surgeon',
                'label_kh' => 'ប្រឡងចេញថ្នាក់ជាតិ-ទន្តបណ្ឌិត',
                'group_name' => 'dental_surgeon',
                'color' => 'green',
                'display_order' => 8,
                'is_active' => true,
            ],
            [
                'key' => 'national_exit_exam_application_doctor_of_medicine',
                'label_en' => 'National Exit Exam Application-Doctor of Medicine',
                'label_kh' => 'ប្រឡងចេញថ្នាក់ជាតិ-វេជ្ជបណ្ឌិត',
                'group_name' => 'doctor_of_medicine',
                'color' => 'red',
                'display_order' => 9,
                'is_active' => true,
            ],
            [
                'key' => 'national_exit_exam_application_medical_specialty',
                'label_en' => 'National Exit Exam Application-Medical Specialty',
                'label_kh' => 'ប្រឡងចេញថ្នាក់ជាតិ-វេជ្ជបណ្ឌិតឯកទេស',
                'group_name' => 'medical_specialty',
                'color' => 'red',
                'display_order' => 10,
                'is_active' => true,
            ],
            [
                'key' => 'continuing_bachelors_degree',
                'label_en' => 'Continuing Bachelor\'s Degree',
                'label_kh' => 'ជ្រើសរើសបន្ត-បរិញ្ញាបត្រ',
                'group_name' => 'bachelor',
                'color' => 'blue',
                'display_order' => 11,
                'is_active' => true,
            ],
            [
                'key' => 'continuing_master_of_science',
                'label_en' => 'Continuing Master\'s Degree',
                'label_kh' => 'ជ្រើសរើសបន្ត-បរិញ្ញាបត្រជាន់ខ្ពស់',
                'group_name' => 'master_of_science',
                'color' => 'orange',
                'display_order' => 12,
                'is_active' => true,
            ],
            [
                'key' => 'continuing_medical_specialty',
                'label_en' => 'Continuing Medical Specialty',
                'label_kh' => 'ជ្រើសរើសបន្ត-វេជ្ជបណ្ឌិតឯកទេស',
                'group_name' => 'medical_specialty',
                'color' => 'red',
                'display_order' => 13,
                'is_active' => true,
            ],
        ];
    }
}
