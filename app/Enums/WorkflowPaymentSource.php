<?php

namespace App\Enums;

enum WorkflowPaymentSource: string
{
    case System = 'system';
    case Ams = 'ams';

    public function label(): string
    {
        return __('workflow_notifications.payment_sources.'.$this->value);
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $source): array => [$source->value => $source->label()])
            ->all();
    }

    /** Older workflows continue using the existing in-system payment flow. */
    public static function resolve(mixed $value): self
    {
        return is_string($value) ? (self::tryFrom($value) ?? self::System) : self::System;
    }
}
