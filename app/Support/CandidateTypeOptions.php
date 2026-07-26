<?php

namespace App\Support;

use App\Models\CandidateType;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class CandidateTypeOptions
{
    public const BASE_ROLE = 'student';
    public const DEFAULT_KEY = 'candidate';

    public static function customQuery()
    {
        static::ensureDefaultCandidateType();

        return CandidateType::query()
            ->where('is_active', true)
            ->orderBy('display_order')
            ->orderBy('id');
    }

    public static function allQuery()
    {
        static::ensureDefaultCandidateType();

        return CandidateType::query()
            ->orderBy('display_order')
            ->orderBy('id');
    }

    public static function options(): array
    {
        if (Schema::hasTable('candidate_types')) {
            $options = static::customQuery()
                ->get()
                ->mapWithKeys(fn (CandidateType $candidateType): array => [
                    $candidateType->key => $candidateType->getLocalizedLabel(),
                ])
                ->all();

            if ($options !== []) {
                return $options;
            }
        }

        return static::defaultOption();
    }

    public static function colors(): array
    {
        if (Schema::hasTable('candidate_types')) {
            $colors = static::customQuery()
                ->get()
                ->mapWithKeys(fn (CandidateType $candidateType): array => [
                    $candidateType->key => static::normalizeColor($candidateType->color),
                ])
                ->all();

            if ($colors !== []) {
                return $colors;
            }
        }

        return [
            self::BASE_ROLE => 'primary',
        ];
    }

    public static function resolve(?string $roleName): string
    {
        $options = static::options();

        if (is_string($roleName) && array_key_exists($roleName, $options)) {
            return $roleName;
        }

        return array_key_first($options) ?? self::BASE_ROLE;
    }

    public static function formatLabel(string $roleName): string
    {
        if (strcasecmp($roleName, self::BASE_ROLE) === 0) {
            return __('app.candidate');
        }

        if ($candidateType = static::findByKey($roleName)) {
            return $candidateType->getLocalizedLabel();
        }

        return (string) Str::of($roleName)
            ->replace(['_', '-'], ' ')
            ->title();
    }

    public static function formatPreviewLabel(string $roleName): string
    {
        if (strcasecmp($roleName, self::BASE_ROLE) === 0) {
            return __('app.candidate');
        }

        if ($candidateType = static::findByKey($roleName)) {
            return $candidateType->getLocalizedLabel();
        }

        return static::formatLabel($roleName);
    }

    public static function findByKey(?string $key): ?CandidateType
    {
        if (blank($key) || ! Schema::hasTable('candidate_types')) {
            return null;
        }

        static::ensureDefaultCandidateType();

        return CandidateType::query()
            ->where('key', $key)
            ->first();
    }

    public static function assignableWebRoles(string $selectedRole): array
    {
        $availableRoles = Role::query()
            ->where('guard_name', 'web')
            ->pluck('name')
            ->all();

        return collect([
            self::BASE_ROLE,
            $selectedRole !== self::BASE_ROLE ? $selectedRole : null,
        ])
            ->filter(fn (?string $role): bool => filled($role))
            ->filter(fn (string $role): bool => in_array($role, $availableRoles, true))
            ->unique(fn (string $role): string => Str::lower($role))
            ->values()
            ->all();
    }

    public static function assignableSystemRoles(string $selectedRole): array
    {
        return collect([
            'Student',
            $selectedRole !== self::BASE_ROLE ? $selectedRole : null,
        ])
            ->filter(fn (?string $role): bool => filled($role))
            ->unique(fn (string $role): string => Str::lower($role))
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
            self::BASE_ROLE => __('app.candidate'),
        ];
    }

    protected static function ensureDefaultCandidateType(): void
    {
        if (! Schema::hasTable('candidate_types')) {
            return;
        }

        CandidateType::query()->firstOrCreate(
            ['key' => self::DEFAULT_KEY],
            [
                'label_en' => 'Candidate',
                'label_kh' => 'បេក្ខជន',
                'color' => 'blue',
                'display_order' => 0,
                'is_active' => true,
            ],
        );
    }
}
