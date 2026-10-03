<?php

namespace App\Support;

class StatisticsMenuOptions
{
    public const ENTRANCE_EXAM_STATISTICS = 'entrance_exam_statistics';

    public const EXIT_EXAM_STATISTICS = 'exit_exam_statistics';

    public static function options(): array
    {
        return [
            self::ENTRANCE_EXAM_STATISTICS => __('filament-custom-forms::fcf.form.statistics_menu_options.entrance_exam_statistics'),
            self::EXIT_EXAM_STATISTICS => __('filament-custom-forms::fcf.form.statistics_menu_options.exit_exam_statistics'),
        ];
    }

    public static function default(): string
    {
        return self::ENTRANCE_EXAM_STATISTICS;
    }

    public static function normalize(?string $value): string
    {
        return match ((string) $value) {
            self::EXIT_EXAM_STATISTICS => self::EXIT_EXAM_STATISTICS,
            default => self::ENTRANCE_EXAM_STATISTICS,
        };
    }

    public static function label(?string $value): string
    {
        $normalized = self::normalize($value);

        return self::options()[$normalized] ?? self::options()[self::ENTRANCE_EXAM_STATISTICS];
    }
}
