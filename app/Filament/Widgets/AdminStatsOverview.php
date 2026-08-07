<?php

namespace App\Filament\Widgets;

use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class AdminStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return auth()->user()?->hasEffectiveRole('admin') ?? false;
    }

    protected function getStats(): array
    {
        $entriesQuery = CustomFormEntry::query()
            ->whereHas('customForm', function (Builder $query): void {
                $query
                    ->where('slug', '!=', 'profile')
                    ->where(function (Builder $query): void {
                        $query->where(function (Builder $query): void {
                            $query->where('menu_placement', 'sidebar')
                                ->where('is_active', true);
                        })->orWhere(function (Builder $query): void {
                            $query->where('menu_placement', 'sub_item')
                                ->where('is_active', true)
                                ->whereHas('parentForm', function (Builder $query): void {
                                    $query->where('menu_placement', 'sidebar')
                                        ->where('is_active', true)
                                        ->where('slug', '!=', 'profile');
                                });
                        });
                    });
            });

        $totalSubmissions = (clone $entriesQuery)
            ->where(function ($query): void {
                $query->whereNull('review_status')
                    ->orWhere('review_status', '!=', 'draft');
            })
            ->where(function ($query): void {
                $query->whereNull('data->registration_status')
                    ->orWhere('data->registration_status', '!=', 'draft');
            })
            ->count();

        $reviewedSubmissions = (clone $entriesQuery)
            ->whereIn('review_status', ['accepted', 'approved', 'passed'])
            ->count();

        $returnedSubmissions = (clone $entriesQuery)
            ->whereIn('review_status', ['rejected', 'send_back', 'returned', 'failed'])
            ->count();

        $pendingReviews = (clone $entriesQuery)
            ->where(function ($query): void {
                $query->whereNull('review_status')
                    ->orWhere('review_status', '')
                    ->orWhere('review_status', 'pending');
            })
            ->count();

        return [
            Stat::make(__('dashboard.total_requests'), number_format($totalSubmissions))
                ->icon('heroicon-m-document-text')
                ->description(__('dashboard.total_requests_hint'))
                ->color('info')
                ->extraAttributes([
                    'class' => 'uhs-admin-stat-card uhs-admin-stat-card--primary',
                ]),

            Stat::make(__('dashboard.reviewed_requests'), number_format($reviewedSubmissions))
                ->icon('heroicon-m-check-circle')
                ->description(__('dashboard.reviewed_requests_hint'))
                ->color('success')
                ->extraAttributes([
                    'class' => 'uhs-admin-stat-card uhs-admin-stat-card--success',
                ]),

            Stat::make(__('dashboard.returned_requests'), number_format($returnedSubmissions))
                ->icon('heroicon-m-arrow-uturn-left')
                ->description(__('dashboard.returned_requests_hint'))
                ->color('danger')
                ->extraAttributes([
                    'class' => 'uhs-admin-stat-card uhs-admin-stat-card--danger',
                ]),

            Stat::make(__('dashboard.in_review_requests'), number_format($pendingReviews))
                ->icon('heroicon-m-clock')
                ->description(__('dashboard.in_review_requests_hint'))
                ->color('warning')
                ->extraAttributes([
                    'class' => 'uhs-admin-stat-card uhs-admin-stat-card--warning',
                ]),
        ];
    }
}
