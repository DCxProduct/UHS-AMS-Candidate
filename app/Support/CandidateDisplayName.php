<?php

namespace App\Support;

use App\Models\User;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class CandidateDisplayName
{
    public static function for(?User $user): string
    {
        if (! $user) {
            return __('dashboard.user');
        }

        foreach ([$user->name_latin, self::latestOwnedEntryName($user->id, true), self::latestOwnedEntryName($user->id)] as $name) {
            $name = self::normalize($name);

            if (filled($name) && ! self::isInternalUsername($name, $user)) {
                return $name;
            }
        }

        $name = self::normalize($user->name);

        if (filled($name) && ! self::isInternalUsername($name, $user)) {
            return $name;
        }

        return self::normalize($user->username ?: $user->email ?: $user->phone) ?: __('dashboard.user');
    }

    public static function partsFor(?User $user): array
    {
        if (! $user) {
            return [
                'first_name_en' => '',
                'last_name_en' => '',
            ];
        }

        $storedName = self::normalize($user->name_latin);

        if (filled($storedName) && ! self::isInternalUsername($storedName, $user)) {
            return self::splitName($storedName);
        }

        foreach ([self::latestOwnedEntryData($user->id, true), self::latestOwnedEntryData($user->id)] as $data) {
            $parts = self::partsFromData($data);

            if (filled($parts['first_name_en']) || filled($parts['last_name_en'])) {
                return $parts;
            }
        }

        $name = self::normalize($user->name);

        return self::splitName(
            filled($name) && ! self::isInternalUsername($name, $user)
                ? $name
                : ''
        );
    }

    private static function latestOwnedEntryName(int $userId, bool $profileOnly = false): ?string
    {
        return self::nameFromData(self::latestOwnedEntryData($userId, $profileOnly));
    }

    private static function latestOwnedEntryData(int $userId, bool $profileOnly = false): ?array
    {
        if ($userId <= 0 || ! Schema::hasTable('custom_form_entries')) {
            return null;
        }

        $columns = Schema::getColumnListing('custom_form_entries');
        $ownerColumns = collect(['created_by', 'user_id', 'created_by_id'])
            ->filter(fn (string $column): bool => in_array($column, $columns, true))
            ->values()
            ->all();

        if ($ownerColumns === []) {
            return null;
        }

        $query = CustomFormEntry::query()
            ->when($profileOnly, function ($query): void {
                if (! Schema::hasTable('custom_forms')) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $profileFormId = CustomForm::query()
                    ->where('slug', 'profile')
                    ->value('id');

                if ($profileFormId) {
                    $query->where('custom_form_id', $profileFormId);
                } else {
                    $query->whereRaw('1 = 0');
                }
            })
            ->where(function ($query) use ($ownerColumns, $userId): void {
                foreach ($ownerColumns as $ownerColumn) {
                    $query->orWhere($ownerColumn, $userId);
                }
            });

        if (in_array('review_status', $columns, true)) {
            $query->where(function ($query): void {
                $query->whereNull('review_status')
                    ->orWhere('review_status', '!=', 'draft');
            });
        }

        if (in_array('status', $columns, true)) {
            $query->where(function ($query): void {
                $query->whereNull('status')
                    ->orWhere('status', '!=', 'draft');
            });
        }

        return $query
            ->latest('id')
            ->get()
            ->map(function (CustomFormEntry $entry): ?array {
                $data = is_array($entry->data)
                    ? $entry->data
                    : json_decode((string) $entry->data, true);

                if (! is_array($data) || blank(self::nameFromData($data))) {
                    return null;
                }

                return $data;
            })
            ->filter()
            ->first();
    }

    private static function nameFromData(?array $data): ?string
    {
        if (! $data) {
            return null;
        }

        $fullName = self::normalize($data['full_name_en'] ?? null);

        if (filled($fullName)) {
            return $fullName;
        }

        $name = self::normalize(implode(' ', array_filter([
            $data['first_name_en'] ?? null,
            $data['last_name_en'] ?? null,
        ])));

        return filled($name) ? $name : null;
    }

    private static function partsFromData(?array $data): array
    {
        if (! $data) {
            return [
                'first_name_en' => '',
                'last_name_en' => '',
            ];
        }

        $firstName = self::normalize($data['first_name_en'] ?? null);
        $lastName = self::normalize($data['last_name_en'] ?? null);

        if (filled($firstName) || filled($lastName)) {
            return [
                'first_name_en' => $firstName,
                'last_name_en' => $lastName,
            ];
        }

        return self::splitName(self::normalize($data['full_name_en'] ?? null));
    }

    private static function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2, PREG_SPLIT_NO_EMPTY) ?: [];

        return [
            'first_name_en' => (string) ($parts[0] ?? ''),
            'last_name_en' => (string) ($parts[1] ?? ''),
        ];
    }

    private static function normalize(mixed $value): string
    {
        return trim((string) preg_replace('/\s+/', ' ', (string) $value));
    }

    private static function isInternalUsername(string $name, User $user): bool
    {
        return Str::lower($name) === Str::lower((string) $user->username)
            || Str::startsWith(Str::lower($name), 'student_');
    }
}
