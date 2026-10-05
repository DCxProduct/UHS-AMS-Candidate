<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class CaseInsensitiveSearch
{
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
