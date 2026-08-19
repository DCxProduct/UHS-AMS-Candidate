<?php

namespace App\Filament\Widgets;

use App\Filament\Admin\Resources\CandidatePaymentLists\CandidatePaymentListResource;
use App\Filament\Admin\Resources\CandidateRequested\CandidateRequestedResource;
use App\Filament\Admin\Resources\ClosingDates\ClosingDateResource;
use App\Filament\Admin\Resources\ExamResults\ExamResultResource;
use App\Filament\Admin\Resources\ExitExamResults\ExitExamResultResource;
use App\Filament\Admin\Resources\Payments\PaymentResource;
use App\Support\DashboardMetrics;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Support\Str;
use Throwable;

class AdminMenuOverview extends Widget
{
    protected const ALLOWED_RESOURCES = [
        CandidatePaymentListResource::class,
        PaymentResource::class,
        CandidateRequestedResource::class,
        ExamResultResource::class,
        ExitExamResultResource::class,
        ClosingDateResource::class,
    ];

    protected const SIDEBAR_ORDER = [
        CandidatePaymentListResource::class => 1,
        PaymentResource::class => 2,
        CandidateRequestedResource::class => 3,
        ExamResultResource::class => 4,
        ExitExamResultResource::class => 5,
        ClosingDateResource::class => 6,
    ];

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.admin-menu-overview';

    protected ?string $pollingInterval = '60s';

    public string $sortMode = 'default';

    public static function canView(): bool
    {
        return auth()->user()?->hasEffectiveRole(['admin', 'cashier']) ?? false;
    }

    protected function getViewData(): array
    {
        $items = $this->sortItems($this->buildDashboardItems());
        $isAdmin = auth()->user()?->hasEffectiveRole('admin') ?? false;

        $formattedItems = array_map(
            fn (array $item): array => [
                ...$item,
                'display_count' => number_format($item['count']),
            ],
            $items,
        );

        $data = [
            'highlights' => [
                [
                    'label' => __('dashboard.quick_access_modules'),
                    'value' => number_format(count($formattedItems)),
                ],
                [
                    'label' => __('dashboard.total_records'),
                    'value' => number_format(array_sum(array_column($items, 'count'))),
                ],
            ],
            'items' => $formattedItems,
        ];

        if ($isAdmin) {
            $data['highlights'][] = [
                'label' => __('dashboard.application_count'),
                'value' => number_format(DashboardMetrics::adminSubmittedApplicationsCount()),
            ];
        }

        return $data;
    }

    protected function buildDashboardItems(): array
    {
        $items = [];

        foreach (Filament::getResources() as $resourceClass) {
            if (! $this->shouldIncludeDashboardResource($resourceClass)) {
                continue;
            }

            $count = $this->resolveResourceCount($resourceClass);

            if ($count === null) {
                continue;
            }

            $label = $resourceClass === CustomFormEntryResource::class
                ? __('dashboard.form_entries')
                : $resourceClass::getNavigationLabel();

            $items[] = [
                'label' => $label,
                'count' => $count,
                'action_label' => __('dashboard.open_module', ['module' => $label]),
                'icon' => $resourceClass::getNavigationIcon() ?? 'heroicon-o-square-3-stack-3d',
                'url' => $resourceClass::getUrl('index'),
                'tone' => $this->toneForResource($resourceClass),
                'description' => $this->descriptionForResource($resourceClass, $label),
                'navigation_sort' => static::SIDEBAR_ORDER[$resourceClass] ?? 999,
            ];
        }

        return collect($items)
            ->sortBy([
                ['navigation_sort', 'asc'],
                ['label', 'asc'],
            ])
            ->values()
            ->all();
    }

    protected function sortItems(array $items): array
    {
        return match ($this->sortMode) {
            'highest' => collect($items)->sortByDesc('count')->values()->all(),
            'lowest' => collect($items)->sortBy('count')->values()->all(),
            'name' => collect($items)->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)->values()->all(),
            default => $items,
        };
    }

    protected function shouldIncludeDashboardResource(string $resourceClass): bool
    {
        if (! is_subclass_of($resourceClass, \Filament\Resources\Resource::class)) {
            return false;
        }

        if (! str_starts_with($resourceClass, 'App\\Filament\\Admin\\Resources\\')) {
            return false;
        }

        if (! $resourceClass::shouldRegisterNavigation() || ! $resourceClass::canAccess()) {
            return false;
        }

        return in_array($resourceClass, static::ALLOWED_RESOURCES, true);
    }

    protected function resolveResourceCount(string $resourceClass): ?int
    {
        try {
            if ($resourceClass === CustomFormEntryResource::class) {
                return $this->formEntriesCount();
            }

            return $resourceClass::getEloquentQuery()->count();
        } catch (Throwable) {
            return null;
        }
    }

    protected function toneForResource(string $resourceClass): string
    {
        return match ($resourceClass) {
            CandidateRequestedResource::class => 'sky',
            ExamResultResource::class => 'violet',
            CandidatePaymentListResource::class => 'lime',
            PaymentResource::class => 'teal',
            ExitExamResultResource::class => 'indigo',
            ClosingDateResource::class => 'slate',
            default => 'slate',
        };
    }

    protected function descriptionForResource(string $resourceClass, string $label): string
    {
        return match ($resourceClass) {
            CandidateRequestedResource::class => __('dashboard.candidate_lists_description'),
            ExamResultResource::class => __('dashboard.exam_results_description'),
            CandidatePaymentListResource::class => __('dashboard.payment_lists_description'),
            PaymentResource::class => __('dashboard.payment_records_description'),
            ExitExamResultResource::class => __('dashboard.exit_exam_results_description'),
            ClosingDateResource::class => __('dashboard.closing_dates_description'),
            default => __('dashboard.open_module_description', ['module' => Str::lower($label)]),
        };
    }
}
