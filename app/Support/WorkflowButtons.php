<?php

namespace App\Support;

use App\Enums\WorkflowStageType;
use App\Models\WorkflowNotification;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;

/** Read button presentation and availability from the application's frozen workflow. */
final class WorkflowButtons
{
    /** Preview a new submission's workflow without creating or updating an application. */
    public static function forForm(int|string|null $formId): CustomFormEntry
    {
        return (new CustomFormEntry)->forceFill([
            'custom_form_id' => $formId,
            'workflow_snapshot' => WorkflowEntrySnapshot::forForm($formId),
            'data' => [],
        ]);
    }

    public static function actionKey(string $action): ?string
    {
        return match ($action) {
            'submitted', 'submit' => 'submitted',
            'accept', 'accepted', 'approve_from_view' => 'accept',
            'send_back', 'rejected', 'reject_from_view', 'send_back_after_reject', 'send_back_after_reject_from_view' => 'send_back',
            'reject', 'reject_final_from_view' => 'reject',
            'verify' => 'verify',
            'paid', 'pay' => 'paid',
            'passed', 'bulk_passed' => 'passed',
            'notify', 'notify_student', 'notify_all_students' => 'notify',
            default => null,
        };
    }

    public static function allows(?CustomFormEntry $entry, string $action, ?WorkflowStageType $stageType = null): bool
    {
        $key = self::actionKey($action);
        if ($entry === null || $key === null) {
            return true;
        }

        // Final Payment/Results checkpoints also expose their existing release notification.
        // A custom payment/results button selection only configures that stage's own actions.
        if ($stageType !== null && ! array_key_exists($key, WorkflowStageTypeSettings::actions($stageType->value))) {
            return true;
        }

        $stage = self::stage($entry, $key, $stageType);
        if (! is_array($stage['buttons'] ?? null)) {
            return true;
        }

        return self::buttonFromStage($stage, $key) !== null;
    }

    public static function configuredButton(?CustomFormEntry $entry, string $action): ?array
    {
        $key = self::actionKey($action);

        return $entry === null || $key === null ? null : self::buttonFromStage(self::stage($entry, $key), $key);
    }

    public static function label(?CustomFormEntry $entry, string $action, string $fallback): string
    {
        $button = self::configuredButton($entry, $action);
        $localeKey = in_array(app()->getLocale(), ['km', 'kh'], true) ? 'label_km' : 'label_en';
        $label = $button[$localeKey] ?? null;
        if (blank($label)) {
            $label = filled($button['label_en'] ?? null) ? $button['label_en'] : ($button['label_km'] ?? null);
        }

        return filled($label) ? (string) $label : $fallback;
    }

    public static function icon(?CustomFormEntry $entry, string $action, ?string $fallback = null): ?string
    {
        return self::configuredButton($entry, $action)['icon'] ?? $fallback;
    }

    public static function color(?CustomFormEntry $entry, string $action, string $fallback): string
    {
        return self::configuredButton($entry, $action)['color'] ?? $fallback;
    }

    private static function buttonFromStage(?array $stage, string $key): ?array
    {
        foreach ($stage['buttons'] ?? [] as $button) {
            if (is_array($button) && ($button['action_key'] ?? null) === $key) {
                return $button;
            }
        }

        return null;
    }

    private static function stage(CustomFormEntry $entry, string $key, ?WorkflowStageType $stageType = null): ?array
    {
        $stageType ??= match ($key) {
            'submitted' => WorkflowStageType::FormSubmission,
            'accept', 'send_back', 'reject' => WorkflowStageType::Review,
            'verify' => WorkflowStageType::Verification,
            'paid' => WorkflowStageType::Payment,
            'passed' => WorkflowStageType::AwaitingResults,
            'notify' => WorkflowStageType::Completed,
        };
        $matches = static fn (array $stage): bool => ($stage['stage_type'] ?? null) === $stageType->value
            || ($stageType === WorkflowStageType::Review && ($stage['stage_type'] ?? null) === WorkflowStageType::Approval->value);

        if (WorkflowStageProgression::enabled($entry)) {
            $current = WorkflowStageProgression::currentStage($entry);
            if ($current !== null && $matches($current)) {
                return $current;
            }
        }

        foreach (WorkflowNotification::forEntry($entry)?->stages ?? [] as $item) {
            $stage = is_array($item) ? ($item['data'] ?? $item) : [];
            if (is_array($stage) && $matches($stage)) {
                return $stage;
            }
        }

        return null;
    }
}
