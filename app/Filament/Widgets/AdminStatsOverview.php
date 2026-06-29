<?php

namespace App\Filament\Widgets;

use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return auth()->user()?->registration_type === 'admin';
    }

    protected function getStats(): array
    {
        $nationalExamFormId = CustomForm::query()
            ->where('slug', 'national-examination-registration')
            ->value('id');

        $totalStudents = DB::table('users')
            ->where('registration_type', 'student')
            ->count();

        $activeStudents = DB::table('users')
            ->where('registration_type', 'student')
            ->where(function ($query): void {
                if (Schema::hasColumn('users', 'is_active')) {
                    $query->where('is_active', true);
                }
            })
            ->count();

        if (! Schema::hasColumn('users', 'is_active')) {
            $activeStudents = $totalStudents;
        }

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

        $pendingReviews = $nationalExamFormId
            ? CustomFormEntry::query()
                ->where('custom_form_id', $nationalExamFormId)
                ->where('review_status', 'pending')
                ->count()
            : 0;

        return [
            Stat::make(__('dashboard.total_students'), number_format($totalStudents))
                ->icon('heroicon-m-user-group')
                ->color('info'),

            Stat::make(__('dashboard.active_students'), number_format($activeStudents))
                ->icon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make(__('dashboard.national_examination_requests'), number_format($totalSubmissions))
                ->icon('heroicon-m-document-text')
                ->color('primary'),

            Stat::make(__('dashboard.pending_reviews'), number_format($pendingReviews))
                ->icon('heroicon-m-clock')
                ->color('warning'),
        ];
    }
}
