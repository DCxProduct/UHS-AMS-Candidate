<?php

namespace App\Support;

use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Carbon;

class LocalizedDate
{
    public static function dayMonthYear(mixed $value): string
    {
        if (blank($value)) {
            return '-';
        }

        $date = self::parse($value);

        if (! $date) {
            return (string) $value;
        }

        $day = str_pad((string) $date->day, 2, '0', STR_PAD_LEFT);
        $year = (string) $date->year;
        $locale = app()->getLocale();

        if ($locale === 'km') {
            return implode('-', [
                self::toKhmerDigits($day),
                self::khmerMonth($date->month),
                self::toKhmerDigits($year),
            ]);
        }

        return implode('-', [
            $day,
            $date->format('M'),
            $year,
        ]);
    }

    public static function dayMonthYearTime(mixed $value): string
    {
        if (blank($value)) {
            return '-';
        }

        $date = self::parse($value);

        if (! $date) {
            return (string) $value;
        }

        $formatted = self::dayMonthYear($date) . ' ' . $date->format('H:i');

        return app()->getLocale() === 'km'
            ? self::toKhmerDigits($formatted)
            : $formatted;
    }

    protected static function parse(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function khmerMonth(int $month): string
    {
        return match ($month) {
            1 => 'មករា',
            2 => 'កុម្ភៈ',
            3 => 'មីនា',
            4 => 'មេសា',
            5 => 'ឧសភា',
            6 => 'មិថុនា',
            7 => 'កក្កដា',
            8 => 'សីហា',
            9 => 'កញ្ញា',
            10 => 'តុលា',
            11 => 'វិច្ឆិកា',
            12 => 'ធ្នូ',
            default => '',
        };
    }

    protected static function toKhmerDigits(string $value): string
    {
        return strtr($value, [
            '0' => '០',
            '1' => '១',
            '2' => '២',
            '3' => '៣',
            '4' => '៤',
            '5' => '៥',
            '6' => '៦',
            '7' => '៧',
            '8' => '៨',
            '9' => '៩',
        ]);
    }
}
