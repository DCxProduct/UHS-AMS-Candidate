<?php

namespace App\Support;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

final class StatisticsPaymentEligibility
{
    /** Restrict an application query to the payment status shown in the payment list. */
    public static function apply(Builder $query): Builder
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
                return $query->whereRaw('1 = 0');
            }

            $payment->whereRaw('payments.users_id = COALESCE('.implode(', ', $ownerColumns).', NULL)');
        }

        return $query->where($payment, 'paid');
    }
}
