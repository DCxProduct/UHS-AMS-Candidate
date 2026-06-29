<?php

namespace App\Filament\Widgets;

use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Widgets\ChartWidget;

class AdminSubmissionsByFormChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '330px';

    protected ?string $pollingInterval = '60s';

    protected bool $isCollapsible = true;

    public static function canView(): bool
    {
        return auth()->user()?->registration_type === 'admin';
    }

    public function getHeading(): ?string
    {
        return __('dashboard.national_examination_submissions');
    }

    public function getDescription(): ?string
    {
        return __('dashboard.national_examination_submissions_description');
    }

    protected function getData(): array
    {
        $nationalExamFormId = CustomForm::query()
            ->where('slug', 'national-examination-registration')
            ->value('id');

        $labels = [
            __('dashboard.form_types.associate'),
            __('dashboard.form_types.bachelor'),
            __('dashboard.form_types.master'),
            __('dashboard.form_types.phd'),
        ];
        $keys = ['associate', 'bachelor', 'master', 'phd'];

        $data = collect($keys)->map(function (string $key) use ($nationalExamFormId): int {
            if (! $nationalExamFormId) {
                return 0;
            }

            return CustomFormEntry::query()
                ->where('custom_form_id', $nationalExamFormId)
                ->where('review_status', '!=', 'draft')
                ->where('data->registration_status', '!=', 'draft')
                ->where('data->form_selection', $key)
                ->count();
        })->values()->all();

        return [
            'datasets' => [
                [
                    'label' => __('dashboard.submissions'),
                    'data' => $data,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.75)',
                    'borderColor' => '#10b981',
                    'borderWidth' => 1,
                    'borderRadius' => 8,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
