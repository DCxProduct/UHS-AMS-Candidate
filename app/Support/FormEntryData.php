<?php

namespace App\Support;

use Chanthoeun\FilamentCustomForms\Models\CustomFormField;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use App\Models\Major;
use Illuminate\Database\Eloquent\Builder;

class FormEntryData
{
    protected static array $fieldOptionCache = [];

    protected static array $majorIdLabelCache = [];

    public static function majorKeys(): array
    {
        return [
            'selected_major',
            'degree_level_major',
            'major',
            'major_applied',
        ];
    }

    public static function academicYearKeys(): array
    {
        return [
            'academic_year',
            'selected_academic_year',
        ];
    }

    public static function firstFilled(array | object | null $data, array $keys, mixed $fallback = null): mixed
    {
        foreach ($keys as $key) {
            $value = data_get($data, $key);

            if (filled($value)) {
                return $value;
            }
        }

        return $fallback;
    }

    public static function firstFilledKey(array | object | null $data, array $keys, ?string $fallback = null): ?string
    {
        foreach ($keys as $key) {
            if (filled(data_get($data, $key))) {
                return $key;
            }
        }

        return $fallback;
    }

    public static function applyJsonLikeFilter(Builder $query, array $keys, string $search): Builder
    {
        $majorOptions = array_intersect($keys, static::majorKeys()) !== [] ? static::majorSearchOptions() : [];
        $term = CaseInsensitiveSearch::normalize($search);
        $values = array_keys(array_filter($majorOptions, fn (string $label): bool => str_contains(CaseInsensitiveSearch::normalize($label), $term)));
        foreach ($keys as $key) {
            foreach (static::fieldChoices($key) as $value => $choice) {
                if (is_array($choice) && array_key_exists('value', $choice)) {
                    $value = $choice['value'];
                    $choice = $choice['label'] ?? $value;
                }
                $label = static::localizedOptionLabel($choice, (string) $value);
                if (str_contains(CaseInsensitiveSearch::normalize($label), $term)) {
                    $values[] = $value;
                }
            }
        }
        $values = array_unique($values);

        return $query->where(function (Builder $query) use ($keys, $search, $values): void {
            CaseInsensitiveSearch::applyAny($query, array_map(fn (string $key): string => "data->{$key}", $keys), $search);
            if (array_intersect($keys, static::academicYearKeys()) !== []) {
                foreach ($keys as $key) {
                    $query->orWhere(fn (Builder $year): Builder => CaseInsensitiveSearch::applyCast($year, "data->{$key}", $search));
                }
            }
            foreach ($values as $value) {
                $query->orWhere(fn (Builder $major): Builder => static::applyJsonExactFilter($major, $keys, (string) $value));
            }
        });
    }

    /** Small configuration lookup, shared by scalar and multi-select major searches. */
    public static function majorSearchOptions(): array
    {
        $options = [];
        foreach (Major::query()->get() as $major) {
            $label = $major->localized_option_label;
            $options[(string) $major->getKey()] = $label;
            if (filled($major->key)) {
                $options[(string) $major->key] = $label;
            }
        }

        return $options;
    }

    /** Search an entry field and the same owner fallback used by its table cell. */
    public static function applyFieldSearch(Builder $query, array $keys, string $search, array $creatorColumns = [], bool $date = false, array $labels = []): Builder
    {
        return $query->where(function (Builder $query) use ($keys, $search, $creatorColumns, $date, $labels): void {
            if ($date || $labels !== []) {
                foreach ($keys as $key) {
                    $query->orWhere(fn (Builder $field): Builder => $date
                        ? CaseInsensitiveSearch::applyDate($field, "data->{$key}", $search)
                        : CaseInsensitiveSearch::applyMapped($field, "data->{$key}", $search, $labels));
                }
            } else {
                static::applyJsonLikeFilter($query, $keys, $search);
            }

            if ($creatorColumns !== []) {
                $query->orWhere(function (Builder $fallback) use ($keys, $search, $creatorColumns, $date): void {
                    foreach ($keys as $key) {
                        $fallback->where(fn (Builder $missing): Builder => $missing->whereNull("data->{$key}")->orWhere("data->{$key}", ''));
                    }
                    $fallback->whereHas('creator', function (Builder $creator) use ($search, $creatorColumns, $date): void {
                        $creator->where(function (Builder $values) use ($search, $creatorColumns, $date): void {
                            foreach ($creatorColumns as $column) {
                                $values->orWhere(fn (Builder $value): Builder => $date
                                    ? CaseInsensitiveSearch::applyDate($value, $column, $search)
                                    : CaseInsensitiveSearch::applyCast($value, $column, $search));
                            }
                        });
                    });
                });
            }
        });
    }

    public static function applyResultStatusSearch(Builder $query, string $column, string $search, ?string $entryRelation = null): Builder
    {
        $labels = [
            'pending' => __('candidate_entrance_statistics.statuses.pending'),
            'passed' => __('candidate_entrance_statistics.statuses.passed'),
        ];

        return $query->where(function (Builder $query) use ($column, $search, $entryRelation, $labels): void {
            CaseInsensitiveSearch::applyMapped($query, $column, $search, $labels);
            if (str_contains(CaseInsensitiveSearch::normalize($labels['pending']), CaseInsensitiveSearch::normalize($search))) {
                $query->orWhere(fn (Builder $pending): Builder => $pending->whereNull($column)->orWhere($column, ''));
            }
            foreach (['passed' => 'status_message', 'pending' => 'result_statuses.pending_status_message'] as $status => $field) {
                $query->orWhere(function (Builder $state) use ($column, $search, $entryRelation, $status, $field): void {
                    $state->where(fn (Builder $value): Builder => $status === 'pending'
                        ? $value->whereNull($column)->orWhere($column, '')->orWhere($column, 'pending')
                        : $value->where($column, $status));
                    if ($entryRelation !== null) {
                        $state->whereHas($entryRelation, fn (Builder $entry): Builder => WorkflowStatusSearch::applyStageText($entry, 'awaiting_results', $field, $search));
                    } else {
                        WorkflowStatusSearch::applyStageText($state, 'awaiting_results', $field, $search);
                    }
                });
            }
        });
    }

    public static function applyCandidateTypeSearch(Builder $query, string $search): Builder
    {
        $options = UserTypeOptions::allQuery()->get()->mapWithKeys(fn ($type): array => [(string) $type->key => UserTypeOptions::formatLabel((string) $type->key)])->all();
        $context = 'data->'.CandidateApplicationContext::DATA_KEY;

        return $query->where(function (Builder $query) use ($search, $options, $context): void {
            CaseInsensitiveSearch::applyMapped($query, $context, $search, $options);
            $query->orWhere(fn (Builder $type): Builder => CaseInsensitiveSearch::applyMapped($type, 'data->user_type', $search, $options));
            $query->orWhere(function (Builder $legacy) use ($search, $options, $context): void {
                $legacy->whereJsonDoesntContainKey($context)
                    ->whereHas('creator', function (Builder $creator) use ($search, $options): void {
                        $creator->where(function (Builder $roles) use ($search, $options): void {
                            CaseInsensitiveSearch::applyCast($roles, 'candidate_roles', $search);
                            $roles->orWhereHas('roles', fn (Builder $role): Builder => CaseInsensitiveSearch::applyMapped($role, 'name', $search, $options));
                            foreach ($options as $key => $label) {
                                if (str_contains(CaseInsensitiveSearch::normalize($label), CaseInsensitiveSearch::normalize($search))) {
                                    $roles->orWhereJsonContains('candidate_roles', $key);
                                }
                            }
                        });
                    });
            });
        });
    }

    public static function applyFormNameSearch(Builder $query, string $search): Builder
    {
        $ids = CustomForm::query()->get()->filter(fn (CustomForm $form): bool => str_contains(
            CaseInsensitiveSearch::normalize((string) $form->display_name),
            CaseInsensitiveSearch::normalize($search),
        ))->modelKeys();

        return $query->where(function (Builder $query) use ($search, $ids): void {
            CaseInsensitiveSearch::applyCast($query, 'name', $search);
            if ($ids !== []) {
                $query->orWhereKey($ids);
            }
        });
    }

    public static function applyJsonExactFilter(Builder $query, array $keys, mixed $value): Builder
    {
        return $query->where(function (Builder $query) use ($keys, $value): void {
            foreach ($keys as $key) {
                $query->orWhere(function (Builder $values) use ($key, $value): void {
                    $values->where("data->{$key}", $value)
                        ->orWhereJsonContains("data->{$key}", $value);
                    if (is_string($value) && ctype_digit($value)) {
                        $values->orWhere("data->{$key}", (int) $value)
                            ->orWhereJsonContains("data->{$key}", (int) $value);
                    }
                });
            }
        });
    }

    /** @return list<string> */
    public static function majorValues(array | object | null $data): array
    {
        return collect(static::normalizeOptionValues(static::firstFilled($data, static::majorKeys())))
            ->filter(fn ($value): bool => is_scalar($value) && filled($value))
            ->map(fn ($value): string => trim((string) $value))
            ->filter(fn (string $value): bool => $value !== '' && $value !== '-')
            ->unique()->values()->all();
    }

    public static function majorValue(array | object | null $data, mixed $fallback = '-'): string
    {
        $value = static::firstFilled($data, static::majorKeys(), $fallback);

        return blank($value) ? (string) $fallback : trim((string) $value);
    }

    public static function majorLabel(array | object | null $data, mixed $fallback = '-'): string
    {
        $fieldName = static::firstFilledKey($data, static::majorKeys(), 'major');
        $value = static::firstFilled($data, static::majorKeys(), $fallback);

        if (blank($value)) {
            return (string) $fallback;
        }

        return collect(static::normalizeOptionValues($value))
            ->map(function (mixed $item) use ($fieldName): string {
                $item = trim((string) $item);

                if ($item === '') {
                    return '';
                }

                return static::majorIdLabel($item)
                    ?? static::optionLabelForFieldValue((string) $fieldName, $item)
                    ?? $item;
            })
            ->filter()
            ->unique()
            ->join(', ') ?: (string) $fallback;
    }

    public static function academicYearValue(array | object | null $data, mixed $fallback = '-'): string
    {
        $value = static::firstFilled($data, static::academicYearKeys(), $fallback);

        return blank($value) ? (string) $fallback : trim((string) $value);
    }

    public static function academicYearLabel(array | object | null $data, mixed $fallback = '-'): string
    {
        $fieldName = static::firstFilledKey($data, static::academicYearKeys(), 'academic_year');
        $value = static::academicYearValue($data, $fallback);

        if ($value === (string) $fallback) {
            return $value;
        }

        return static::optionLabelForFieldValue((string) $fieldName, $value)
            ?? static::formatAcademicYearValue($value);
    }

    public static function optionLabelForFieldValue(string $fieldName, string $value): ?string
    {
        $choices = static::fieldChoices($fieldName);

        foreach ($choices as $choiceKey => $choiceLabel) {
            if (is_array($choiceLabel) && array_key_exists('value', $choiceLabel)) {
                if ((string) $choiceLabel['value'] === $value) {
                    return static::localizedOptionLabel($choiceLabel['label'] ?? $choiceLabel['value'], $value);
                }

                continue;
            }

            if ((string) $choiceKey === $value) {
                return static::localizedOptionLabel($choiceLabel, $value);
            }
        }

        return null;
    }

    public static function optionLabelForFirstMatchingFieldValue(array $fieldNames, string $value): ?string
    {
        foreach ($fieldNames as $fieldName) {
            $label = static::optionLabelForFieldValue((string) $fieldName, $value);

            if (filled($label)) {
                return $label;
            }
        }

        return null;
    }

    public static function majorOptionLabel(string $value, mixed $fallback = null): string
    {
        $fallback ??= $value;

        if (blank($value)) {
            return (string) $fallback;
        }

        return static::majorIdLabel($value)
            ?? static::optionLabelForFirstMatchingFieldValue(static::majorKeys(), $value)
            ?? (string) $fallback;
    }

    public static function majorKhmerOptionLabel(string $value): string
    {
        if (! ctype_digit($value)) {
            return $value;
        }

        $name = Major::query()->whereKey($value)->value('name_kh');

        return filled($name) ? (string) $name : $value;
    }

    protected static function majorIdLabel(string $value): ?string
    {
        if (! ctype_digit($value)) {
            return null;
        }

        $locale = app()->getLocale();

        if (! array_key_exists($locale, static::$majorIdLabelCache)) {
            static::$majorIdLabelCache[$locale] = Major::query()
                ->get(['id', 'abbreviation', 'name_en', 'name_kh'])
                ->mapWithKeys(fn (Major $major): array => [
                    (string) $major->getKey() => $major->localized_option_label,
                ])
                ->all();
        }

        if (array_key_exists($value, static::$majorIdLabelCache[$locale])) {
            return static::$majorIdLabelCache[$locale][$value];
        }

        $major = Major::query()->find($value);

        if (! $major) {
            return null;
        }

        return static::$majorIdLabelCache[$locale][$value] = $major->localized_option_label;
    }

    protected static function normalizeOptionValues(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [$value];
    }

    public static function academicYearOptionLabel(string $value, mixed $fallback = null): string
    {
        $fallback ??= $value;

        if (blank($value)) {
            return (string) $fallback;
        }

        return static::optionLabelForFirstMatchingFieldValue(static::academicYearKeys(), $value)
            ?? static::formatAcademicYearValue($value);
    }

    public static function formatAcademicYearValue(string $value): string
    {
        $normalized = trim($value);

        if ($normalized === '') {
            return $value;
        }

        if (preg_match('/^\d{4}_\d{4}$/', $normalized) === 1) {
            return str_replace('_', '-', $normalized);
        }

        return $normalized;
    }

    protected static function fieldChoices(string $fieldName): array
    {
        if (array_key_exists($fieldName, static::$fieldOptionCache)) {
            return static::$fieldOptionCache[$fieldName];
        }

        $choices = CustomFormField::query()
            ->where('name', $fieldName)
            ->whereNotNull('options')
            ->orderBy('sort')
            ->get()
            ->flatMap(function (CustomFormField $field): array {
                $options = is_array($field->options) ? $field->options : json_decode((string) $field->options, true);
                $choices = $options['choices'] ?? [];

                return is_array($choices) ? $choices : [];
            })
            ->all();

        return static::$fieldOptionCache[$fieldName] = $choices;
    }

    protected static function localizedOptionLabel(mixed $label, string $fallback): string
    {
        $locale = app()->getLocale();

        if (is_array($label)) {
            return (string) (
                $label['label_' . $locale]
                ?? $label[$locale]['label'] ?? null
                ?? $label[$locale] ?? null
                ?? $label['label_km'] ?? null
                ?? $label['label_kh'] ?? null
                ?? $label['label_en'] ?? null
                ?? $label['km'] ?? null
                ?? $label['kh'] ?? null
                ?? $label['en'] ?? null
                ?? $label['label'] ?? null
                ?? $label['name'] ?? null
                ?? collect($label)->first()
                ?? $fallback
            );
        }

        return filled($label) ? (string) $label : $fallback;
    }
}
