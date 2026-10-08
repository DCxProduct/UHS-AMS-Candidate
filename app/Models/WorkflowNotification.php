<?php

namespace App\Models;

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

                if ($hasDataWrapper) {
                    $stage['data'] = $data;

                    return $stage;
                }

                return $data;
            })
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
