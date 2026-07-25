<?php

namespace App\Filament\Widgets;

use App\Filament\Admin\Resources\ClosingDates\ClosingDateResource;
use App\Filament\Admin\Resources\ExamResults\ExamResultResource;
use App\Filament\Admin\Resources\ReviewApplications\ReviewApplicationResource;
use App\Filament\Admin\Resources\SystemUsers\SystemUserResource;
use App\Models\ClosingDate;
use App\Models\SystemUser;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomForms\CustomFormResource;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate;
use Chanthoeun\FilamentDocumentBuilder\Resources\DocumentTemplateResource;
use Filament\Widgets\Widget;

class AdminMenuOverview extends Widget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.admin-menu-overview';

    protected ?string $pollingInterval = '60s';

    public static function canView(): bool
    {
        return auth()->user()?->registration_type === 'admin';
    }

    protected function getViewData(): array
    {
        $items = [
            [
                'label' => __('dashboard.candidate_lists'),
                'count' => ReviewApplicationResource::getEloquentQuery()->count(),
                'action_label' => __('dashboard.open_candidate_lists'),
                'icon' => 'heroicon-o-clipboard-document-check',
                'url' => ReviewApplicationResource::getUrl('index'),
                'tone' => 'sky',
                'description' => __('dashboard.candidate_lists_description'),
            ],
            [
                'label' => __('dashboard.exam_results'),
                'count' => ExamResultResource::getEloquentQuery()->count(),
                'action_label' => __('dashboard.open_exam_results'),
                'icon' => 'heroicon-o-academic-cap',
                'url' => ExamResultResource::getUrl('index'),
                'tone' => 'violet',
                'description' => __('dashboard.exam_results_description'),
            ],
            [
                'label' => __('dashboard.form_entries'),
                'count' => CustomFormEntry::query()->count(),
                'action_label' => __('dashboard.open_form_entries'),
                'icon' => 'heroicon-o-clipboard-document-list',
                'url' => CustomFormEntryResource::getUrl('index'),
                'tone' => 'emerald',
                'description' => __('dashboard.form_entries_description'),
            ],
            [
                'label' => __('dashboard.custom_forms'),
                'count' => CustomForm::query()->count(),
                'action_label' => __('dashboard.manage_custom_forms'),
                'icon' => 'heroicon-o-document-duplicate',
                'url' => CustomFormResource::getUrl('index'),
                'tone' => 'amber',
                'description' => __('dashboard.custom_forms_description'),
            ],
            [
                'label' => __('dashboard.document_templates'),
                'count' => DocumentTemplate::query()->count(),
                'action_label' => __('dashboard.manage_document_templates'),
                'icon' => 'heroicon-o-document-text',
                'url' => DocumentTemplateResource::getUrl('index'),
                'tone' => 'rose',
                'description' => __('dashboard.document_templates_description'),
            ],
            [
                'label' => __('dashboard.closing_dates'),
                'count' => ClosingDate::query()->count(),
                'action_label' => __('dashboard.manage_closing_dates'),
                'icon' => 'heroicon-o-calendar-days',
                'url' => ClosingDateResource::getUrl('index'),
                'tone' => 'indigo',
                'description' => __('dashboard.closing_dates_description'),
            ],
            [
                'label' => __('dashboard.system_users'),
                'count' => SystemUser::query()->count(),
                'action_label' => __('dashboard.manage_system_users'),
                'icon' => 'heroicon-o-user-group',
                'url' => SystemUserResource::getUrl('index'),
                'tone' => 'slate',
                'description' => __('dashboard.system_users_description'),
            ],
        ];

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
}
