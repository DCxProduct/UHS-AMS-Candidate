<?php

namespace App\Support;

use App\Models\GeoLocation;
use Illuminate\Support\Facades\Schema;

final class CandidateProfilePayload
{
    public static function make(array $data, array $fallback): array
    {
        // A blank application answer must not erase the candidate's stored profile.
        $data = array_replace($fallback, array_filter($data, fn ($value) => filled($value)));
        foreach (['name_khmer' => ['name', 'full_name_kh', 'name_kh'],
            'name_latin' => ['full_name_en', 'name_en'], 'phone_number' => ['phone', 'student_phone'],
            'email' => ['email_address', 'student_email'], 'date_of_birth' => ['dob', 'birth_date']] as $key => $aliases) {
            foreach ($aliases as $alias) {
                if (blank($data[$key] ?? null) && filled($data[$alias] ?? null)) {
                    $data[$key] = $data[$alias];
                }
            }
        }
        foreach (['name_khmer' => ['first_name_kh', 'last_name_kh'], 'name_latin' => ['first_name_en', 'last_name_en']] as $field => $parts) {
            if (blank($data[$field] ?? null)) {
                $data[$field] = trim(implode(' ', array_map(fn ($part) => is_scalar($data[$part] ?? null) ? $data[$part] : '', $parts)));
            }
        }
        $fields = DynamicProfileSchema::fields();
        $data = self::normalizeChoices($data, $fields);
        $values = DynamicProfileSchema::filter($data, $fields);
        $locations = [];
        foreach ($fields as $field => $configuration) {
            $type = $configuration['options']['geo_location_type'] ?? null;
            if (! in_array($type, ['province', 'district', 'commune', 'village'], true)) {
                continue;
            }
            unset($values[$field]);
            $value = $data[$field] ?? null;
            if (! is_scalar($value) || blank($value) || ! Schema::hasTable('geo_locations')) {
                continue;
            }
            $query = GeoLocation::query()->where('type', $type);
            $location = ($configuration['options']['geo_location_value_column'] ?? 'id') === 'code'
                ? $query->where('code', (string) $value)->first()
                : $query->find($value);
            if ($location) {
                $locations[$field] = (string) $location->code;
            }
        }
        return ['version' => 1, 'data' => $values, 'locations' => $locations];
    }

    /** Send stored choice keys even when older entries contain translated labels. */
    public static function normalizeChoices(array $data, array $fields): array
    {
        foreach ($fields as $name => $field) {
            if (! array_key_exists($name, $data)) {
                continue;
            }
            if ($field['type'] === 'repeater' && is_array($data[$name])) {
                $data[$name] = array_map(fn ($row) => is_array($row)
                    ? self::normalizeChoices($row, $field['children'] ?? []) : $row, $data[$name]);
                continue;
            }
            $choices = $field['options']['choices'] ?? null;
            if (! is_array($choices) || $choices === [] || array_is_list($choices)
                || ! empty($field['options']['geo_location_type'])) {
                continue;
            }
            $multiple = in_array($field['type'], ['checkbox_list', 'multi_select', 'tags_input'], true)
                || ! empty($field['options']['multiple']);
            $data[$name] = $multiple && is_array($data[$name])
                ? array_map(fn ($value) => self::choiceKey($value, $choices), $data[$name])
                : self::choiceKey($data[$name], $choices);
        }

        return $data;
    }

    private static function choiceKey(mixed $value, array $choices): mixed
    {
        if (! is_string($value) && ! is_int($value)) {
            return $value;
        }
        if (array_key_exists($value, $choices)) {
            return $value;
        }
        $normalized = mb_strtolower(trim((string) $value));
        if ($normalized === '') {
            return $value;
        }
        $matches = [];
        foreach ($choices as $key => $labels) {
            foreach ([(string) $key, ...(is_array($labels) ? array_values($labels) : [$labels])] as $label) {
                if (is_scalar($label) && mb_strtolower(trim((string) $label)) === $normalized) {
                    $matches[(string) $key] = (string) $key;
                }
            }
        }

        // Preserve unknown answers for the destination to handle using its schema.
        return count($matches) === 1 ? reset($matches) : $value;
    }
}
