<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Schema;
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
     * Built-in variables plus the custom ones, as offered to the editor.
     */
    public function variableNames(): array
    {
        return array_values(array_unique([
            ...(static::VARIABLES[$this->key] ?? []),
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
