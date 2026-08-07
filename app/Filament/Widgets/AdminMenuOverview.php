<?php

namespace App\Filament\Widgets;

use App\Filament\Admin\Resources\AuditLogs\AuditLogResource;
use App\Filament\Admin\Resources\CandidatePaymentLists\CandidatePaymentListResource;
use App\Filament\Admin\Resources\ExamResults\ExamResultResource;
use App\Filament\Admin\Resources\Payments\PaymentResource;
use App\Filament\Admin\Resources\ReviewApplications\ReviewApplicationResource;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class AdminMenuOverview extends Widget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.admin-menu-overview';

    protected ?string $pollingInterval = '60s';

    public string $sortMode = 'default';

    public static function canView(): bool
    {
        return auth()->user()?->hasEffectiveRole('admin') ?? false;
    }

    protected function getViewData(): array
    {
        $items = $this->sortItems($this->buildDashboardItems());

        $formattedItems = array_map(
            fn (array $item): array => [
                ...$item,
                'display_count' => number_format($item['count']),
            ],
            $items,
        );

        return [
            'highlights' => [
                [
                    'label' => __('dashboard.quick_access_modules'),
                    'value' => number_format(count($formattedItems)),
                ],
                [
                    'label' => __('dashboard.total_records'),
                    'value' => number_format(array_sum(array_column($items, 'count'))),
                ],
                [
                    'label' => __('dashboard.active_sections'),
                    'value' => number_format(count(array_filter($items, fn (array $item): bool => $item['count'] > 0))),
                ],
            ],
            'items' => $formattedItems,
        ];
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
                'icon' => (string) ($resourceClass::getNavigationIcon() ?? 'heroicon-o-square-3-stack-3d'),
                'url' => $resourceClass::getUrl('index'),
                'tone' => $this->toneForResource($resourceClass),
                'description' => $this->descriptionForResource($resourceClass, $label),
                'navigation_sort' => $resourceClass::getNavigationSort() ?? 999,
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

        if (
            ! str_starts_with($resourceClass, 'App\\Filament\\Admin\\Resources\\')
            && $resourceClass !== CustomFormEntryResource::class
        ) {
            return false;
        }

        if (! $resourceClass::shouldRegisterNavigation() || ! $resourceClass::canAccess()) {
            return false;
        }

        if ($resourceClass === CustomFormEntryResource::class) {
            return true;
        }

        $group = (string) $resourceClass::getNavigationGroup();

        return in_array($group, [
            __('navigation.groups.candidates'),
            __('navigation.groups.cashier'),
            __('audit_logs.activity_navigation_label'),
        ], true);
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
            ReviewApplicationResource::class => 'sky',
            ExamResultResource::class => 'violet',
            CandidatePaymentListResource::class => 'lime',
            PaymentResource::class => 'teal',
            CustomFormEntryResource::class => 'emerald',
            AuditLogResource::class => 'zinc',
            default => 'slate',
        };
    }

    protected function descriptionForResource(string $resourceClass, string $label): string
    {
        return match ($resourceClass) {
            ReviewApplicationResource::class => __('dashboard.candidate_lists_description'),
            ExamResultResource::class => __('dashboard.exam_results_description'),
            CandidatePaymentListResource::class => __('dashboard.payment_lists_description'),
            PaymentResource::class => __('dashboard.payment_records_description'),
            CustomFormEntryResource::class => __('dashboard.form_entries_description'),
            AuditLogResource::class => __('dashboard.activity_logs_description'),
            default => __('dashboard.open_module_description', ['module' => Str::lower($label)]),
        };
    }

    protected function formEntriesCount(): int
    {
        $ownerColumns = collect(['created_by', 'user_id', 'created_by_id'])
            ->filter(fn (string $column): bool => Schema::hasColumn('custom_form_entries', $column))
            ->values()
            ->all();

        $entries = CustomFormEntry::query()
            ->whereHas('customForm', function (Builder $query): void {
                $query->where('slug', '!=', 'profile');
            })
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('review_status')
                    ->orWhere('review_status', '!=', 'draft');
            })
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('data->registration_status')
                    ->orWhere('data->registration_status', '!=', 'draft');
            })
            ->get([
                'id',
                'custom_form_id',
                ...$ownerColumns,
                'data',
            ]);

        return $entries
            ->unique(function (CustomFormEntry $entry) use ($ownerColumns): string {
                $ownerId = collect($ownerColumns)
                    ->map(fn (string $column): mixed => data_get($entry, $column))
                    ->first(fn (mixed $value): bool => filled($value)) ?? 0;

                $formSelection = strtolower((string) data_get($entry->data, 'form_selection', ''));

                return implode(':', [
                    (string) $entry->custom_form_id,
                    (string) $ownerId,
                    $formSelection,
                ]);
            })
            ->count();
    }
}
