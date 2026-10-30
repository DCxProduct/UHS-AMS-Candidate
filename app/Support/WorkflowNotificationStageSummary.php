<?php

namespace App\Support;

use App\Enums\WorkflowPaymentSource;
use App\Enums\WorkflowStageType;
use App\Models\Role;
use App\Models\WorkflowNotification;
use Illuminate\Support\Str;

final class WorkflowNotificationStageSummary
{
    public static function from(array $stages): array
    {
        $stageData = collect($stages)
            ->map(fn (array $stage): array => $stage['data'] ?? $stage)
            ->values();

        $roleNames = $stageData
            ->flatMap(fn (array $stage): array => WorkflowNotification::normalizeResponsibleRoles($stage['responsible_role'] ?? null))
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
                $responsibleRoles = WorkflowNotification::normalizeResponsibleRoles($stage['responsible_role'] ?? null);

                $responsibleLabel = match ($type) {
                    WorkflowStageType::FormSubmission => __('workflow_notifications.view.candidate'),
                    default => count($responsibleRoles) > 0
                        ? collect($responsibleRoles)->map(fn (string $role): string => $roleLabels->get(
                            $role,
                            Str::of($role)
                                ->replace(['_', '-'], ' ')
                                ->title()
                                ->toString(),
                        ))->implode(', ')
                        : ($type === WorkflowStageType::Completed
                            ? __('workflow_notifications.view.automatic')
                            : __('workflow_notifications.not_assigned')),
                };

                $summary = [
                    'name' => self::localizedName($stage['stage_name'] ?? null, $type),
                    'type_label' => $type?->label() ?? (string) ($stage['stage_type'] ?? ''),
                    'responsible_label' => $responsibleLabel,
                    'status_message' => $stage['status_message'] ?? null,
                    'notification_message' => $stage['notification_message'] ?? null,
                ];

                if ($type === WorkflowStageType::Payment) {
                    $summary['payment_source_label'] = WorkflowPaymentSource::resolve($stage['payment_source'] ?? null)->label();
                }

                return $summary;
            })
            ->all();
    }

    public static function localizedName(?string $name, ?WorkflowStageType $type): string
    {
        $name ??= '';

        if ($type === null || (app()->getLocale() !== 'km' && $type !== WorkflowStageType::Completed)) {
            return $name;
        }

        $normalized = Str::of($name)
            ->lower()
            ->replace(['-', '_'], ' ')
            ->squish()
            ->toString();

        $defaults = match ($type) {
            WorkflowStageType::FormSubmission => ['form', 'form submission', 'submission', 'student', 'candidate'],
            WorkflowStageType::Review => ['review', 'application review', 'registrar review', 'ការពិនិត្យ'],
            WorkflowStageType::Approval => ['approval', 'approve', 'registrar approval'],
            WorkflowStageType::Payment => ['payment'],
            WorkflowStageType::AwaitingResults => ['awaiting result', 'awaiting results', 'results'],
            WorkflowStageType::Completed => [
                'completion', 'complete', 'completed', 'បញ្ចប់', 'បញ្ជប់',
                'awaiting successful candidate notification',
                'រង់ចាំសេចក្ដីជូនដំណឹងបេក្ខជនប្រឡងជាប់ជ័យលាភី',
            ],
            WorkflowStageType::Rejected => ['rejected', 'reject', 'failed', 'failure'],
        };

        return in_array($normalized, $defaults, true) ? $type->label() : $name;
    }
}
