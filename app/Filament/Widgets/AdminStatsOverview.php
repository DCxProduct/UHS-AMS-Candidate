<?php

namespace App\Filament\Widgets;

use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

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
        $nationalExamFormId = CustomForm::query()
            ->where('slug', 'national-examination-registration')
            ->value('id');

        $totalSubmissions = $nationalExamFormId
            ? CustomFormEntry::query()
                ->where('custom_form_id', $nationalExamFormId)
                ->where(function ($query): void {
                    $query->whereNull('review_status')
                        ->orWhere('review_status', '!=', 'draft');
                })
                ->where(function ($query): void {
                    $query->whereNull('data->registration_status')
                        ->orWhere('data->registration_status', '!=', 'draft');
                })
                ->count()
            : 0;

        $reviewedSubmissions = $nationalExamFormId
            ? CustomFormEntry::query()
                ->where('custom_form_id', $nationalExamFormId)
                ->whereIn('review_status', ['accepted', 'approved', 'passed'])
                ->count()
            : 0;

        $returnedSubmissions = $nationalExamFormId
            ? CustomFormEntry::query()
                ->where('custom_form_id', $nationalExamFormId)
                ->whereIn('review_status', ['rejected', 'send_back', 'returned', 'failed'])
                ->count()
            : 0;

        $pendingReviews = $nationalExamFormId
            ? CustomFormEntry::query()
                ->where('custom_form_id', $nationalExamFormId)
                ->where(function ($query): void {
                    $query->whereNull('review_status')
                        ->orWhere('review_status', '')
                        ->orWhere('review_status', 'pending');
                })
                ->count()
            : 0;

        $totalForRatios = max($totalSubmissions, 1);
        $reviewedRate = (int) round(($reviewedSubmissions / $totalForRatios) * 100);
        $returnedRate = (int) round(($returnedSubmissions / $totalForRatios) * 100);
        $pendingRate = (int) round(($pendingReviews / $totalForRatios) * 100);

        return [
            Stat::make(__('dashboard.total_requests'), number_format($totalSubmissions))
                ->icon('heroicon-m-document-text')
                ->description(__('dashboard.total_requests_hint'))
                ->chart([
                    max($totalSubmissions - $pendingReviews, 0),
                    $reviewedSubmissions,
                    $returnedSubmissions,
                    $pendingReviews,
                ])
                ->color('info')
                ->extraAttributes([
                    'class' => 'uhs-admin-stat-card uhs-admin-stat-card--primary',
                ]),

            Stat::make(__('dashboard.reviewed_requests'), number_format($reviewedSubmissions))
                ->icon('heroicon-m-check-circle')
                ->description(__('dashboard.reviewed_requests_hint', ['percent' => $reviewedRate]))
                ->chart([
                    0,
                    max($reviewedSubmissions - 1, 0),
                    $reviewedSubmissions,
                    $reviewedSubmissions,
                ])
                ->color('success')
                ->extraAttributes([
                    'class' => 'uhs-admin-stat-card uhs-admin-stat-card--success',
                ]),

            Stat::make(__('dashboard.returned_requests'), number_format($returnedSubmissions))
                ->icon('heroicon-m-arrow-uturn-left')
                ->description(__('dashboard.returned_requests_hint', ['percent' => $returnedRate]))
                ->chart([
                    0,
                    $returnedSubmissions,
                    max($returnedSubmissions - 1, 0),
                    $returnedSubmissions,
                ])
                ->color('danger')
                ->extraAttributes([
                    'class' => 'uhs-admin-stat-card uhs-admin-stat-card--danger',
                ]),

            Stat::make(__('dashboard.in_review_requests'), number_format($pendingReviews))
                ->icon('heroicon-m-clock')
                ->description(__('dashboard.in_review_requests_hint', ['percent' => $pendingRate]))
                ->chart([
                    max($pendingReviews - 2, 0),
                    max($pendingReviews - 1, 0),
                    $pendingReviews,
                    $pendingReviews,
                ])
                ->color('warning')
                ->extraAttributes([
                    'class' => 'uhs-admin-stat-card uhs-admin-stat-card--warning',
                ]),
        ];
    }
}
