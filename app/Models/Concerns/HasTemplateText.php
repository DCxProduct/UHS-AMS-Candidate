<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Shared by the email and SMS templates: one row per fixed key, one text
 * (written in Khmer or English, sent to everyone as written) with
 * {{ variables }}, admin-made custom variables, and built-in defaults for
 * any field left empty.
 */
trait HasTemplateText
{
    abstract public static function defaults(string $key): array;

    public function initializeHasTemplateText(): void
    {
        $this->mergeCasts(['custom_variables' => 'array']);
    }

    /**
     * The template for a key: the saved row, or an unsaved one with defaults
     * when the row or the table does not exist yet.
     */
    public static function for(string $key): static
    {
        try {
            if (Schema::hasTable((new static)->getTable())) {
                return static::query()->firstOrCreate(['key' => $key], static::defaults($key));
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return new static(['key' => $key, ...static::defaults($key)]);
    }

    /**
     * Built-in templates are used by the system (for example password reset):
     * they can be edited but not deleted.
     */
    public function isBuiltIn(): bool
    {
        return in_array($this->key, static::TEMPLATES, true);
    }

    public function label(): string
    {
        return $this->isBuiltIn() || blank($this->name)
            ? __(static::LANG_FILE.'.templates.'.$this->key)
            : (string) $this->name;
    }

    /**
     * Variables a template offers: its own for built-in templates, the general ones otherwise.
     */
    public static function builtInVariablesFor(?self $record): array
    {
        return $record !== null && array_key_exists((string) $record->key, static::VARIABLES)
            ? static::VARIABLES[$record->key]
            : static::GENERAL_VARIABLES;
    }

    /**
     * A unique key for a new template, made from its name.
     */
    public static function keyFromName(string $name): string
    {
        $base = Str::slug($name, '_') ?: 'template';
        $key = $base;
        $suffix = 1;

        while (static::query()->where('key', $key)->exists() || in_array($key, static::TEMPLATES, true)) {
            $key = $base.'_'.$suffix++;
        }

        return $key;
    }

    /**
     * Built-in variables plus the custom ones, as offered to the editor.
     */
    public function variableNames(): array
    {
        return array_values(array_unique([
            ...static::builtInVariablesFor($this),
            ...array_keys($this->customVariables()),
        ]));
    }

    /**
     * Custom variables: name => value.
     */
    public function customVariables(): array
    {
        return collect($this->custom_variables ?? [])
            ->filter(fn ($item): bool => is_array($item) && filled($item['name'] ?? null))
            ->mapWithKeys(fn (array $item): array => [
                (string) $item['name'] => (string) ($item['value'] ?? ''),
            ])
            ->all();
    }

    /**
     * A plain-text field with {{ variables }} filled in.
     */
    public function text(string $field, array $variables = []): string
    {
        return self::fillVariables($this->raw($field), array_map(fn ($value): string => strip_tags((string) $value), $variables));
    }

    protected function raw(string $field): string
    {
        return filled($this->{$field})
            ? (string) $this->{$field}
            : (string) (static::defaults($this->key)[$field] ?? '');
    }

    /**
     * Replace {{ name }} style variables; unknown ones are left as typed.
     */
    protected static function fillVariables(string $text, array $values): string
    {
        return preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/i',
            fn (array $match): string => array_key_exists($match[1], $values) ? (string) $values[$match[1]] : $match[0],
            $text,
        );
    }
}
