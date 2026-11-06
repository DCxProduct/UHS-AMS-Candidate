<?php

namespace App\Support;

use App\Enums\WorkflowStageType;
use App\Models\WorkflowNotification;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

/** New stage choices are frozen per application; older applications retain their form routing. */
final class WorkflowStatisticsRouting
{
    public static function forStages(array $stages): ?string
    {
        foreach ($stages as $item) {
            $stage = is_array($item) ? ($item['data'] ?? $item) : [];
            if (is_array($stage) && ($stage['stage_type'] ?? null) === WorkflowStageType::AwaitingResults->value) {
                $menu = $stage['statistics_menu'] ?? null;

                return self::isValid($menu) ? $menu : null;
            }
        }

        return null;
    }

    public static function forForm(CustomForm $form): string
    {
        $workflow = WorkflowNotification::forForm($form->getKey());

        return self::forStages($workflow?->stages ?? [])
            ?? StatisticsMenuOptions::normalize($form->statistics_menu);
    }

    public static function forEntry(CustomFormEntry $entry): string
    {
        $snapshot = WorkflowEntrySnapshot::snapshotFor($entry);
        $menu = $snapshot['statistics_menu'] ?? null;
        if (($snapshot['has_workflow'] ?? false) === true && self::isValid($menu)) {
            return $menu;
        }

        $entry->loadMissing('customForm');

        return StatisticsMenuOptions::normalize($entry->customForm?->statistics_menu);
    }

    /** Keep both statistics lists and their counts consistent with the synchronizer. */
    public static function apply(Builder $query, string $menu): Builder
    {
        $menu = StatisticsMenuOptions::normalize($menu);
        $formRouting = fn (Builder $form): Builder => $form->where('statistics_menu', $menu);
        if (! Schema::hasColumn('custom_form_entries', 'workflow_snapshot')) {
            return $query->whereHas('customForm', $formRouting);
        }

        return $query->where(function (Builder $routing) use ($menu, $formRouting): void {
            $routing->where(fn (Builder $frozen): Builder => $frozen
                ->where('workflow_snapshot->has_workflow', true)
                ->where('workflow_snapshot->statistics_menu', $menu))
                ->orWhere(function (Builder $legacy) use ($formRouting): void {
                    $legacy->where(function (Builder $missing): void {
                        $missing->whereNull('workflow_snapshot->statistics_menu')
                            ->orWhereNotIn('workflow_snapshot->statistics_menu', [
                                StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS,
                                StatisticsMenuOptions::EXIT_EXAM_STATISTICS,
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
            StatisticsMenuOptions::ENTRANCE_EXAM_STATISTICS,
            StatisticsMenuOptions::EXIT_EXAM_STATISTICS,
        ], true);
    }
}
