<?php

namespace App\Filament\Admin\Resources\ExamResults\Tables;

use App\Filament\Admin\Resources\ReviewApplications\Tables\ReviewApplicationsTable;
use Carbon\Carbon;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ExamResultsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordAction(null)
            ->recordUrl(null)
            ->selectable()
            ->columns([
                TextColumn::make('row_number')
                    ->label(__('exam_results.no'))
                    ->rowIndex()
                    ->alignCenter(),

                TextColumn::make('form_type')
                    ->label(__('review_applications.form_type'))
                    ->getStateUsing(fn (CustomFormEntry $record): string => self::recordFormTypeLabel($record))
                    ->badge()
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('academic_year')
                    ->label(__('exam_results.academic_year'))
                    ->getStateUsing(fn ($record): string => self::entryValue($record, 'academic_year', $record->creator?->academic_year))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('data->academic_year', 'like', "%{$search}%"))
                    ->sortable(),

                TextColumn::make('seat_number')
                    ->label(__('exam_results.seat_number'))
                    ->getStateUsing(fn ($record): string => self::entryValue($record, 'seat_number', self::entryValue($record, 'list_number', $record->creator?->seat_number)))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('data->seat_number', 'like', "%{$search}%")
                        ->orWhere('data->list_number', 'like', "%{$search}%")),

                TextColumn::make('name_khmer')
                    ->label(__('exam_results.name_khmer'))
                    ->getStateUsing(fn ($record): string => self::khmerName($record))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('data->first_name_kh', 'like', "%{$search}%")
                        ->orWhere('data->last_name_kh', 'like', "%{$search}%")),

                TextColumn::make('name_latin')
                    ->label(__('exam_results.name_latin'))
                    ->getStateUsing(fn ($record): string => self::latinName($record))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('data->first_name_en', 'like', "%{$search}%")
                        ->orWhere('data->last_name_en', 'like', "%{$search}%")),

                TextColumn::make('date_of_birth')
                    ->label(__('exam_results.date_of_birth'))
                    ->getStateUsing(fn ($record): string => self::dateValue(self::entryValue($record, 'date_of_birth', $record->creator?->date_of_birth))),
            ])
            ->filters([
                Filter::make('exam_result_filters')
                    ->label(new HtmlString('&nbsp;'))
                    ->schema([
                        Select::make('form_selection')
                            ->label(__('review_applications.form_type'))
                            ->options(fn (): array => self::dynamicFormTypeOptions())
                            ->native(false)
                            ->live(),

                        Select::make('reviewed_year')
                            ->label(__('review_applications.reviewed_year'))
                            ->options(fn (): array => self::dynamicPassedYears())
                            ->native(false)
                            ->live(),
                    ])
                    ->columns(4)
                    ->columnSpanFull()
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['form_selection'] ?? null),
                                fn (Builder $query): Builder => self::applyFormTypeFilter(
                                    $query,
                                    (string) $data['form_selection'],
                                )
                            )
                            ->when(
                                filled($data['reviewed_year'] ?? null),
                                fn (Builder $query): Builder => $query->whereRaw(
                                    "EXTRACT(YEAR FROM NULLIF(data->>'candidate_reviewed_at', '')::timestamp) = ?",
                                    [$data['reviewed_year']]
                                )
                            );
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->filtersFormColumns(4)
            ->recordActions([
                Action::make('notify_student')
                    ->label(__('exam_results.notify_student'))
                    ->icon('heroicon-o-bell-alert')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('exam_results.notify_student_confirm_title'))
                    ->modalDescription(__('exam_results.notify_student_confirm_description'))
                    ->modalSubmitActionLabel(__('exam_results.send_notification'))
                    ->modalCancelActionLabel(__('app.cancel'))
                    ->visible(fn (CustomFormEntry $record): bool => ! ReviewApplicationsTable::hasStudentReviewResultNotification($record, 'passed'))
                    ->action(function (CustomFormEntry $record, $livewire): void {
                        $sent = ReviewApplicationsTable::notifyStudentReviewResult(
                            record: $record,
                            status: 'passed',
                            note: null,
                        );

                        $notification = Notification::make()
                            ->title($sent ? __('exam_results.notification_sent') : __('exam_results.notification_already_sent'));

                        $sent
                            ? $notification->success()
                            : $notification->warning();

                        $notification->send();

                        if (method_exists($livewire, 'flushCachedTableRecords')) {
                            $livewire->flushCachedTableRecords();
                        }

                        $livewire->dispatch('$refresh');
                    }),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function downloadExcel(iterable $records)
    {
        $filename = 'exam-results-' . now()->format('Y-m-d-His') . '.xls';

        return response()->streamDownload(function () use ($records): void {
            echo '<html><head><meta charset="UTF-8"></head><body>';
            echo '<table border="1">';
            echo '<thead><tr>';

            foreach (self::excelHeadings() as $heading) {
                echo '<th>' . e($heading) . '</th>';
            }

            echo '</tr></thead><tbody>';

            $rowNumber = 1;

            foreach ($records as $record) {
                if (! $record instanceof CustomFormEntry) {
                    continue;
                }

                $row = [
                    $rowNumber++,
                    self::entryValue($record, 'academic_year', $record->creator?->academic_year),
                    self::entryValue($record, 'seat_number', self::entryValue($record, 'list_number', $record->creator?->seat_number)),
                    self::khmerName($record),
                    self::latinName($record),
                    self::exportDateValue(self::entryValue($record, 'date_of_birth', $record->creator?->date_of_birth)),
                ];

                echo '<tr>';

                foreach ($row as $value) {
                    echo '<td>' . e((string) $value) . '</td>';
                }

                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '</body></html>';
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    protected static function excelHeadings(): array
    {
        return [
            'id',
            'academic_year',
            'seat_number',
            'name_khmer',
            'name_latin',
            'date_of_birth',
        ];
    }

    protected static function exportDateValue(mixed $state): string
    {
        if (blank($state) || $state === '-') {
            return '-';
        }

        try {
            return Carbon::parse($state)->format('Y-m-d');
        } catch (\Throwable) {
            return (string) $state;
        }
    }

    public static function sendPassedNotifications(iterable $records): int
    {
        $sentCount = 0;

        foreach ($records as $record) {
            if (! $record instanceof CustomFormEntry) {
                continue;
            }

            $sent = ReviewApplicationsTable::notifyStudentReviewResult(
                record: $record,
                status: 'passed',
                note: null,
            );

            if ($sent) {
                $sentCount++;
            }
        }

        return $sentCount;
    }

    public static function hasUnsentPassedNotifications(): bool
    {
        return CustomFormEntry::query()
            ->with(['creator', 'customForm'])
            ->where('data->candidate_status', 'passed')
            ->get()
            ->contains(fn (CustomFormEntry $record): bool => ! ReviewApplicationsTable::hasStudentReviewResultNotification($record, 'passed'));
    }

    protected static function dynamicPassedYears(): array
    {
        return CustomFormEntry::query()
            ->where('data->candidate_status', 'passed')
            ->get(['data'])
            ->pluck('data.candidate_reviewed_at')
            ->filter()
            ->map(fn ($date): string => Carbon::parse($date)->format('Y'))
            ->unique()
            ->sort()
            ->mapWithKeys(fn ($year): array => [(string) $year => (string) $year])
            ->toArray();
    }

    protected static function dynamicFormTypeOptions(): array
    {
        $options = [];

        CustomForm::query()
            ->where('menu_placement', 'sidebar')
            ->where('is_active', true)
            ->where('slug', '!=', 'profile')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->each(function (CustomForm $form) use (&$options): void {
                $childForms = CustomForm::query()
                    ->where('custom_form_id', $form->id)
                    ->where('menu_placement', 'sub_item')
                    ->where('is_active', true)
                    ->whereNotNull('sub_item_type')
                    ->orderBy('id')
                    ->get(['id', 'name', 'custom_form_id', 'sub_item_type']);

                if (self::formHasPassedEntries((int) $form->id, $childForms->pluck('id')->all())) {
                    $options[self::formFilterValue((int) $form->id)] = $form->display_name;
                }

                foreach ($childForms as $childForm) {
                    if (! self::subFormHasPassedEntries($childForm)) {
                        continue;
                    }

                    $options[self::subFormFilterValue((int) $childForm->id)] = $form->display_name . ' - ' . $childForm->display_name;
                }
            });

        return $options;
    }

    protected static function applyFormTypeFilter(Builder $query, string $formType): Builder
    {
        if (str_starts_with($formType, 'form:')) {
            $formId = self::formIdFromFilterValue($formType);

            if ($formId) {
                return $query->whereIn('custom_form_id', self::sidebarFormIdsForFilter($formId));
            }
        }

        if (str_starts_with($formType, 'subform:')) {
            $subFormId = self::subFormIdFromFilterValue($formType);
            $subForm = $subFormId
                ? CustomForm::query()->whereKey($subFormId)->first(['id', 'custom_form_id', 'sub_item_type'])
                : null;

            if ($subForm) {
                return $query->where(function (Builder $query) use ($subForm): void {
                    $query->where('custom_form_id', $subForm->id);

                    if (filled($subForm->sub_item_type)) {
                        $query->orWhere(function (Builder $query) use ($subForm): void {
                            $query->where('custom_form_id', $subForm->custom_form_id)
                                ->where('data->form_selection', $subForm->sub_item_type);
                        });
                    }
                });
            }
        }

        return $query->where('data->form_selection', $formType);
    }

    protected static function formFilterValue(int $formId): string
    {
        return 'form:' . $formId;
    }

    protected static function formIdFromFilterValue(string $value): ?int
    {
        if (! str_starts_with($value, 'form:')) {
            return null;
        }

        $formId = (int) substr($value, 5);

        return $formId > 0 ? $formId : null;
    }

    protected static function subFormFilterValue(int $formId): string
    {
        return 'subform:' . $formId;
    }

    protected static function subFormIdFromFilterValue(string $value): ?int
    {
        if (! str_starts_with($value, 'subform:')) {
            return null;
        }

        $formId = (int) substr($value, 8);

        return $formId > 0 ? $formId : null;
    }

    protected static function sidebarFormIdsForFilter(int $formId): array
    {
        $childIds = CustomForm::query()
            ->where('custom_form_id', $formId)
            ->where('menu_placement', 'sub_item')
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return array_values(array_unique(array_merge([$formId], $childIds)));
    }

    protected static function passedCandidateQuery(Builder $query): Builder
    {
        return $query->where('data->candidate_status', 'passed');
    }

    protected static function formHasPassedEntries(int $formId, array $childFormIds = []): bool
    {
        $formIds = array_values(array_unique(array_merge([$formId], array_map('intval', $childFormIds))));

        return self::passedCandidateQuery(CustomFormEntry::query())
            ->whereIn('custom_form_id', $formIds)
            ->exists();
    }

    protected static function subFormHasPassedEntries(CustomForm $subForm): bool
    {
        return self::passedCandidateQuery(CustomFormEntry::query())
            ->where(function (Builder $query) use ($subForm): void {
                $query->where('custom_form_id', $subForm->id);

                if (filled($subForm->sub_item_type)) {
                    $query->orWhere(function (Builder $query) use ($subForm): void {
                        $query->where('custom_form_id', $subForm->custom_form_id)
                            ->where('data->form_selection', $subForm->sub_item_type);
                    });
                }
            })
            ->exists();
    }

    protected static function recordFormTypeLabel(CustomFormEntry $record): string
    {
        $form = $record->customForm;

        if ($form?->menu_placement === 'sub_item') {
            $parentName = $form->parentForm?->display_name;

            return filled($parentName)
                ? $parentName . ' - ' . $form->display_name
                : $form->display_name;
        }

        $selection = (string) data_get($record->data, 'form_selection');

        if ($form?->menu_placement === 'sidebar' && filled($selection)) {
            $subForm = CustomForm::query()
                ->where('custom_form_id', $form->id)
                ->where('menu_placement', 'sub_item')
                ->where('sub_item_type', $selection)
                ->first(['name']);

            if ($subForm) {
                return $form->display_name . ' - ' . $subForm->display_name;
            }
        }

        if ($form) {
            return $form->display_name;
        }

        return self::formTypeLabel($selection);
    }

    protected static function formTypeLabel(?string $state): string
    {
        if (blank($state)) {
            return '-';
        }

        $form = CustomForm::query()
            ->where('menu_placement', 'sub_item')
            ->where('sub_item_type', $state)
            ->first(['name']);

        if ($form) {
            return $form->display_name;
        }

        return match ($state) {
            'associate' => app()->getLocale() === 'km' ? 'បរិញ្ញាបត្ររង' : 'Associate',
            'bachelor' => app()->getLocale() === 'km' ? 'បរិញ្ញាបត្រ' : 'Bachelor',
            'master' => app()->getLocale() === 'km' ? 'អនុបណ្ឌិត' : 'Master',
            'phd' => app()->getLocale() === 'km' ? 'បណ្ឌិត' : 'PhD',
            default => filled($state) ? ucfirst((string) $state) : '-',
        };
    }

    protected static function statusLabel(?string $state): string
    {
        return match (strtolower((string) $state)) {
            'passed' => __('review_applications.statuses.passed'),
            default => filled($state) ? ucfirst((string) $state) : '-',
        };
    }

    protected static function entryValue($record, string $key, mixed $fallback = null): string
    {
        $value = data_get($record->data, $key);

        if (blank($value)) {
            $value = $fallback;
        }

        return blank($value) ? '-' : (string) $value;
    }

    protected static function khmerName($record): string
    {
        $name = trim(collect([
            data_get($record->data, 'first_name_kh'),
            data_get($record->data, 'last_name_kh'),
        ])->filter()->join(' '));

        return filled($name) ? $name : self::entryValue($record, 'name_khmer', $record->creator?->name);
    }

    protected static function latinName($record): string
    {
        $name = trim(collect([
            data_get($record->data, 'first_name_en'),
            data_get($record->data, 'last_name_en'),
        ])->filter()->join(' '));

        return filled($name) ? strtoupper($name) : self::entryValue($record, 'name_latin', $record->creator?->name_latin);
    }

    protected static function genderLabel(string $state): string
    {
        return match (strtolower($state)) {
            'male' => app()->getLocale() === 'km' ? 'ប្រុស' : 'Male',
            'female' => app()->getLocale() === 'km' ? 'ស្រី' : 'Female',
            default => $state,
        };
    }

    protected static function dateValue(mixed $state): string
    {
        if (blank($state) || $state === '-') {
            return '-';
        }

        try {
            return Carbon::parse($state)->format('d/m/Y');
        } catch (\Throwable) {
            return (string) $state;
        }
    }
}
