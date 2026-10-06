<?php

namespace App\Models;

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

    public function forms(): BelongsToMany
    {
        return $this->belongsToMany(CustomForm::class, 'workflow_notification_forms', 'workflow_notification_id', 'custom_form_id')
            ->withTimestamps();
    }

    public function getStepsCountAttribute(): int
    {
        return count($this->stages ?? []);
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
