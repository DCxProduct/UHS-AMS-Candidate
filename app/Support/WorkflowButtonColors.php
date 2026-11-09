<?php

namespace App\Support;

use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;

final class WorkflowButtonColors
{
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
