<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

final class DynamicProfileSchema
{
    private const BLOCKED = ['id', 'user_id', 'created_by', 'updated_by', 'custom_form_id',
        'password', 'password_hash', 'password_confirmation', 'remember_token', 'api_token',
        'token', 'role', 'roles', 'permissions', 'is_admin', 'is_active', 'status',
        'student_id', 'std_id', 'candidate_account_id', 'registration_type'];

    public static function fields(?int $formId = null): array
    {
        if (! Schema::hasTable('custom_form_fields') || ! Schema::hasTable('custom_forms')) {
            return [];
        }
        $formId ??= DB::table('custom_forms')->where('slug', 'profile')->value('id');
        if (! $formId) {
            return [];
        }
        $rows = DB::table('custom_form_fields')->where('custom_form_id', $formId)->orderBy('id')->get()->all();
        return self::nodes($rows, null);
    }

    private static function nodes(array $rows, ?int $parent, int $depth = 0): array
    {
        if ($depth > 16) {
            return [];
        }
        $result = [];
        foreach ($rows as $row) {
            if (($row->parent_id ?? null) != $parent) {
                continue;
            }
            $type = str_replace('-', '_', strtolower((string) $row->type));
            $name = (string) $row->name;
            $children = self::nodes($rows, (int) $row->id, $depth + 1);
            if (in_array($type, ['section', 'fieldset', 'container', 'group', 'grid', 'wizard', 'wizard_step', 'step'], true)) {
                $result = array_replace($result, $children);
                continue;
            }
            if (in_array($name, self::BLOCKED, true) || ! preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $name)) {
                continue;
            }
            if (in_array($type, ['heading', 'info', 'placeholder', 'html', 'markdown', 'view',
                'hidden', 'password', 'file', 'file_upload', 'image', 'image_upload', 'signature'], true)) {
                continue;
            }
            $options = is_array($row->options ?? null) ? $row->options : (json_decode($row->options ?? '{}', true) ?: []);
            $result[$name] = ['type' => $type, 'required' => (bool) ($row->required ?? false),
                'options' => $options, 'children' => $children];
        }
        return $result;
    }

    public static function filter(array $data, array $fields, bool $nested = false): array
    {
        $result = [];
        foreach ($fields as $name => $field) {
            if ($nested && filled($field['options']['geo_location_type'] ?? null)) {
                continue;
            }
            $value = $data[$name] ?? null;
            if (blank($value)) {
                continue;
            }
            if ($field['type'] === 'repeater' && is_array($value)) {
                $result[$name] = array_values(array_map(
                    fn ($row) => is_array($row) ? self::filter($row, $field['children'], true) : [], $value));
            } elseif (is_array($value) && self::multiple($field)) {
                $result[$name] = array_values(array_filter($value, fn ($item) => is_scalar($item)));
            } elseif (is_scalar($value)) {
                $result[$name] = is_string($value) ? trim($value) : $value;
            }
        }
        return $result;
    }

    public static function rules(array $fields, string $prefix): array
    {
        $rules = [$prefix => ['array'.($fields ? ':'.implode(',', array_keys($fields)) : ''), 'max:200']];
        if (! $fields) {
            $rules[$prefix][] = 'size:0';
        }
        foreach ($fields as $name => $field) {
            $key = $prefix.'.'.$name;
            if ($field['type'] === 'repeater') {
                $rules[$key] = ['sometimes', 'array', 'max:100'];
                $rules += self::rules($field['children'], $key.'.*');
            } elseif (self::multiple($field)) {
                $rules[$key] = ['sometimes', 'array', 'max:100'];
                $rules[$key.'.*'] = [self::scalarRule()];
            } else {
                $rules[$key] = ['sometimes', 'nullable', self::scalarRule()];
                if (in_array($field['type'], ['email', 'email_input'], true)) {
                    $rules[$key][] = 'email';
                }
                if (in_array($field['type'], ['date', 'date_picker'], true)) {
                    $rules[$key][] = 'date_format:Y-m-d';
                }
                if (in_array($field['type'], ['number', 'number_input'], true)) {
                    $rules[$key][] = 'numeric';
                }
                if (in_array($field['type'], ['toggle', 'checkbox'], true)) {
                    $rules[$key][] = 'boolean';
                }
            }
            $choices = $field['options']['choices'] ?? null;
            if (is_array($choices) && $choices && ! array_is_list($choices) && $field['type'] !== 'repeater') {
                $rules[self::multiple($field) ? $key.'.*' : $key][] = Rule::in(array_keys($choices));
            }
        }
        return $rules;
    }

    private static function multiple(array $field): bool
    {
        return in_array($field['type'], ['checkbox_list', 'multi_select', 'tags_input'], true)
            || ! empty($field['options']['multiple']);
    }

    private static function scalarRule(): \Closure
    {
        return static function ($attribute, $value, $fail): void {
            if (! is_scalar($value) || (is_string($value) && mb_strlen($value) > 10000)) {
                $fail('The profile field must be a scalar value of at most 10000 characters.');
            }
        };
    }

    public static function complete(array $data, array $fields): bool
    {
        if (! $fields || Validator::make(['data' => self::filter($data, $fields)], self::rules($fields, 'data'))->fails()) {
            return false;
        }
        foreach ($fields as $name => $field) {
            if (! self::visible($data, $field['options'])) {
                continue;
            }
            if ($field['required'] && blank($data[$name] ?? null)) {
                return false;
            }
            if ($field['type'] === 'repeater' && is_array($data[$name] ?? null)) {
                foreach ($data[$name] as $row) {
                    if (! is_array($row) || ! self::complete($row, $field['children'])) {
                        return false;
                    }
                }
            }
        }
        // File inputs are deliberately not imported; their presence is checked separately.
        return true;
    }

    public static function visible(array $data, array $options): bool
    {
        $condition = $options['visible_when'] ?? null;
        if (! is_array($condition) || empty($condition['field']) || blank($condition['value'] ?? null)
            || in_array($condition['value'], [false, 'false'], true)) {
            return true;
        }
        $actual = data_get($data, $condition['field']);
        $expected = $condition['value'];
        $matches = array_intersect(array_map('strval', (array) $actual), array_map('strval', (array) $expected)) !== [];
        return match ($condition['operator'] ?? '=') {
            'in' => $matches,
            'not_in' => ! $matches,
            '!=', '<>' => (string) $actual !== (string) $expected,
            default => mb_strtolower((string) $actual) === mb_strtolower((string) $expected),
        };
    }
}
