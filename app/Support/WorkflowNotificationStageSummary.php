<?php

namespace App\Support;

use App\Enums\WorkflowStageType;
use App\Models\Role;
use Illuminate\Support\Str;

final class WorkflowNotificationStageSummary
{
    public static function from(array $stages): array
    {
        $stageData = collect($stages)
            ->map(fn (array $stage): array => $stage['data'] ?? $stage)
            ->values();

        $roleNames = $stageData
            ->pluck('responsible_role')
            ->filter()
            ->unique()
            ->values();

        $roleLabels = Role::query()
            ->whereIn('name', $roleNames->all())
            ->get()
            ->mapWithKeys(function (Role $role): array {
                $label = $role->localized_name;

                if ($label === $role->name) {
                    $label = Str::of($role->name)
                        ->replace(['_', '-'], ' ')
                        ->title()
                        ->toString();
                }

                return [$role->name => $label];
            });

        return $stageData
            ->map(function (array $stage) use ($roleLabels): array {
                $type = WorkflowStageType::tryFrom((string) ($stage['stage_type'] ?? null));

                $responsibleLabel = match ($type) {
                    WorkflowStageType::FormSubmission => __('workflow_notifications.view.candidate'),
                    WorkflowStageType::Completed => __('workflow_notifications.view.automatic'),
                    default => filled($stage['responsible_role'] ?? null)
                        ? $roleLabels->get(
                            $stage['responsible_role'],
                            Str::of((string) $stage['responsible_role'])
                                ->replace(['_', '-'], ' ')
                                ->title()
                                ->toString(),
                        )
                        : __('workflow_notifications.not_assigned'),
                };

                return [
                    'name' => self::localizedName($stage['stage_name'] ?? null, $type),
                    'type_label' => $type?->label() ?? (string) ($stage['stage_type'] ?? ''),
                    'responsible_label' => $responsibleLabel,
                    'status_message' => $stage['status_message'] ?? null,
                    'notification_message' => $stage['notification_message'] ?? null,
                ];
            })
            ->all();
    }

    public static function localizedName(?string $name, ?WorkflowStageType $type): string
    {
        $name ??= '';

        if (app()->getLocale() !== 'km' || $type === null) {
            return $name;
        }

        $normalized = Str::of($name)
            ->lower()
            ->replace(['-', '_'], ' ')
            ->squish()
            ->toString();

        $defaults = match ($type) {
            WorkflowStageType::FormSubmission => ['form', 'form submission', 'submission', 'student', 'candidate'],
            WorkflowStageType::Review => ['review', 'application review', 'registrar review'],
            WorkflowStageType::Approval => ['approval', 'approve', 'registrar approval'],
            WorkflowStageType::Payment => ['payment'],
            WorkflowStageType::AwaitingResults => ['awaiting result', 'awaiting results', 'results'],
            WorkflowStageType::Completed => ['completion', 'complete', 'completed'],
        };

        return in_array($normalized, $defaults, true) ? $type->label() : $name;
    }
}
