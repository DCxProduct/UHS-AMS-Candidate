<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/** Separate ordered application snapshots from the existing workflow queries. */
final class WorkflowProgressQuery
{
    public static function ordered(Builder $query): Builder
    {
        if (! Schema::hasColumn('custom_form_entries', 'workflow_snapshot')) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('workflow_snapshot->schema_version', '>=', 2)
            ->where('workflow_snapshot->has_workflow', true);
    }

    public static function legacy(Builder $query): Builder
    {
        if (! Schema::hasColumn('custom_form_entries', 'workflow_snapshot')) {
            return $query;
        }

        return $query->where(function (Builder $legacy): void {
            $legacy->whereNull('workflow_snapshot->schema_version')
                ->orWhere('workflow_snapshot->schema_version', '<', 2)
                ->orWhereNull('workflow_snapshot->has_workflow')
                ->orWhere('workflow_snapshot->has_workflow', false);
        });
    }
}
