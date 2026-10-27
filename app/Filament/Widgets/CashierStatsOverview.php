<?php

namespace App\Filament\Widgets;

use App\Filament\Admin\Resources\PaymentStatusLists\PaymentStatusListResource;
use App\Filament\Admin\Resources\Payments\PaymentResource;
use App\Support\CustomFormRoleAccess;
use App\Support\StaffRoles;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

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
        $paymentLists = PaymentStatusListResource::getEloquentQuery()->count();
        $paidRecords = PaymentResource::getEloquentQuery()
            ->whereRaw('LOWER(COALESCE(status_payt, \'\')) = ?', ['paid'])
            ->count();
        $unpaidApplications = PaymentStatusListResource::getUnpaidEloquentQuery()->count();

        $stats = [
            Stat::make(__('dashboard.payment_status_lists'), number_format($paymentLists))
                ->icon('heroicon-m-currency-dollar')
                ->description(__('dashboard.cashier_payment_status_lists_hint'))
                ->color('info')
                ->extraAttributes([
                    'class' => 'uhs-admin-stat-card uhs-admin-stat-card--primary',
                ]),

            Stat::make(__('dashboard.paid_records'), number_format($paidRecords))
                ->icon('heroicon-m-check-badge')
                ->description(__('dashboard.cashier_paid_records_hint'))
                ->color('success')
                ->extraAttributes([
                    'class' => 'uhs-admin-stat-card uhs-admin-stat-card--success',
                ]),

            Stat::make(__('dashboard.unpaid_statuses'), number_format($unpaidApplications))
                ->icon('heroicon-m-clock')
                ->description(__('dashboard.cashier_unpaid_statuses_hint'))
                ->color('warning')
                ->extraAttributes([
                    'class' => 'uhs-admin-stat-card uhs-admin-stat-card--warning',
                ]),
        ];

        if (auth()->user()?->hasEffectiveRole(['cashier_manager', 'cashier_officer'])) {
            $allowedFormIds = CustomFormRoleAccess::allowedFormIds(auth()->user());
            $reviewedUnpaidApplications = PaymentStatusListResource::getUnpaidEloquentQuery()
                ->whereIn('review_status', ['accepted', 'approved', 'passed'])
                ->when($allowedFormIds !== null, fn (Builder $query): Builder => $query->whereIn('custom_form_id', $allowedFormIds))
                ->count();

            array_unshift($stats, Stat::make(__('dashboard.cashier_reviewed_applicants'), number_format($reviewedUnpaidApplications))
                ->icon('heroicon-m-check-circle')
                ->description(__('dashboard.cashier_reviewed_unpaid_hint'))
                ->color('success')
                ->extraAttributes([
                    'class' => 'uhs-admin-stat-card uhs-admin-stat-card--success',
                ]));
        }

        return $stats;
    }
}
