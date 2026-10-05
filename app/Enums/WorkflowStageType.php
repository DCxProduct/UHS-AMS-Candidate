<?php

namespace App\Enums;

enum WorkflowStageType: string
{
    case FormSubmission = 'form_submission';
    case Review = 'review';
    case Approval = 'approval';
    case Payment = 'payment';

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
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
