<?php

namespace App\Support;

use Chanthoeun\FilamentCustomForms\Models\CustomFormField;
use Illuminate\Database\Eloquent\Builder;

class FormEntryData
{
    protected static array $fieldOptionCache = [];

    public static function majorKeys(): array
    {
        return [
            'selected_major',
            'degree_level_major',
            'major',
            'major_applied',
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
        return $query->where(function (Builder $query) use ($keys, $search): void {
            foreach ($keys as $index => $key) {
                if ($index === 0) {
                    $query->where("data->{$key}", 'like', "%{$search}%");

                    continue;
                }

                $query->orWhere("data->{$key}", 'like', "%{$search}%");
            }
        });
    }

    public static function applyJsonExactFilter(Builder $query, array $keys, mixed $value): Builder
    {
        return $query->where(function (Builder $query) use ($keys, $value): void {
            foreach ($keys as $index => $key) {
                if ($index === 0) {
                    $query->where("data->{$key}", $value);

                    continue;
                }

                $query->orWhere("data->{$key}", $value);
            }
        });
    }

    public static function majorValue(array | object | null $data, mixed $fallback = '-'): string
    {
        $value = static::firstFilled($data, static::majorKeys(), $fallback);

        return blank($value) ? (string) $fallback : trim((string) $value);
    }

    public static function majorLabel(array | object | null $data, mixed $fallback = '-'): string
    {
        $fieldName = static::firstFilledKey($data, static::majorKeys(), 'major');
        $value = static::majorValue($data, $fallback);

        if ($value === (string) $fallback) {
            return $value;
        }

        return static::optionLabelForFieldValue((string) $fieldName, $value) ?? $value;
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

        return static::optionLabelForFirstMatchingFieldValue(static::majorKeys(), $value)
            ?? (string) $fallback;
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
