<?php

namespace App\Filament\Admin\Resources\ExamResults\Tables;

use App\Filament\Admin\Resources\CandidateRequested\Tables\CandidateRequestedTable;
use App\Support\PassedResultMenuOptions;
use App\Support\LocalizedNumber;
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
    public static function configure(Table $table, string $resultMenu = PassedResultMenuOptions::EXAM_RESULTS): Table
    {
        return $table
            ->recordAction(null)
            ->recordUrl(null)
            ->selectable()
            ->columns([
                TextColumn::make('row_number')
                    ->label(__('exam_results.no'))
                    ->rowIndex()
                    ->formatStateUsing(fn ($state): string => LocalizedNumber::digits($state))
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('seat_number')
                    ->label(__('exam_results.seat_number'))
                    ->getStateUsing(fn ($record): string => self::entryValue($record, 'seat_number', self::entryValue($record, 'list_number', $record->creator?->seat_number)))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('data->seat_number', 'like', "%{$search}%")
                        ->orWhere('data->list_number', 'like', "%{$search}%"))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('name_khmer')
                    ->label(__('exam_results.name_khmer'))
                    ->getStateUsing(fn ($record): string => self::khmerName($record))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('data->first_name_kh', 'like', "%{$search}%")
                        ->orWhere('data->last_name_kh', 'like', "%{$search}%"))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('name_latin')
                    ->label(__('exam_results.name_latin'))
                    ->getStateUsing(fn ($record): string => self::latinName($record))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('data->first_name_en', 'like', "%{$search}%")
                        ->orWhere('data->last_name_en', 'like', "%{$search}%"))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('gender')
                    ->label(__('exam_results.gender'))
                    ->getStateUsing(fn ($record): string => self::genderLabel(self::entryValue($record, 'gender')))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('date_of_birth')
                    ->label(__('exam_results.date_of_birth'))
                    ->getStateUsing(fn ($record): string => self::dateValue(self::entryValue($record, 'date_of_birth', $record->creator?->date_of_birth)))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('academic_year')
                    ->label(__('exam_results.academic_year'))
                    ->getStateUsing(fn ($record): string => self::entryValue($record, 'academic_year', $record->creator?->academic_year))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where('data->academic_year', 'like', "%{$search}%"))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('exam_date')
                    ->label(__('exam_results.exam_date'))
                    ->getStateUsing(fn ($record): string => self::dateValue(self::entryValue($record, 'exam_date')))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('major')
                    ->label(__('exam_results.major'))
                    ->badge()
                    ->getStateUsing(fn ($record): string => self::entryValue(
                        $record,
                        filled(data_get($record->data, 'selected_major')) ? 'selected_major' : 'degree_level_major'
                    ))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('data->selected_major', 'like', "%{$search}%")
                        ->orWhere('data->degree_level_major', 'like', "%{$search}%"))
                    ->toggleable(isToggledHiddenByDefault: false),

            ])
            ->filters([
                Filter::make('exam_result_filters')
                    ->label(new HtmlString('&nbsp;'))
                    ->schema([
                        Select::make('academic_year')
                            ->label(__('exam_results.academic_year'))
                            ->options(fn (): array => self::dynamicAcademicYearOptions($resultMenu))
                            ->native(false)
                            ->searchable()
                            ->live(),

                        Select::make('major')
                            ->label(__('exam_results.major'))
                            ->options(fn (): array => self::dynamicMajorOptions($resultMenu))
                            ->native(false)
                            ->searchable()
                            ->live(),
                    ])
                    ->columns(4)
                    ->columnSpanFull()
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['academic_year'] ?? null),
                                fn (Builder $query): Builder => $query->where(function (Builder $query) use ($data): void {
                                    $query->where('data->academic_year', $data['academic_year'])
                                        ->orWhereHas('creator', fn (Builder $creatorQuery): Builder => $creatorQuery->where('academic_year', $data['academic_year']));
                                })
                            )
                            ->when(
                                filled($data['major'] ?? null),
                                fn (Builder $query): Builder => $query->where(function (Builder $query) use ($data): void {
                                    $query->where('data->selected_major', $data['major'])
                                        ->orWhere('data->degree_level_major', $data['major']);
                                })
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
                    ->visible(fn (CustomFormEntry $record): bool => ! CandidateRequestedTable::hasStudentReviewResultNotification($record, 'passed'))
                    ->action(function (CustomFormEntry $record, $livewire): void {
                        $sent = CandidateRequestedTable::notifyStudentReviewResult(
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

    public static function downloadExcel(iterable $records, ?array $columnKeys = null)
    {
        $filename = 'exam-results-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/' . uniqid('exam-results-', true) . '.xlsx');

        $columnKeys ??= array_keys(self::exportColumnDefinitions());

        self::writeXlsx($path, [
            [
                'name' => 'Clean Data',
                'rows' => self::excelRows($records, $columnKeys),
            ],
            [
                'name' => 'Database Export',
                'rows' => self::cleanDataRows($records, $columnKeys),
            ],
        ]);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    protected static function excelRows(iterable $records, ?array $columnKeys = null): array
    {
        $columnKeys ??= array_keys(self::exportColumnDefinitions());
        $rows = [self::excelHeadings($columnKeys)];
        $rowNumber = 1;

        foreach ($records as $record) {
            if (! $record instanceof CustomFormEntry) {
                continue;
            }

            $rows[] = self::exportRow($record, $rowNumber++, $columnKeys);
        }

        return $rows;
    }

    protected static function cleanDataRows(iterable $records, array $columnKeys): array
    {
        $rows = [self::cleanDataHeadings($columnKeys)];
        $rowNumber = 1;

        foreach ($records as $record) {
            if (! $record instanceof CustomFormEntry) {
                continue;
            }

            $rows[] = self::cleanDataRow($record, $rowNumber++, $columnKeys);
        }

        return $rows;
    }

    protected static function writeXlsx(string $path, array $sheets): void
    {
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', self::contentTypesXml($sheets));
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/workbook.xml', self::workbookXml($sheets));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRelsXml($sheets));

        foreach (array_values($sheets) as $index => $sheet) {
            $zip->addFromString(
                'xl/worksheets/sheet' . ($index + 1) . '.xml',
                self::worksheetXml($sheet['rows'])
            );
        }

        $zip->close();
    }

    protected static function worksheetXml(array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $xml .= '<row r="' . $excelRow . '">';

            foreach (array_values($row) as $columnIndex => $value) {
                $cell = self::columnName($columnIndex + 1) . $excelRow;
                $xml .= '<c r="' . $cell . '" t="inlineStr"><is><t>' . self::xmlValue($value) . '</t></is></c>';
            }

            $xml .= '</row>';
        }

        return $xml . '</sheetData></worksheet>';
    }

    protected static function columnName(int $index): string
    {
        $name = '';

        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)) . $name;
            $index = intdiv($index, 26);
        }

        return $name;
    }

    protected static function xmlValue(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    protected static function excelHeadings(array $columnKeys): array
    {
        $definitions = self::exportColumnDefinitions();

        return collect($columnKeys)
            ->filter(fn (string $key): bool => array_key_exists($key, $definitions))
            ->map(fn (string $key): string => $definitions[$key]['label'])
            ->values()
            ->all();
    }

    protected static function cleanDataHeadings(array $columnKeys): array
    {
        $definitions = self::exportColumnDefinitions();

        return collect($columnKeys)
            ->filter(fn (string $key): bool => array_key_exists($key, $definitions))
            ->map(fn (string $key): string => $definitions[$key]['field_key'])
            ->values()
            ->all();
    }

    protected static function exportRow(CustomFormEntry $record, int $rowNumber, array $columnKeys): array
    {
        $definitions = self::exportColumnDefinitions();

        return collect($columnKeys)
            ->filter(fn (string $key): bool => array_key_exists($key, $definitions))
            ->map(fn (string $key): string => $definitions[$key]['value']($record, $rowNumber))
            ->values()
            ->all();
    }

    protected static function cleanDataRow(CustomFormEntry $record, int $rowNumber, array $columnKeys): array
    {
        $definitions = self::exportColumnDefinitions();

        return collect($columnKeys)
            ->filter(fn (string $key): bool => array_key_exists($key, $definitions))
            ->map(fn (string $key): string => $definitions[$key]['clean']($record, $rowNumber))
            ->values()
            ->all();
    }

    protected static function exportColumnDefinitions(): array
    {
        return [
            'row_number' => [
                'label' => __('exam_results.no'),
                'field_key' => 'row_number',
                'value' => fn (CustomFormEntry $record, int $rowNumber): string => (string) $rowNumber,
                'clean' => fn (CustomFormEntry $record, int $rowNumber): string => (string) $rowNumber,
            ],
            'academic_year' => [
                'label' => __('exam_results.academic_year'),
                'field_key' => 'academic_year',
                'value' => fn (CustomFormEntry $record): string => self::entryValue($record, 'academic_year', $record->creator?->academic_year),
                'clean' => fn (CustomFormEntry $record): string => self::entryValue($record, 'academic_year', $record->creator?->academic_year),
            ],
            'seat_number' => [
                'label' => __('exam_results.seat_number'),
                'field_key' => 'seat_number',
                'value' => fn (CustomFormEntry $record): string => self::entryValue($record, 'seat_number', self::entryValue($record, 'list_number', $record->creator?->seat_number)),
                'clean' => fn (CustomFormEntry $record): string => self::entryValue($record, 'seat_number', self::entryValue($record, 'list_number', $record->creator?->seat_number)),
            ],
            'name_khmer' => [
                'label' => __('exam_results.name_khmer'),
                'field_key' => 'name_khmer',
                'value' => fn (CustomFormEntry $record): string => self::khmerName($record),
                'clean' => fn (CustomFormEntry $record): string => self::khmerName($record),
            ],
            'name_latin' => [
                'label' => __('exam_results.name_latin'),
                'field_key' => 'name_latin',
                'value' => fn (CustomFormEntry $record): string => self::latinName($record),
                'clean' => fn (CustomFormEntry $record): string => self::latinName($record),
            ],
            'gender' => [
                'label' => __('exam_results.gender'),
                'field_key' => 'gender',
                'value' => fn (CustomFormEntry $record): string => self::genderLabel(self::entryValue($record, 'gender')),
                'clean' => fn (CustomFormEntry $record): string => self::entryValue($record, 'gender'),
            ],
            'major' => [
                'label' => __('exam_results.major'),
                'field_key' => 'major',
                'value' => fn (CustomFormEntry $record): string => self::entryValue(
                    $record,
                    filled(data_get($record->data, 'selected_major')) ? 'selected_major' : 'degree_level_major'
                ),
                'clean' => fn (CustomFormEntry $record): string => self::entryValue(
                    $record,
                    filled(data_get($record->data, 'selected_major')) ? 'selected_major' : 'degree_level_major'
                ),
            ],
            'date_of_birth' => [
                'label' => __('exam_results.date_of_birth'),
                'field_key' => 'date_of_birth',
                'value' => fn (CustomFormEntry $record): string => self::dateValue(
                    self::entryValue($record, 'date_of_birth', $record->creator?->date_of_birth)
                ),
                'clean' => fn (CustomFormEntry $record): string => self::entryValue(
                    $record,
                    'date_of_birth',
                    $record->creator?->date_of_birth
                ),
            ],
            'exam_date' => [
                'label' => __('exam_results.exam_date'),
                'field_key' => 'exam_date',
                'value' => fn (CustomFormEntry $record): string => self::dateValue(
                    self::entryValue($record, 'exam_date')
                ),
                'clean' => fn (CustomFormEntry $record): string => self::entryValue(
                    $record,
                    'exam_date'
                ),
            ],
        ];
    }

    protected static function contentTypesXml(array $sheets): string
    {
        $overrides = collect(array_keys($sheets))
            ->map(fn (int $index): string => '<Override PartName="/xl/worksheets/sheet' . ($index + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>')
            ->implode('');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . $overrides
            . '</Types>';
    }

    protected static function workbookXml(array $sheets): string
    {
        $sheetXml = collect(array_values($sheets))
            ->map(fn (array $sheet, int $index): string => '<sheet name="' . self::xmlValue(self::sheetName($sheet['name'])) . '" sheetId="' . ($index + 1) . '" r:id="rId' . ($index + 1) . '"/>')
            ->implode('');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $sheetXml . '</sheets>'
            . '</workbook>';
    }

    protected static function workbookRelsXml(array $sheets): string
    {
        $relationships = collect(array_keys($sheets))
            ->map(fn (int $index): string => '<Relationship Id="rId' . ($index + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ($index + 1) . '.xml"/>')
            ->implode('');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $relationships
            . '</Relationships>';
    }

    protected static function sheetName(string $name): string
    {
        return substr($name, 0, 31);
    }

    protected static function rawFormTypeValue(CustomFormEntry $record): string
    {
        $selection = trim((string) data_get($record->data, 'form_selection'));

        if ($selection !== '') {
            return $selection;
        }

        $subItemType = trim((string) $record->customForm?->sub_item_type);

        if ($subItemType !== '') {
            return $subItemType;
        }

        $formSlug = trim((string) $record->customForm?->slug);

        if ($formSlug !== '') {
            return $formSlug;
        }

        return $record->custom_form_id ? (string) $record->custom_form_id : '-';
    }

    public static function sendPassedNotifications(iterable $records): int
    {
        $sentCount = 0;

        foreach ($records as $record) {
            if (! $record instanceof CustomFormEntry) {
                continue;
            }

            $sent = CandidateRequestedTable::notifyStudentReviewResult(
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

    public static function hasUnsentPassedNotifications(string $resultMenu = PassedResultMenuOptions::EXAM_RESULTS): bool
    {
        return self::applyPassedResultMenuFilter(
            query: CustomFormEntry::query()->with(['creator', 'customForm']),
            resultMenu: $resultMenu,
            hiddenFlag: null,
        )
            ->get()
            ->contains(fn (CustomFormEntry $record): bool => ! self::hasStudentPassedNotification($record));
    }

    public static function hasStudentPassedNotification(CustomFormEntry $record): bool
    {
        return CandidateRequestedTable::hasStudentReviewResultNotification($record, 'passed');
    }

    protected static function dynamicAcademicYearOptions(string $resultMenu = PassedResultMenuOptions::EXAM_RESULTS): array
    {
        return self::applyPassedResultMenuFilter(
            query: CustomFormEntry::query()->with('creator:id,academic_year'),
            resultMenu: $resultMenu,
            hiddenFlag: null,
        )
            ->get(['id', 'data'])
            ->flatMap(function (CustomFormEntry $entry): array {
                return array_filter([
                    data_get($entry->data, 'academic_year'),
                    $entry->creator?->academic_year,
                ], fn ($value): bool => filled($value));
            })
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $year): array => [$year => $year])
            ->toArray();
    }

    protected static function dynamicMajorOptions(string $resultMenu = PassedResultMenuOptions::EXAM_RESULTS): array
    {
        return self::applyPassedResultMenuFilter(
            query: CustomFormEntry::query(),
            resultMenu: $resultMenu,
            hiddenFlag: null,
        )
            ->get(['data'])
            ->flatMap(function (CustomFormEntry $entry): array {
                return array_filter([
                    data_get($entry->data, 'selected_major'),
                    data_get($entry->data, 'degree_level_major'),
                ], fn ($value): bool => filled($value));
            })
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $value): array => [$value => $value])
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

    protected static function passedCandidateQuery(Builder $query, string $resultMenu = PassedResultMenuOptions::EXAM_RESULTS): Builder
    {
        return self::applyPassedResultMenuFilter(
            query: $query,
            resultMenu: $resultMenu,
            hiddenFlag: null,
        );
    }

    protected static function formHasPassedEntries(int $formId, array $childFormIds = [], string $resultMenu = PassedResultMenuOptions::EXAM_RESULTS): bool
    {
        $formIds = array_values(array_unique(array_merge([$formId], array_map('intval', $childFormIds))));

        return self::passedCandidateQuery(CustomFormEntry::query(), $resultMenu)
            ->whereIn('custom_form_id', $formIds)
            ->exists();
    }

    protected static function subFormHasPassedEntries(CustomForm $subForm, string $resultMenu = PassedResultMenuOptions::EXAM_RESULTS): bool
    {
        return self::passedCandidateQuery(CustomFormEntry::query(), $resultMenu)
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

    public static function applyPassedResultMenuFilter(
        Builder $query,
        string $resultMenu = PassedResultMenuOptions::EXAM_RESULTS,
        ?string $hiddenFlag = null,
    ): Builder {
        $resultMenu = PassedResultMenuOptions::normalize($resultMenu);

        $query->where('data->candidate_status', 'passed')
            ->where(function (Builder $query) use ($resultMenu): void {
                $query
                    ->where(function (Builder $query) use ($resultMenu): void {
                        $query->whereHas('customForm', function (Builder $query) use ($resultMenu): void {
                            $query->where('menu_placement', 'sidebar')
                                ->where('is_active', true)
                                ->where('slug', '!=', 'profile')
                                ->where('passed_result_menu', $resultMenu);
                        })->where(function (Builder $query): void {
                            $query->whereNull('data->form_selection')
                                ->orWhere('data->form_selection', '')
                                ->orWhereNotExists(function ($subQuery): void {
                                    $subQuery->selectRaw('1')
                                        ->from('custom_forms as child_forms')
                                        ->whereColumn('child_forms.custom_form_id', 'custom_form_entries.custom_form_id')
                                        ->where('child_forms.menu_placement', 'sub_item')
                                        ->where('child_forms.is_active', true)
                                        ->whereRaw("LOWER(child_forms.sub_item_type) = LOWER(COALESCE(custom_form_entries.data->>'form_selection', ''))");
                                });
                        });
                    })
                    ->orWhereHas('customForm', function (Builder $query) use ($resultMenu): void {
                        $query->where('menu_placement', 'sub_item')
                            ->where('is_active', true)
                            ->where('passed_result_menu', $resultMenu);
                    })
                    ->orWhereExists(function ($subQuery) use ($resultMenu): void {
                        $subQuery->selectRaw('1')
                            ->from('custom_forms as child_forms')
                            ->whereColumn('child_forms.custom_form_id', 'custom_form_entries.custom_form_id')
                            ->where('child_forms.menu_placement', 'sub_item')
                            ->where('child_forms.is_active', true)
                            ->where('child_forms.passed_result_menu', $resultMenu)
                            ->whereRaw("LOWER(child_forms.sub_item_type) = LOWER(COALESCE(custom_form_entries.data->>'form_selection', ''))");
                    });
            });

        if ($hiddenFlag) {
            $query->where(function (Builder $query) use ($hiddenFlag): void {
                $query->whereNull('data->' . $hiddenFlag)
                    ->orWhere('data->' . $hiddenFlag, false)
                    ->orWhere('data->' . $hiddenFlag, 'false')
                    ->orWhere('data->' . $hiddenFlag, 0)
                    ->orWhere('data->' . $hiddenFlag, '0');
            });
        }

        return $query;
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
            'associate' => __('exam_results.options.form_type.associate'),
            'bachelor' => __('exam_results.options.form_type.bachelor'),
            'master' => __('exam_results.options.form_type.master'),
            'phd' => __('exam_results.options.form_type.phd'),
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
            'male' => __('exam_results.options.gender.male'),
            'female' => __('exam_results.options.gender.female'),
            default => $state,
        };
    }

    protected static function dateValue(mixed $state): string
    {
        if (blank($state) || $state === '-') {
            return '-';
        }

        try {
            return Carbon::parse($state)->format('d-m-Y');
        } catch (\Throwable) {
            return (string) $state;
        }
    }
}
