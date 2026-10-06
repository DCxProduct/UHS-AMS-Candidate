<?php

namespace App\Enums;

enum WorkflowStageType: string
{
    case FormSubmission = 'form_submission';
    case Review = 'review';
    case Payment = 'payment';
    case AwaitingResults = 'awaiting_results';
    case Completed = 'completed';

    /**
     * Kept for existing workflow templates, but no longer offered for new stages.
     */
    case Approval = 'approval';

    public function label(): string
    {
        return __('workflow_notifications.stage_types.'.$this->value);
    }

    /**
     * Every stage after the candidate's own submission is handled by staff,
     * so it names the role responsible for it.
     */
    public function requiresRole(): bool
    {
        return $this !== self::FormSubmission;
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->reject(fn (self $type): bool => $type === self::Approval)
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
