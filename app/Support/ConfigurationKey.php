<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ConfigurationKey
{
    /**
     * Generate an internal identifier for a new configuration record only.
     * Existing identifiers must stay unchanged when display names are edited.
     *
     * @param  class-string<Model>  $model
     * @param  array<string>  $reserved
     */
    public static function generate(
        string $model,
        string $label,
        string $fallback,
        int $maxLength = 255,
        array $reserved = [],
    ): string {
        $base = substr(Str::slug($label, '_') ?: $fallback, 0, $maxLength);
        $key = $base;
        $suffix = 2;
        $reserved = array_map('strtolower', $reserved);

        while (in_array($key, $reserved, true) || $model::query()->whereRaw('LOWER(key) = ?', [$key])->exists()) {
            $ending = '_'.$suffix++;
            $key = substr($base, 0, $maxLength - strlen($ending)).$ending;
        }

        return $key;
    }
}
