<?php

namespace App\Filament\Widgets;

use App\Support\DashboardUserAccess;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class CandidateStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return DashboardUserAccess::isCandidate(auth()->user());
    }

    protected function getStats(): array
    {
        $entriesQuery = $this->candidateEntriesQuery();

        $totalRequests = (clone $entriesQuery)
            ->where(function (Builder $query): void {
                $query->whereNull('review_status')
                    ->orWhere('review_status', '!=', 'draft');
            })
            ->where(function (Builder $query): void {
                $query->whereNull('data->registration_status')
                    ->orWhere('data->registration_status', '!=', 'draft');
            })
            ->count();

        $reviewedRequests = (clone $entriesQuery)
            ->whereIn('review_status', ['accepted', 'approved', 'passed'])
            ->count();

        $returnedRequests = (clone $entriesQuery)
            ->whereIn('review_status', ['rejected', 'send_back', 'returned', 'failed'])
            ->count();

        $requestsInReview = (clone $entriesQuery)
            ->where(function (Builder $query): void {
                $query->whereNull('review_status')
                    ->orWhere('review_status', '')
                    ->orWhere('review_status', 'pending');
            })
            ->count();

        return [
            Stat::make(__('dashboard.total_requests'), number_format($totalRequests))
                ->icon('heroicon-m-document-text')
                ->description(__('dashboard.total_requests_hint'))
                ->color('info')
                ->extraAttributes([
                    'class' => 'uhs-admin-stat-card uhs-admin-stat-card--primary',
                ]),

            Stat::make(__('dashboard.reviewed_requests'), number_format($reviewedRequests))
                ->icon('heroicon-m-check-circle')
                ->description(__('dashboard.reviewed_requests_hint'))
                ->color('success')
                ->extraAttributes([
                    'class' => 'uhs-admin-stat-card uhs-admin-stat-card--success',
                ]),

            Stat::make(__('dashboard.returned_requests'), number_format($returnedRequests))
                ->icon('heroicon-m-arrow-uturn-left')
                ->description(__('dashboard.returned_requests_hint'))
                ->color('danger')
                ->extraAttributes([
                    'class' => 'uhs-admin-stat-card uhs-admin-stat-card--danger',
                ]),

            Stat::make(__('dashboard.in_review_requests'), number_format($requestsInReview))
                ->icon('heroicon-m-clock')
                ->description(__('dashboard.in_review_requests_hint'))
                ->color('warning')
                ->extraAttributes([
                    'class' => 'uhs-admin-stat-card uhs-admin-stat-card--warning',
                ]),
        ];
    }

    private function candidateEntriesQuery(): Builder
    {
        $userId = (int) auth()->id();
        $ownerColumns = collect([
            'created_by',
            'user_id',
            'created_by_id',
            'system_user_id',
        ])
            ->filter(fn (string $column): bool => Schema::hasColumn('custom_form_entries', $column))
            ->values()
            ->all();

        return CustomFormEntry::query()
            ->whereHas('customForm', function (Builder $query): void {
                $query->where('slug', '!=', 'profile');
            })
            ->where(function (Builder $query) use ($ownerColumns, $userId): void {
                foreach ($ownerColumns as $column) {
                    $query->orWhere($column, $userId);
                }
            });
    }

    protected function getColumns(): int
    {
        return 4;
    }
}
