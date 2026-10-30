<?php

namespace App\Models;

use App\Enums\WorkflowPaymentSource;
use App\Enums\WorkflowStageType;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

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
            $workflow->stages = self::normalizeStages($workflow->stages);
        });
    }

    /**
     * Normalize the builder wrapper without merging status and notification
     * messages. They are separate candidate-facing outputs.
     */
    public static function normalizeStages(?array $stages): array
    {
        return collect($stages ?? [])
            ->map(function (mixed $stage): mixed {
                if (! is_array($stage)) {
                    return $stage;
                }

                $hasDataWrapper = is_array($stage['data'] ?? null);
                $data = $hasDataWrapper ? $stage['data'] : $stage;

                if (array_key_exists('responsible_role', $data)) {
                    $roles = self::normalizeResponsibleRoles($data['responsible_role']);
                    $data['responsible_role'] = $roles === [] ? null : $roles;
                }

                if (($data['stage_type'] ?? null) === WorkflowStageType::Payment->value
                    && array_key_exists('payment_source', $data)) {
                    $data['payment_source'] = WorkflowPaymentSource::resolve($data['payment_source'])->value;
                }

                foreach (['submission_messages', 'audience_messages'] as $key) {
                    if (is_array($data[$key] ?? null)) {
                        $data[$key] = self::normalizeStaffRecipients($data[$key]);
                    }
                }

                foreach (['accept', 'send_back', 'reject'] as $action) {
                    if (is_array($data['review_actions'][$action] ?? null)) {
                        $data['review_actions'][$action] = self::normalizeStaffRecipients($data['review_actions'][$action]);
                    }
                }

                if ($hasDataWrapper) {
                    $stage['data'] = $data;

                    return $stage;
                }

                return $data;
            })
            ->all();
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
    public static function forEntry(\Chanthoeun\FilamentCustomForms\Models\CustomFormEntry $entry): ?self
    {
        return \App\Support\WorkflowEntrySnapshot::workflowFor($entry);
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
