<?php

namespace App\Support;

use App\Filament\Admin\Resources\Payments\PaymentResource;
use App\Filament\Admin\Resources\PaymentStatusLists\PaymentStatusListResource;

final class CashierDashboardTotals
{
    public static function get(): array
    {
        $paid = PaymentResource::getEloquentQuery()
            ->whereRaw('LOWER(COALESCE(payments.status_payt, \'\')) = ?', ['paid']);

        return [
            'applications' => PaymentStatusListResource::getEloquentQuery()->count(),
            'paid' => $paid->count(),
            'unpaid' => PaymentStatusListResource::getUnpaidEloquentQuery()->count(),
        ];
    }
}
