<?php

namespace App\Filament\Widgets;

use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Schema;

class AdminSubmissionsByFormChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '330px';

    protected ?string $pollingInterval = '60s';

    protected bool $isCollapsible = true;

    public static function canView(): bool
    {
        return auth()->user()?->hasEffectiveRole('admin') ?? false;
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
        $forms = $this->sidebarForms();

        return [
            'datasets' => [
                [
                    'label' => __('dashboard.submissions'),
                    'data' => $forms
                        ->map(fn (CustomForm $form): int => $this->submissionCount($form))
                        ->values()
                        ->all(),
                    'backgroundColor' => 'rgba(16, 185, 129, 0.75)',
                    'borderColor' => '#10b981',
                    'borderWidth' => 1,
                    'borderRadius' => 8,
                ],
            ],
            'labels' => $forms
                ->map(fn (CustomForm $form): string => $this->localizedText($form->name))
                ->values()
                ->all(),
        ];
    }

    private function sidebarForms()
    {
        $query = CustomForm::query()
            ->where(function ($query): void {
                $query->whereNull('slug')
                    ->orWhere('slug', '!=', 'profile');
            });

        if (Schema::hasColumn('custom_forms', 'is_active')) {
            $query->where('is_active', true);
        }

        if (Schema::hasColumn('custom_forms', 'menu_placement')) {
            $query->where('menu_placement', 'sidebar');
        }

        if (Schema::hasColumn('custom_forms', 'display_order')) {
            $query->orderBy('display_order');
        }

        return $query
            ->orderBy('id')
            ->get();
    }

    private function submissionCount(CustomForm $form): int
    {
        $formIds = CustomForm::query()
            ->where('id', $form->id)
            ->orWhere('custom_form_id', $form->id)
            ->pluck('id')
            ->all();

        return CustomFormEntry::query()
            ->whereIn('custom_form_id', $formIds)
            ->where(function ($query): void {
                $query->whereNull('review_status')
                    ->orWhere('review_status', '!=', 'draft');
            })
            ->where(function ($query): void {
                $query->whereNull('data->registration_status')
                    ->orWhere('data->registration_status', '!=', 'draft');
            })
            ->count();
    }

    private function localizedText(mixed $value): string
    {
        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                $value = $decoded;
            }
        }

        if (is_object($value)) {
            $value = json_decode(json_encode($value), true);
        }

        if (is_array($value)) {
            $locale = app()->getLocale();

            return (string) (
                $value[$locale]
                ?? $value['km']
                ?? $value['kh']
                ?? $value['en']
                ?? collect($value)->first()
                ?? ''
            );
        }

        return (string) $value;
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
