<?php

namespace App\Support;

class LocalizedNumber
{
    public static function digits(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        $string = (string) $value;

        if (app()->getLocale() !== 'km') {
            return $string;
        }

        return strtr($string, [
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
