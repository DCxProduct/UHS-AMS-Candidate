<?php

namespace App\Support;

use App\Models\User;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Support\Facades\DB;

final class CandidateApplicationContext
{
    public const DATA_KEY = 'application_candidate_type';

    public static function candidateType(CustomForm $form, ?User $user = null): ?string
    {
        if ($form->isProfileForm()) {
            return null;
        }

        $slugType = self::knownType(str_replace('-', '_', (string) $form->slug));
        if ($slugType !== null) {
            return $slugType;
        }

        $roles = $form->allowed_roles;
        if (is_string($roles)) {
            $decoded = json_decode($roles, true);
            $roles = is_array($decoded) ? $decoded : [$roles];
        }

        $types = collect(is_array($roles) ? $roles : [])
            ->map(fn (mixed $role): ?string => is_string($role) ? self::knownType($role) : null)
            ->filter()
            ->unique()
            ->values();

        if ($types->count() === 1) {
            return $types->first();
        }

        $matching = $types->intersect(CandidateApplicationLevels::formRoleKeys($user));

        return $matching->count() === 1 ? $matching->first() : null;
    }

    public static function dataForForm(CustomForm $form, ?User $user, array $data): array
    {
        unset($data[self::DATA_KEY]);
        if ($form->isProfileForm()) {
            return $data;
        }

        if (($type = self::candidateType($form, $user)) !== null) {
            $data[self::DATA_KEY] = $type;
        }

        return $data;
    }

    public static function stamp(CustomFormEntry $entry): void
    {
        if ($entry->exists && ! $entry->isDirty('data')) {
            return;
        }

        $data = is_array($entry->data) ? $entry->data : [];
        if ($entry->exists) {
            $original = DB::table($entry->getTable())->where($entry->getKeyName(), $entry->getKey())->value('data');
            $original = is_string($original) ? json_decode($original, true, flags: JSON_THROW_ON_ERROR) : $original;
            unset($data[self::DATA_KEY]);
            if (is_array($original) && array_key_exists(self::DATA_KEY, $original)) {
                $data[self::DATA_KEY] = $original[self::DATA_KEY];
            }
        } else {
            $form = $entry->customForm;
            $user = filled($entry->created_by) ? $entry->creator : auth()->user();
            unset($data[self::DATA_KEY]);
            if ($form instanceof CustomForm) {
                $data = self::dataForForm($form, $user instanceof User ? $user : null, $data);
            }
        }

        $entry->data = $data;
    }

    private static function knownType(string $key): ?string
    {
        $key = strtolower(trim($key));

        return ! in_array($key, ['candidate', 'student'], true) && UserTypeOptions::findByKey($key)
            ? $key
            : null;
    }
}
