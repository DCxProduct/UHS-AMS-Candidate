<?php

namespace App\Support;

use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

final class TableFilterOptions
{
    /**
     * Build choices from all matching rows, ignoring only this selector's value.
     * Keep the table's scopes, other filters and searches, without pagination.
     */
    public static function query(HasTable $livewire, string $filterName, string $field = 'value'): Builder
    {
        $applied = $livewire->tableFilters;
        $deferred = $livewire->tableDeferredFilters;
        $state = ($livewire->getTable()->hasDeferredFilters() ? $deferred : $applied) ?? [];
        Arr::set($state, $filterName.'.'.$field, null);

        try {
            $livewire->tableFilters = $state;
            $livewire->tableDeferredFilters = $state;

            return $livewire->filterTableQuery($livewire->getTable()->getQuery());
        } finally {
            $livewire->tableFilters = $applied;
            $livewire->tableDeferredFilters = $deferred;
        }
    }
}
