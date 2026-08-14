<?php

namespace App\Support;

class PassedResultMenuOptions
{
    public const EXAM_RESULTS = 'exam_results';
    public const EXIT_EXAM_RESULTS = 'exit_exam_results';

    public static function options(): array
    {
        return [
            self::EXAM_RESULTS => __('filament-custom-forms::fcf.form.passed_result_menu_options.exam_results'),
            self::EXIT_EXAM_RESULTS => __('filament-custom-forms::fcf.form.passed_result_menu_options.exit_exam_results'),
        ];
    }

    public static function default(): string
    {
        return self::EXAM_RESULTS;
    }

    public static function normalize(?string $value): string
    {
        return match ((string) $value) {
            self::EXIT_EXAM_RESULTS => self::EXIT_EXAM_RESULTS,
            default => self::EXAM_RESULTS,
        };
    }

    public static function label(?string $value): string
    {
        $normalized = self::normalize($value);

        return self::options()[$normalized] ?? self::options()[self::EXAM_RESULTS];
    }
}
