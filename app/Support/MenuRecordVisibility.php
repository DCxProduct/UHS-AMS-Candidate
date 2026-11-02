<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

final class MenuRecordVisibility
{
    private static int $clearQueryDepth = 0;

    public static function includingCleared(callable $callback): mixed
    {
        self::$clearQueryDepth++;

        try {
            return $callback();
        } finally {
            self::$clearQueryDepth--;
        }
    }

    public static function excludesCleared(): bool
    {
        return self::$clearQueryDepth === 0;
    }

    public const DELETED_AT_COLUMNS = [
        'hidden_from_form_entries' => 'form_entries_deleted_at',
        'hidden_from_payment_status_lists' => 'payment_status_lists_deleted_at',
        'hidden_from_review_applications' => 'entrance_statistics_deleted_at',
        'hidden_from_exam_results' => 'passed_entrance_deleted_at',
        'hidden_from_exit_exam_results' => 'passed_exit_deleted_at',
        'hidden_from_statistics' => 'statistics_deleted_at',
    ];

    public static function deletionColumn(string $flag): string
    {
        return self::DELETED_AT_COLUMNS[$flag] ?? throw new InvalidArgumentException('Unknown menu visibility flag: '.$flag);
    }

    public static function visible(Builder $query, string $flag): Builder
    {
        if (! self::excludesCleared()) {
            return $query;
        }

        $column = self::deletionColumn($flag);

        if (Schema::hasColumn($query->getModel()->getTable(), $column)) {
            $query->whereNull($query->getModel()->qualifyColumn($column));
        }

        // Continue respecting rows cleared before menu deletion timestamps existed.
        $legacyColumn = $flag === 'hidden_from_statistics' ? $flag : 'data->'.$flag;

        return $query->where(function (Builder $query) use ($legacyColumn, $flag): void {
            $query->whereNull($legacyColumn)->orWhere($legacyColumn, false);

            if ($flag !== 'hidden_from_statistics') {
                $query->orWhere($legacyColumn, 'false')->orWhere($legacyColumn, 0)->orWhere($legacyColumn, '0');
            }
        });
    }

    public static function clearRecords(Builder $query, string $flag): int
    {
        $table = $flag === 'hidden_from_statistics' ? 'candidate_exit_statistics' : 'custom_form_entries';

        if ($query->getModel()->getTable() !== $table) {
            throw new InvalidArgumentException('Unexpected table for menu: '.$flag);
        }

        return self::clearColumn($query, self::deletionColumn($flag));
    }

    public static function clearPayments(Builder $query): int
    {
        if ($query->getModel()->getTable() !== 'payments') {
            throw new InvalidArgumentException('Unexpected payment table.');
        }

        return self::clearColumn($query, 'records_cleared_at');
    }

    private static function clearColumn(Builder $query, string $column): int
    {
        return DB::transaction(function () use ($query, $column): int {
            $model = $query->getModel();
            $ids = (clone $query)->reorder()->pluck($model->getQualifiedKeyName())->all();

            // Mark this menu only, without model events or changes to payment/workflow facts.
            return DB::table($model->getTable())->whereIntegerInRaw($model->getKeyName(), $ids)
                ->whereNull($column)->update([$column => now()]);
        });
    }
}
