<?php

namespace App\Models;

use App\Enums\WorkflowPaymentSource;
use App\Enums\WorkflowStageType;
use App\Support\PassedResultMenuOptions;
use App\Support\StatisticsMenuOptions;
use App\Support\WorkflowEntrySnapshot;
use App\Support\WorkflowStageTypeSettings;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A reusable workflow template: an ordered list of stages assigned to forms.
 */
class WorkflowNotification extends Model
{
    protected $fillable = [
        'name',
        'stages',
    ];

    protected function casts(): array
    {
        return [
            'stages' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $workflow): void {
            $stages = self::normalizeStages($workflow->stages, applyTypeDefaults: ! $workflow->exists);
            $types = self::stageTypes($stages);

            if (in_array(WorkflowStageType::Verification->value, $types, true)) {
                $canonical = array_map(fn (string $type): string => $type === WorkflowStageType::Approval->value ? WorkflowStageType::Review->value : $type, $types);
                $seen = [];
                foreach ($canonical as $type) {
                    $behavior = WorkflowStageType::tryFrom($type);
                    if ($behavior === null || $behavior === WorkflowStageType::Rejected || isset($seen[$type])) {
                        throw ValidationException::withMessages([
                            'stages' => isset($seen[$type])
                                ? __('workflow_notifications.validation.duplicate_stage_type', ['stage' => $behavior?->label() ?? $type])
                                : __('workflow_notifications.validation.invalid_stage_type'),
                        ]);
                    }
                    $seen[$type] = true;
                }
            }

            if (self::hasCatalogSelection($stages) && $types !== self::stageTypes((array) $workflow->getOriginal('stages'))) {
                $seen = [];
                foreach ($types as $type) {
                    $behavior = WorkflowStageType::tryFrom($type);
                    $key = $behavior === WorkflowStageType::Approval ? WorkflowStageType::Review->value : $type;
                    if ($behavior === null || isset($seen[$key])) {
                        throw ValidationException::withMessages([
                            'stages' => $behavior === null
                                ? __('workflow_notifications.validation.invalid_stage_type')
                                : __('workflow_notifications.validation.duplicate_stage_type', ['stage' => $behavior->label()]),
                        ]);
                    }
                    $seen[$key] = true;
                }
            }

            $workflow->stages = $stages;
        });
    }

    /**
     * Keep stage statuses, delivered notifications, and immediate action alerts
     * independent while normalizing the builder wrapper.
     */
    public static function normalizeStages(?array $stages, bool $applyTypeDefaults = true): array
    {
        return collect($stages ?? [])
            ->map(function (mixed $stage) use ($applyTypeDefaults): mixed {
                if (! is_array($stage)) {
                    return $stage;
                }

                $hasDataWrapper = is_array($stage['data'] ?? null);
                $data = $hasDataWrapper ? $stage['data'] : $stage;

                if (array_key_exists('stage_type_id', $data)) {
                    $data = \App\Models\WorkflowStageType::bindStage($data, $applyTypeDefaults);
                }

                $stageType = $data['stage_type'] ?? null;
                if ($stageType === null || (is_string($stageType) && trim($stageType) === '')) {
                    $data['stage_type'] = null;
                    unset($data['enabled_actions'], $data['stage_type_labels']);
                }

                if (array_key_exists('enabled_actions', $data)) {
                    $behavior = ($data['stage_type'] ?? null) === WorkflowStageType::Approval->value
                        ? WorkflowStageType::Review->value : (string) ($data['stage_type'] ?? '');
                    Validator::make($data, [
                        'enabled_actions' => ['nullable', 'array', 'min:1'],
                        'enabled_actions.*' => ['required', 'string', 'distinct:strict', Rule::in(array_keys(WorkflowStageTypeSettings::actions($behavior)))],
                    ])->validate();
                    if ($data['enabled_actions'] !== null) {
                        $data['enabled_actions'] = array_keys(WorkflowStageTypeSettings::selectedActions($behavior, $data['enabled_actions']));
                    }
                }

                if (array_key_exists('alert_message', $data)) {
                    $data['alert_message'] = self::normalizeAlertMessage($data['alert_message']);
                }

                if (array_key_exists('responsible_role', $data)) {
                    $roles = self::normalizeResponsibleRoles($data['responsible_role']);
                    $data['responsible_role'] = $roles === [] ? null : $roles;
                }

                if (($data['stage_type'] ?? null) === WorkflowStageType::Payment->value
                    && array_key_exists('payment_source', $data)) {
                    $data['payment_source'] = WorkflowPaymentSource::resolve($data['payment_source'])->value;
                }

                if (array_key_exists('statistics_menu', $data)) {
                    if (($data['stage_type'] ?? null) !== WorkflowStageType::AwaitingResults->value
                        || blank($data['statistics_menu'])) {
                        unset($data['statistics_menu']);
                    } else {
                        Validator::make($data, [
                            'statistics_menu' => ['string', Rule::in(array_keys(StatisticsMenuOptions::options()))],
                        ])->validate();
                    }
                }

                if (array_key_exists('passed_result_menu', $data)) {
                    if (($data['stage_type'] ?? null) !== WorkflowStageType::Completed->value
                        || blank($data['passed_result_menu'])) {
                        unset($data['passed_result_menu']);
                    } else {
                        Validator::make($data, [
                            'passed_result_menu' => ['string', Rule::in(array_keys(PassedResultMenuOptions::options()))],
                        ])->validate();
                    }
                }

                foreach (['submission_messages', 'audience_messages'] as $key) {
                    if (is_array($data[$key] ?? null)) {
                        $data[$key] = self::normalizeStaffRecipients($data[$key]);
                    }
                }

                if (isset($data['submission_messages']['resubmission_staff'])) {
                    $settings = $data['submission_messages']['resubmission_staff'];
                    Validator::make(
                        ['submission_messages' => ['resubmission_staff' => $settings]],
                        WorkflowStageTypeSettings::rules('form_submission', ''),
                    )->validate();
                    if (filled($settings['title'] ?? null) || filled($settings['message'] ?? null)
                        || filled($settings['staff_recipient_roles'] ?? null)) {
                        $data['submission_messages']['resubmission_staff'] = self::normalizeStaffRecipients($settings);
                    } else {
                        unset($data['submission_messages']['resubmission_staff']);
                    }
                }

                foreach (['accept', 'send_back', 'reject'] as $action) {
                    if (is_array($data['review_actions'][$action] ?? null)) {
                        $data['review_actions'][$action] = self::normalizeStaffRecipients($data['review_actions'][$action]);
                        if (array_key_exists('alert_message', $data['review_actions'][$action])) {
                            $data['review_actions'][$action]['alert_message'] = self::normalizeAlertMessage($data['review_actions'][$action]['alert_message']);
                        }
                    }
                }

                if (is_array($data['result_statuses'] ?? null)
                    && array_key_exists('pending_alert_message', $data['result_statuses'])) {
                    $data['result_statuses']['pending_alert_message'] = self::normalizeAlertMessage($data['result_statuses']['pending_alert_message']);
                }

                if ($hasDataWrapper) {
                    $stage['data'] = $data;

                    return $stage;
                }

                return $data;
            })
            ->all();
    }

    private static function stageTypes(array $stages): array
    {
        $types = array_map(function (mixed $stage): ?string {
            $data = is_array($stage) && is_array($stage['data'] ?? null) ? $stage['data'] : $stage;

            if (! is_array($data)) {
                return '';
            }

            $type = $data['stage_type'] ?? null;

            return $type === null || (is_string($type) && trim($type) === '')
                ? null
                : (is_scalar($type) ? (string) $type : '');
        }, $stages);

        return array_values(array_filter($types, fn (?string $type): bool => $type !== null));
    }

    private static function hasCatalogSelection(array $stages): bool
    {
        foreach ($stages as $stage) {
            $data = is_array($stage) && is_array($stage['data'] ?? null) ? $stage['data'] : $stage;
            if (is_array($data) && filled($data['stage_type_id'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private static function normalizeAlertMessage(mixed $value): ?string
    {
        $value = is_scalar($value) ? trim((string) $value) : '';

        return $value === '' ? null : $value;
    }

    private static function normalizeStaffRecipients(array $messages): array
    {
        if (array_key_exists('staff_recipient_roles', $messages)) {
            $roles = $messages['staff_recipient_roles'];
            $messages['staff_recipient_roles'] = self::normalizeResponsibleRoles(is_array($roles) ? $roles : []);
            unset($messages['staff_recipient_ids']);
        }

        return $messages;
    }

    /** @return array<string> */
    public static function normalizeResponsibleRoles(mixed $roles): array
    {
        return collect(is_array($roles) ? $roles : [$roles])
            ->filter(fn (mixed $role): bool => is_string($role) && filled(trim($role)))
            ->map(fn (string $role): string => trim($role))
            ->unique()
            ->values()
            ->all();
    }

    public function forms(): BelongsToMany
    {
        return $this->belongsToMany(CustomForm::class, 'workflow_notification_forms', 'workflow_notification_id', 'custom_form_id')
            ->withTimestamps();
    }

    public function getStepsCountAttribute(): int
    {
        return count($this->stages ?? []);
    }

    public function hasStageType(WorkflowStageType|string $stageType): bool
    {
        $stageType = $stageType instanceof WorkflowStageType
            ? $stageType->value
            : (string) $stageType;

        return collect($this->stages ?? [])
            ->map(fn (mixed $stage): array => is_array($stage) && is_array($stage['data'] ?? null)
                ? $stage['data']
                : (is_array($stage) ? $stage : []))
            ->contains(fn (array $stage): bool => ($stage['stage_type'] ?? null) === $stageType);
    }

    /**
     * Return custom forms whose assigned workflow contains the given stage.
     *
     * Stage definitions are stored as JSON builder data, so this is resolved
     * through the model rather than relying on database-specific JSON syntax.
     */
    public static function formIdsWithStage(WorkflowStageType|string $stageType): array
    {
        $workflowIds = static::query()
            ->get(['id', 'stages'])
            ->filter(fn (self $workflow): bool => $workflow->hasStageType($stageType))
            ->pluck('id');

        if ($workflowIds->isEmpty()) {
            return [];
        }

        return DB::table('workflow_notification_forms')
            ->whereIn('workflow_notification_id', $workflowIds)
            ->pluck('custom_form_id')
            ->map(fn (int|string $id): int => (int) $id)
            ->all();
    }

    /**
     * Return forms that have any workflow assigned, including templates that
     * do not contain the stage currently being queried.
     */
    public static function assignedFormIds(): array
    {
        return DB::table('workflow_notification_forms')
            ->distinct()
            ->pluck('custom_form_id')
            ->map(fn (int|string $id): int => (int) $id)
            ->all();
    }

    /**
     * The template a form follows. A form belongs to at most one template.
     */
    public static function forForm(int|string $customFormId): ?self
    {
        return static::query()
            ->whereIn('id', DB::table('workflow_notification_forms')
                ->select('workflow_notification_id')
                ->where('custom_form_id', $customFormId))
            ->first();
    }

    /** Resolve an application's original configuration instead of the editable template. */
    public static function forEntry(CustomFormEntry $entry): ?self
    {
        return WorkflowEntrySnapshot::workflowFor($entry);
    }

    /** Read the configured source without changing payment processing or status. */
    public function paymentSource(): WorkflowPaymentSource
    {
        $stage = collect($this->stages ?? [])
            ->map(fn (mixed $item): array => is_array($item) && is_array($item['data'] ?? null)
                ? $item['data']
                : (is_array($item) ? $item : []))
            ->first(fn (array $data): bool => ($data['stage_type'] ?? null) === WorkflowStageType::Payment->value);

        return WorkflowPaymentSource::resolve($stage['payment_source'] ?? null);
    }

    public static function paymentSourceForForm(int|string $customFormId): WorkflowPaymentSource
    {
        return static::forForm($customFormId)?->paymentSource() ?? WorkflowPaymentSource::System;
    }

    /**
     * Point a form at one template, or at none when the id is empty.
     */
    public static function assignForm(int|string $customFormId, int|string|null $workflowNotificationId): void
    {
        DB::transaction(function () use ($customFormId, $workflowNotificationId): void {
            DB::table('workflow_notification_forms')->where('custom_form_id', $customFormId)->delete();

            if (filled($workflowNotificationId)) {
                static::query()->findOrFail($workflowNotificationId)->forms()->attach($customFormId);
            }
        });
    }
}
