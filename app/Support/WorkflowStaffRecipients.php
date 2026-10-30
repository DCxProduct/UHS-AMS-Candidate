<?php

namespace App\Support;

use App\Models\Role;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Support\Collection;

final class WorkflowStaffRecipients
{
    public static function roleOptions(): array
    {
        // Candidate-type roles created by UserType may have no role_type_key.
        $candidateRoles = collect(UserTypeOptions::defaultRecords())->pluck('key')
            ->merge(UserType::query()->pluck('key'))
            ->merge(['candidate', 'student'])
            ->map(fn (string $name): string => strtolower(trim($name)))
            ->flip();

        return Role::query()
            ->where('guard_name', 'web')
            ->orderBy('id')
            ->get()
            ->reject(function (Role $role) use ($candidateRoles): bool {
                $name = strtolower(trim((string) $role->name));

                return $name === ''
                    || $candidateRoles->has($name)
                    || strtolower(trim((string) $role->role_type_key)) === 'candidate'
                    || str_starts_with($name, 'national_entrance_exam_application_')
                    || str_starts_with($name, 'national_exit_exam_application_');
            })
            ->mapWithKeys(fn (Role $role): array => [$role->name => $role->localized_name])
            ->all();
    }

    /** @return Collection<int, User> */
    public static function resolve(array $messages, int|string|null $excludedUserId = null): Collection
    {
        $query = User::query()->where('registration_type', 'admin')->where('is_active', true);

        if (filled($excludedUserId)) {
            $query->whereKeyNot($excludedUserId);
        }

        if (array_key_exists('staff_recipient_roles', $messages)) {
            $selected = $messages['staff_recipient_roles'];
            $allowed = array_map(strtolower(...), array_keys(self::roleOptions()));
            $roles = collect(is_array($selected) ? $selected : [])
                ->filter(fn (mixed $role): bool => is_string($role))
                ->map(fn (string $role): string => strtolower(trim($role)))
                ->intersect($allowed)
                ->unique()
                ->values()
                ->all();

            if ($roles === []) {
                return collect();
            }

            return $query->with('roles')->get()
                ->filter(fn (User $user): bool => $user->hasEffectiveRole($roles))
                ->values();
        }

        // Existing templates keep their explicit recipients until edited to select roles.
        $ids = $messages['staff_recipient_ids'] ?? [];
        $ids = array_values(array_unique(array_filter(is_array($ids) ? $ids : [],
            fn (mixed $id): bool => (is_int($id) || is_string($id)) && ctype_digit((string) $id))));

        return $query->whereKey($ids)->get();
    }
}
