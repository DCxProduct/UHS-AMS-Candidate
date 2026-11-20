<?php

namespace App\Support;

use App\Models\WorkflowButton;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;

final class WorkflowButtonColors
{
    private ?array $catalogColors = null;

    /** Custom button colors are cosmetic; action definitions remain frozen. */
    public static function forDefinition(array $button, string $fallback = 'gray'): string
    {
        $savedColor = $button['color'] ?? $fallback;
        $id = $button['id'] ?? null;

        if (! array_key_exists('action_key', $button)
            || $button['action_key'] !== null
            || ! (is_int($id) || is_string($id))
            || ! ctype_digit((string) $id)
            || (int) $id < 1) {
            return $savedColor;
        }

        $resolver = app(self::class);
        $resolver->catalogColors ??= WorkflowButton::withTrashed()->pluck('color', 'id')->all();

        return $resolver->catalogColors[(int) $id] ?? $savedColor;
    }

    public static function clearCachedColors(): void
    {
        app(self::class)->catalogColors = null;
    }

    /** Named palettes are registered globally so saved buttons render on every application screen. */
    public static function palettes(): array
    {
        return [
            'slate' => Color::Slate,
            'zinc' => Color::Zinc,
            'neutral' => Color::Neutral,
            'stone' => Color::Stone,
            'red' => Color::Red,
            'orange' => Color::Orange,
            'amber' => Color::Amber,
            'yellow' => Color::Yellow,
            'lime' => Color::Lime,
            'green' => Color::Green,
            'emerald' => Color::Emerald,
            'teal' => Color::Teal,
            'cyan' => Color::Cyan,
            'sky' => Color::Sky,
            'blue' => Color::Blue,
            'indigo' => Color::Indigo,
            'violet' => Color::Violet,
            'purple' => Color::Purple,
            'fuchsia' => Color::Fuchsia,
            'pink' => Color::Pink,
            'rose' => Color::Rose,
        ];
    }

    public static function swatch(string $color): string
    {
        return FilamentColor::getColor($color)[500] ?? Color::Zinc[500];
    }
}
