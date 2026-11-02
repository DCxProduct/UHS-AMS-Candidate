<?php

namespace App\Filament\Widgets;

use App\Support\CashierDashboardTotals;
use App\Support\StaffRoles;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CashierStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return auth()->user()?->hasEffectiveRole(['admin', ...StaffRoles::CASHIERS]) ?? false;
    }

    protected function getStats(): array
    {
        $totals = CashierDashboardTotals::get();

        $stats = [
            Stat::make(__('dashboard.payment_status_lists'), number_format($totals['applications']))
                ->icon('heroicon-m-currency-dollar')
                ->description(__('dashboard.cashier_payment_status_lists_hint'))
                ->color('info')
                ->extraAttributes([
                    'class' => 'uhs-admin-stat-card uhs-admin-stat-card--primary',
                ]),

            Stat::make(__('dashboard.paid_records'), number_format($totals['paid']))
                ->icon('heroicon-m-check-badge')
                ->description(__('dashboard.cashier_paid_records_hint'))
                ->color('success')
                ->extraAttributes([
                    'class' => 'uhs-admin-stat-card uhs-admin-stat-card--success',
                ]),

            Stat::make(__('dashboard.unpaid_statuses'), number_format($totals['unpaid']))
                ->icon('heroicon-m-clock')
                ->description(__('dashboard.cashier_unpaid_statuses_hint'))
                ->color('warning')
                ->extraAttributes([
                    'class' => 'uhs-admin-stat-card uhs-admin-stat-card--warning',
                ]),
        ];

        return $stats;
    }
}
