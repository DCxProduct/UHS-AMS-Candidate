<?php

namespace App\Support;

use App\Enums\WorkflowStageType;
use App\Models\Payment;
use App\Models\WorkflowNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

final class StatisticsPaymentEligibility
{
    /** Ordered workflows enter statistics when their results stage becomes active. */
    public static function apply(Builder $query): Builder
    {
        return $query->where(function (Builder $eligible): void {
            $eligible->where(function (Builder $ordered): void {
                WorkflowProgressQuery::ordered($ordered)
                    ->where(function (Builder $progress): void {
                        $progress->where('data->workflow_progress->current_type', WorkflowStageType::AwaitingResults->value)
                            ->orWhere('data->workflow_progress->result_passed', true);
                    });
            })->orWhere(function (Builder $legacy): void {
                WorkflowProgressQuery::legacy($legacy);
                self::applyLegacy($legacy);
            });
        });
    }

    private static function applyLegacy(Builder $query): Builder
    {
        $payment = Payment::query()
            ->selectRaw("LOWER(COALESCE(status_payt, ''))")
            ->whereColumn('payments.form_id', 'custom_form_entries.custom_form_id')
            ->latest('id')
            ->limit(1);

        if (Schema::hasColumn('payments', 'custom_form_entry_id')) {
            $payment->whereColumn('payments.custom_form_entry_id', 'custom_form_entries.id');
        } else {
            $ownerColumns = collect(['created_by', 'user_id', 'created_by_id'])
                ->filter(fn (string $column): bool => Schema::hasColumn('custom_form_entries', $column))
                ->map(fn (string $column): string => $payment->getGrammar()->wrap('custom_form_entries.'.$column))
                ->all();

            if ($ownerColumns === []) {
                $payment = null;
            } else {
                $payment->whereRaw('payments.users_id = COALESCE('.implode(', ', $ownerColumns).', NULL)');
            }
        }

        return $query->where(function (Builder $eligible) use ($payment): void {
            if ($payment !== null) {
                $eligible->where($payment, 'paid');
            } else {
                $eligible->whereRaw('1 = 0');
            }

            $eligible->orWhere(function (Builder $reviewed): void {
                $reviewed->whereIn('custom_form_entries.review_status', ['approved', 'accepted', 'passed'])
                    ->where(fn (Builder $workflow): Builder => self::withoutPayment($workflow));
            });
        });
    }

    private static function withoutPayment(Builder $query): Builder
    {
        if (! Schema::hasColumn('custom_form_entries', 'workflow_snapshot')) {
            return self::withoutPaymentInCurrentConfiguration($query);
        }

        return $query->where(function (Builder $frozen): void {
            $frozen->where('workflow_snapshot->has_workflow', true)
                ->where('workflow_snapshot->requires_payment', false);
        })->orWhere(function (Builder $static): void {
            $static->where('workflow_snapshot->has_workflow', false);
            self::withoutStaticPayment($static);
        })->orWhere(function (Builder $legacy): void {
            $legacy->whereNull('workflow_snapshot')
                ->where(fn (Builder $current): Builder => self::withoutPaymentInCurrentConfiguration($current));
        });
    }

    private static function withoutPaymentInCurrentConfiguration(Builder $query): Builder
    {
        $assignedFormIds = [];
        $paymentFormIds = [];
        if (Schema::hasTable('workflow_notifications') && Schema::hasTable('workflow_notification_forms')) {
            $assignedFormIds = WorkflowNotification::assignedFormIds();
            $paymentFormIds = WorkflowNotification::formIdsWithStage(WorkflowStageType::Payment);
        }

        return $query->whereIn('custom_form_id', array_values(array_diff($assignedFormIds, $paymentFormIds)))
            ->orWhere(function (Builder $static) use ($assignedFormIds): void {
                $static->whereNotIn('custom_form_id', $assignedFormIds);
                self::withoutStaticPayment($static);
            });
    }

    private static function withoutStaticPayment(Builder $query): Builder
    {
        return $query->whereHas('customForm', fn (Builder $form): Builder => $form
            ->where(fn (Builder $setting): Builder => $setting->where('requires_payment', false)->orWhereNull('requires_payment')));
    }
}
