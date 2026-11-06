<?php

namespace App\Support;

use App\Models\User;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CandidateTypeResolver
{
    public static function forEntry(CustomFormEntry $entry): ?string
    {
        $data = is_array($entry->data) ? $entry->data : [];
        if (array_key_exists(CandidateApplicationContext::DATA_KEY, $data)) {
            $saved = $data[CandidateApplicationContext::DATA_KEY];

            return is_string($saved) && trim($saved) !== '' ? strtolower(trim($saved)) : null;
        }

        $entry->loadMissing(['customForm', 'creator']);
        $creator = $entry->creator instanceof User ? $entry->creator : null;

        if ($entry->customForm && ($type = CandidateApplicationContext::candidateType($entry->customForm, $creator)) !== null) {
            return $type;
        }

        return self::resolve($creator, $data);
    }

    public static function filterEntries(Builder $query, string $candidateType): Builder
    {
        $candidateType = self::normalize($candidateType) ?? strtolower(trim($candidateType));
        $column = $query->getModel()->qualifyColumn('data').'->'.CandidateApplicationContext::DATA_KEY;
        $legacyIds = (clone $query)->reorder()
            ->where(fn (Builder $legacy): Builder => $legacy->whereNull($column)->orWhere($column, ''))
            ->with(['customForm', 'creator.roles'])
            ->lazyById(250, $query->getModel()->getQualifiedKeyName(), $query->getModel()->getKeyName())
            ->filter(fn (CustomFormEntry $entry): bool => self::forEntry($entry) === $candidateType)
            ->map(fn (CustomFormEntry $entry): mixed => $entry->getKey())
            ->all();

        return $query->where(fn (Builder $matches): Builder => $matches->where($column, $candidateType)->orWhereKey($legacyIds));
    }

    public static function resolve(?User $user = null, array|object|null $data = null): ?string
    {
        if ($user) {
            if (($current = CandidateApplicationLevels::currentType($user)) !== null) {
                return $current;
            }

            $role = collect($user->effectiveRoleNames())
                ->map(fn ($value): ?string => static::candidateRoleKey($value))
                ->filter(fn (?string $value): bool => filled($value) && ! in_array($value, ['candidate', 'student'], true))
                ->first();

            if (filled($role)) {
                return $role;
            }
        }

        foreach (['user_type', 'candidate_type', 'student_role'] as $key) {
            $role = static::candidateRoleKey(data_get($data, $key));

            if (filled($role)) {
                return $role;
            }
        }

        if ($user) {
            $baseRole = collect($user->effectiveRoleNames())
                ->map(fn ($value): ?string => static::candidateRoleKey($value))
                ->first(fn (?string $value): bool => in_array($value, ['candidate', 'student'], true));

            if (filled($baseRole)) {
                return $baseRole;
            }
        }

        return null;
    }

    public static function label(?string $role): string
    {
        if (blank($role)) {
            return '-';
        }

        $key = static::candidateRoleKey($role);

        return filled($key)
            ? UserTypeOptions::formatLabel($key)
            : (string) $role;
    }

    public static function normalize(?string $role): ?string
    {
        return static::candidateRoleKey($role);
    }

    protected static function candidateRoleKey(mixed $role): ?string
    {
        if (! is_string($role) || trim($role) === '') {
            return null;
        }

        $value = Str::lower(trim($role));

        if (in_array($value, ['candidate', 'student'], true)) {
            return $value;
        }

        if (UserTypeOptions::findByKey($value) || UserTypeOptions::isCandidateManagedRole($value)) {
            return $value;
        }

        return null;
    }
}
