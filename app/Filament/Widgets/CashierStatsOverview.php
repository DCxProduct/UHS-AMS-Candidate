<?php

namespace App\Filament\Widgets;

use App\Filament\Admin\Resources\CandidatePaymentLists\CandidatePaymentListResource;
use App\Filament\Admin\Resources\Payments\PaymentResource;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CashierStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return auth()->user()?->hasEffectiveRole(['admin', 'cashier']) ?? false;
    }

    protected function getStats(): array
    {
        $paymentLists = CandidatePaymentListResource::getEloquentQuery()->count();
        $paymentRecords = PaymentResource::getEloquentQuery()->count();
        $paidRecords = PaymentResource::getEloquentQuery()
            ->whereRaw('LOWER(COALESCE(status_payt, \'\')) = ?', ['paid'])
            ->count();
        $unpaidApplications = CandidatePaymentListResource::getEloquentQuery()->count();

        return [
            Stat::make(__('dashboard.payment_lists'), number_format($paymentLists))
                ->icon('heroicon-m-currency-dollar')
                ->description(__('dashboard.cashier_payment_lists_hint'))
                ->color('info')
                ->extraAttributes([
                    'class' => 'uhs-admin-stat-card uhs-admin-stat-card--primary',
                ]),

            Stat::make(__('dashboard.payment_records'), number_format($paymentRecords))
                ->icon('heroicon-m-banknotes')
                ->description(__('dashboard.cashier_payment_records_hint'))
                ->color('success')
                ->extraAttributes([
                    'class' => 'uhs-admin-stat-card uhs-admin-stat-card--success',
                ]),

            Stat::make(__('dashboard.paid_records'), number_format($paidRecords))
                ->icon('heroicon-m-check-badge')
                ->description(__('dashboard.cashier_paid_records_hint'))
                ->color('success')
                ->extraAttributes([
                    'class' => 'uhs-admin-stat-card uhs-admin-stat-card--success',
                ]),

            Stat::make(__('dashboard.unpaid_applications'), number_format($unpaidApplications))
                ->icon('heroicon-m-clock')
                ->description(__('dashboard.cashier_unpaid_applications_hint'))
                ->color('warning')
                ->extraAttributes([
                    'class' => 'uhs-admin-stat-card uhs-admin-stat-card--warning',
                ]),
        ];
    }
}
