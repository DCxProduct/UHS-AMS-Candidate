<?php

namespace App\Support;

use App\Enums\WorkflowStageType;
use App\Models\WorkflowNotification;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/** Freeze explicit workflow result destinations while retaining older form routing. */
final class WorkflowPassedResultRouting
{
    public static function forStages(array $stages): ?string
    {
        foreach ($stages as $item) {
            $stage = is_array($item) ? ($item['data'] ?? $item) : [];
            if (is_array($stage) && ($stage['stage_type'] ?? null) === WorkflowStageType::Completed->value) {
                $menu = $stage['passed_result_menu'] ?? null;

                return self::isValid($menu) ? $menu : null;
            }
        }

        return null;
    }

    public static function forForm(CustomForm $form): string
    {
        $workflow = WorkflowNotification::forForm($form->getKey());

        return self::forStages($workflow?->stages ?? [])
            ?? PassedResultMenuOptions::normalize($form->passed_result_menu);
    }

    public static function forEntry(CustomFormEntry $entry): string
    {
        $snapshot = WorkflowEntrySnapshot::snapshotFor($entry);
        $menu = $snapshot['passed_result_menu'] ?? null;
        if (($snapshot['has_workflow'] ?? false) === true && self::isValid($menu)) {
            return $menu;
        }

        $entry->loadMissing('customForm');

        return PassedResultMenuOptions::normalize($entry->customForm?->passed_result_menu);
    }

    /** Use the same destination for result lists, counts, filters, exports, and notifications. */
    public static function apply(Builder $query, string $menu): Builder
    {
        $menu = PassedResultMenuOptions::normalize($menu);
        $formRouting = fn (Builder $form): Builder => $form->where('passed_result_menu', $menu);
        if (! Schema::hasColumn('custom_form_entries', 'workflow_snapshot')) {
            return $query->whereHas('customForm', $formRouting);
        }

        return $query->where(function (Builder $routing) use ($menu, $formRouting): void {
            $routing->where(fn (Builder $frozen): Builder => $frozen
                ->where('workflow_snapshot->has_workflow', true)
                ->where('workflow_snapshot->passed_result_menu', $menu))
                ->orWhere(function (Builder $legacy) use ($formRouting): void {
                    $legacy->where(function (Builder $missing): void {
                        $missing->whereNull('workflow_snapshot->passed_result_menu')
                            ->orWhereNotIn('workflow_snapshot->passed_result_menu', [
                                PassedResultMenuOptions::EXAM_RESULTS,
                                PassedResultMenuOptions::EXIT_EXAM_RESULTS,
                            ])
                            ->orWhereNull('workflow_snapshot->has_workflow')
                            ->orWhere('workflow_snapshot->has_workflow', false);
                    })->whereHas('customForm', $formRouting);
                });
        });
    }

    private static function isValid(mixed $menu): bool
    {
        return in_array($menu, [
            PassedResultMenuOptions::EXAM_RESULTS,
            PassedResultMenuOptions::EXIT_EXAM_RESULTS,
        ], true);
    }
}
