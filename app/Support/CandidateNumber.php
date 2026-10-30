<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

final class CandidateNumber
{
    /** Format the visible number while keeping the year-scoped stored ID unchanged. */
    public static function format(mixed $value): string
    {
        $number = trim((string) ($value ?? ''));

        if (preg_match('/^([A-Z]+)\d{2}(\d{4,})$/i', $number, $matches) === 1) {
            return strtoupper($matches[1]).'-'.$matches[2];
        }

        return $number;
    }

    public static function applySearch(Builder $query, string $column, string $search): Builder
    {
        $search = trim($search);

        return $query->where(function (Builder $query) use ($column, $search): void {
            CaseInsensitiveSearch::apply($query, $column, $search);

            if (preg_match('/^([A-Z]*)-(\d*)$/i', $search, $matches) === 1) {
                CaseInsensitiveSearch::apply($query, $column, $matches[1].'__'.$matches[2], or: true);
            }
        });
    }
}
