<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

final class CaseInsensitiveSearch
{
    public static function normalize(string $value): string
    {
        return Str::lower(strtr(trim($value), array_combine(
            preg_split('//u', '០១២៣៤៥៦៧៨៩', -1, PREG_SPLIT_NO_EMPTY),
            str_split('0123456789'),
        )));
    }

    /** Search scalar columns without applying LOWER directly to dates or numbers. */
    public static function applyCast(Builder $query, string $column, string $search, bool $or = false): Builder
    {
        $expression = self::textExpression($query, $column);
        $terms = array_unique([Str::lower($search), self::normalize($search)]);
        if (preg_match('/^\d{4}[-_]\d{4}$/D', self::normalize($search))) {
            $terms[] = str_replace('-', '_', self::normalize($search));
            $terms[] = str_replace('_', '-', self::normalize($search));
        }
        $numeric = preg_replace('/[\s,$៛€]/u', '', self::normalize($search));
        if (is_numeric($numeric) && str_contains($numeric, '.')) {
            $terms[] = rtrim(rtrim($numeric, '0'), '.');
        } elseif (is_numeric($numeric)) {
            $terms[] = $numeric;
        }

        return $query->{$or ? 'orWhere' : 'where'}(function (Builder $query) use ($expression, $terms): void {
            foreach (array_unique($terms) as $term) {
                $query->orWhereRaw("LOWER({$expression}) LIKE ?", ['%'.$term.'%']);
            }
        });
    }

    /** Match stored values and the small, configured set of labels displayed for them. */
    public static function applyMapped(Builder $query, string $column, string $search, array $labels, bool $or = false): Builder
    {
        $term = self::normalize($search);
        $values = [];
        foreach ($labels as $value => $label) {
            $text = implode(' ', array_filter(Arr::flatten((array) $label), 'is_scalar'));
            if (str_contains(self::normalize($text), $term)) {
                $values[] = $value;
            }
        }

        return $query->{$or ? 'orWhere' : 'where'}(function (Builder $query) use ($column, $search, $values): void {
            self::applyCast($query, $column, $search);
            if ($values !== []) {
                $query->orWhereIn($column, $values);
                $strings = array_values(array_filter($values, fn ($value): bool => is_string($value) && ! is_numeric($value)));
                if ($strings !== []) {
                    $expression = self::textExpression($query, $column);
                    $placeholders = implode(', ', array_fill(0, count($strings), '?'));
                    $query->orWhereRaw("LOWER({$expression}) IN ({$placeholders})", array_map(self::normalize(...), $strings));
                }
            }
        });
    }

    /** Match ISO storage and the day-month-year/date-time text shown by LocalizedDate. */
    public static function applyDate(Builder $query, string $column, string $search, bool $or = false): Builder
    {
        $months = [
            '01' => ['jan', 'january', 'មករា'], '02' => ['feb', 'february', 'កុម្ភៈ'],
            '03' => ['mar', 'march', 'មីនា'], '04' => ['apr', 'april', 'មេសា'],
            '05' => ['may', 'may', 'ឧសភា'], '06' => ['jun', 'june', 'មិថុនា'],
            '07' => ['jul', 'july', 'កក្កដា'], '08' => ['aug', 'august', 'សីហា'],
            '09' => ['sep', 'september', 'កញ្ញា'], '10' => ['oct', 'october', 'តុលា'],
            '11' => ['nov', 'november', 'វិច្ឆិកា'], '12' => ['dec', 'december', 'ធ្នូ'],
        ];
        $term = self::normalize($search);
        foreach ($months as [$short, $long, $khmer]) {
            $term = str_replace([$long, $khmer], $short, $term);
        }

        $text = self::textExpression($query, $column);
        $day = "SUBSTR({$text}, 9, 2)";
        $month = "SUBSTR({$text}, 6, 2)";
        $year = "SUBSTR({$text}, 1, 4)";
        $time = "SUBSTR({$text}, 12, 8)";
        $monthName = 'CASE '.$month;
        foreach ($months as $number => [$short]) {
            $monthName .= " WHEN '".str_pad((string) $number, 2, '0', STR_PAD_LEFT)."' THEN '{$short}'";
        }
        $monthName .= " ELSE '' END";
        $formats = [];
        foreach ([$monthName, $month] as $middle) {
            $parts = [$day, "'-'", $middle, "'-'", $year, "' '", $time];
            $formats[] = in_array($query->getConnection()->getDriverName(), ['mysql', 'mariadb'], true)
                ? 'CONCAT('.implode(', ', $parts).')'
                : '('.implode(' || ', $parts).')';
        }

        return $query->{$or ? 'orWhere' : 'where'}(function (Builder $query) use ($column, $search, $term, $formats): void {
            self::applyCast($query, $column, $search);
            foreach ($formats as $format) {
                $query->orWhereRaw("LOWER({$format}) LIKE ?", ['%'.$term.'%']);
            }
        });
    }

    private static function textExpression(Builder $query, string $column): string
    {
        $column = $query->getQuery()->getGrammar()->wrap($column);
        $type = in_array($query->getConnection()->getDriverName(), ['mysql', 'mariadb'], true) ? 'CHAR' : 'TEXT';

        return "CAST({$column} AS {$type})";
    }

    public static function apply(Builder $query, string $column, string $search, bool $or = false): Builder
    {
        $wrappedColumn = $query->getQuery()->getGrammar()->wrap($column);
        $method = $or ? 'orWhereRaw' : 'whereRaw';

        return $query->{$method}(
            "LOWER({$wrappedColumn}) LIKE ?",
            ['%'.Str::lower($search).'%'],
        );
    }

    /**
     * Apply one case-insensitive partial match across multiple columns.
     * JSON selectors are wrapped through the active database grammar.
     */
    public static function applyAny(Builder $query, array $columns, string $search): Builder
    {
        return $query->where(function (Builder $query) use ($columns, $search): void {
            foreach (array_values($columns) as $index => $column) {
                self::apply($query, (string) $column, $search, $index > 0);
            }
        });
    }
}
