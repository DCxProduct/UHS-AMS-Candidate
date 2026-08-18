<?php

namespace Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\Tables;

use App\Filament\Admin\Resources\CandidatePaymentLists\CandidatePaymentListResource;
use App\Support\AuditLogger;
use App\Support\FormEntryData;
use App\Support\LocalizedDate;
use App\Support\LocalizedNumber;
use App\Models\User;
use App\Models\GeoLocation;
use App\Support\NotificationLanguage;
use Chanthoeun\FilamentCustomForms\Filament\Resources\CustomFormEntries\CustomFormEntryResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomFormEntriesTable
{
    public static function configure(Table $table): Table
    {
        $formId = self::getFormId($table);

        return $table
            ->selectable(self::currentPanelIsAdmin())
            ->recordAction(null)
            ->recordUrl(null)
            ->columns(self::getColumns($formId))
            ->filters(self::getFilters($formId), layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->filtersFormColumns(4)
            ->defaultSort('created_at', 'desc')
            ->recordActions(self::getRecordActions())
            ->modifyQueryUsing(fn (Builder $query) => self::applyQueryConstraints($query, $formId));
    }

    public static function downloadExcel(iterable $records, ?array $columnKeys = null, ?string $formId = null, ?Table $table = null)
    {
        $filename = 'form-submission-list-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/' . uniqid('form-submission-list-', true) . '.xlsx');

        $columnKeys ??= collect(self::getColumns($formId))
            ->map(fn (TextColumn $column): string => $column->getName())
            ->values()
            ->all();

        self::writeXlsx($path, [
            [
                'name' => 'Clean Data',
                'rows' => self::excelRows($records, $columnKeys, $formId, $table),
            ],
            [
                'name' => 'Database Export',
                'rows' => self::cleanDataRows($records, $columnKeys, $formId, $table),
            ],
        ]);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    protected static function excelRows(iterable $records, array $columnKeys, ?string $formId, ?Table $table): array
    {
        $rows = [self::exportHeadings($columnKeys, $formId, $table)];
        $rowNumber = 1;

        foreach ($records as $record) {
            $rows[] = self::exportRow($record, $rowNumber++, $columnKeys, $formId, $table, formatted: true);
        }

        return $rows;
    }

    protected static function cleanDataRows(iterable $records, array $columnKeys, ?string $formId, ?Table $table): array
    {
        $rows = [self::cleanDataHeadings($columnKeys)];
        $rowNumber = 1;

        foreach ($records as $record) {
            $rows[] = self::exportRow($record, $rowNumber++, $columnKeys, $formId, $table, formatted: false);
        }

        return $rows;
    }

    protected static function exportHeadings(array $columnKeys, ?string $formId, ?Table $table): array
    {
        $columns = self::exportColumnsByKey($formId, $table);

        return collect($columnKeys)
            ->filter(fn (string $key): bool => array_key_exists($key, $columns))
            ->map(fn (string $key): string => self::columnLabel($columns[$key]))
            ->values()
            ->all();
    }

    protected static function cleanDataHeadings(array $columnKeys): array
    {
        return collect($columnKeys)
            ->map(fn (string $key): string => str_starts_with($key, 'data.') ? substr($key, 5) : $key)
            ->values()
            ->all();
    }

    protected static function exportRow($record, int $rowNumber, array $columnKeys, ?string $formId, ?Table $table, bool $formatted): array
    {
        $columns = self::exportColumnsByKey($formId, $table);

        return collect($columnKeys)
            ->filter(fn (string $key): bool => array_key_exists($key, $columns))
            ->map(function (string $key) use ($columns, $record, $rowNumber, $formatted): string {
                if ($key === 'row_number') {
                    return (string) $rowNumber;
                }

                $column = clone $columns[$key];
                $column->record($record);
                $column->recordKey((string) data_get($record, 'id'));
                $column->clearCachedState();

                $state = $column->getState();

                if (! $formatted) {
                    return self::normalizeExportValue($state);
                }

                return self::normalizeExportValue($column->formatState($state));
            })
            ->values()
            ->all();
    }

    protected static function exportColumnsByKey(?string $formId, ?Table $table): array
    {
        return collect(self::getColumns($formId))
            ->map(function (TextColumn $column) use ($table): TextColumn {
                if ($table) {
                    $column->table($table);
                }

                return $column;
            })
            ->keyBy(fn (TextColumn $column): string => $column->getName())
            ->all();
    }

    protected static function columnLabel(TextColumn $column): string
    {
        $label = $column->getLabel();

        if ($label instanceof Htmlable) {
            $label = $label->toHtml();
        }

        return trim(strip_tags((string) $label));
    }

    protected static function normalizeExportValue(mixed $value): string
    {
        if ($value instanceof Htmlable) {
            $value = $value->toHtml();
        }

        if (is_array($value)) {
            $value = collect($value)
                ->map(fn ($item): string => is_scalar($item) || $item === null ? (string) $item : json_encode($item))
                ->implode(', ');
        }

        $value = trim(strip_tags((string) $value));

        return $value === '' ? '-' : $value;
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

    protected static function columnName(int $index): string
    {
        $column = '';

        while ($index > 0) {
            $index--;
            $column = chr(65 + ($index % 26)) . $column;
            $index = intdiv($index, 26);
        }

        return $column;
    }

    protected static function xmlValue(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    protected static function getFormId(Table $table): ?string
    {
        $livewire = $table->getLivewire();

        return data_get($livewire, 'tableFilters.custom_form_id.value')
            ?? data_get($livewire, 'activeFormId')
            ?? request()->input('tableFilters.custom_form_id.value')
            ?? data_get(request()->query('tableFilters'), 'custom_form_id.value')
            ?? request()->query('form_id');
    }

    protected static function getColumns(?string $formId): array
    {
        if ($formId && self::isProfileForm($formId)) {
            return self::getProfileColumns();
        }

        $nationalExamFormId = \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
            ->where('slug', 'national-examination-registration')
            ->value('id');

        $targetFormIds = [];
        if (empty($formId) || (string)$formId === (string)$nationalExamFormId) {
            if ($nationalExamFormId) {
                $targetFormIds[] = $nationalExamFormId;
                $childFormIds = \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
                    ->where('custom_form_id', $nationalExamFormId)
                    ->where('menu_placement', 'sub_item')
                    ->whereNotNull('sub_item_type')
                    ->where('is_active', true)
                    ->pluck('id')
                    ->toArray();
                $targetFormIds = array_merge($targetFormIds, $childFormIds);
            }
        } elseif ($formId) {
            $targetFormIds[] = $formId;

            $childFormIds = \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
                ->where('custom_form_id', $formId)
                ->where('menu_placement', 'sub_item')
                ->whereNotNull('sub_item_type')
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();

            $targetFormIds = array_merge($targetFormIds, $childFormIds);
        }

        $additionalColumns = [];
        if (!empty($targetFormIds)) {
            $fields = \Chanthoeun\FilamentCustomForms\Models\CustomFormField::query()
                ->whereIn('custom_form_id', $targetFormIds)
                ->whereNotIn('type', ['section', 'grid', 'fieldset', 'repeater', 'wizard', 'info'])
                ->orderBy('sort')
                ->get();

            $imageFieldKeys = $fields
                ->filter(fn ($field): bool => self::isImageFieldType((string) $field->type))
                ->pluck('name')
                ->filter()
                ->map(fn ($name): string => (string) $name)
                ->all();

            $processedKeys = [
                'form_selection',
                ...FormEntryData::majorKeys(),
                'gender',
                'phone_number',
                'academic_year',
                'registration_status',
                'candidate_status',
            ];

            foreach ($fields as $field) {
                $key = (string) $field->name;
                if (blank($key) || in_array($key, $processedKeys, true) || in_array($key, $imageFieldKeys, true)) {
                    continue;
                }

                if (isset($additionalColumns[$key])) {
                    continue;
                }

                $label = self::transText($field->label ?: $key);
                if ($key === 'last_name_kh') {
                    $label = __('app.custom_form_entry_ui.labels.last_name_kh');
                } elseif ($key === 'first_name_kh') {
                    $label = __('app.custom_form_entry_ui.labels.first_name_kh');
                } elseif ($key === 'last_name_en') {
                    $label = __('app.custom_form_entry_ui.labels.last_name_en');
                } elseif ($key === 'first_name_en') {
                    $label = __('app.custom_form_entry_ui.labels.first_name_en');
                }

                $column = TextColumn::make("data.{$key}")
                    ->label($label)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap();

                if (self::isGeoColumn((string) $key)) {
                    $column->formatStateUsing(fn (mixed $state): string => self::geoLocationName($state));
                }

                // Format Choice Columns (Select, Dropdown, Radio, Checkbox, etc.)
                if (!self::isGeoColumn((string) $key)) {
                    $fieldOptions = is_array($field->options) ? $field->options : json_decode((string) $field->options, true);
                    $choices = $fieldOptions['choices'] ?? null;
                    if (is_array($choices) && !empty($choices)) {
                        $column->formatStateUsing(fn (mixed $state): string => self::formatChoiceState($choices, $state));
                    }
                }

                // Format Date Columns
                if (in_array($field->type, ['date_picker', 'date_time_picker'], true)) {
                    $column->formatStateUsing(function (mixed $state): string {
                        if (blank($state)) {
                            return '-';
                        }
                        try {
                            return \Carbon\Carbon::parse($state)->format('d-M-Y');
                        } catch (\Throwable) {
                            return (string) $state;
                        }
                    });
                }

                $additionalColumns[$key] = $column;
            }

        }

        $columns = [
            // Row number
            TextColumn::make('row_number')
                ->label(__('app.custom_form_entry_ui.labels.no'))
                ->rowIndex()
                ->formatStateUsing(fn ($state): string => LocalizedNumber::digits($state))
                ->alignCenter()
                ->width('60px'),

            // 3. Major
            TextColumn::make('major')
                ->label(__('app.custom_form_entry_ui.labels.major'))
                ->badge()
                ->getStateUsing(function ($record): string {
                    $key = FormEntryData::firstFilledKey($record->data, FormEntryData::majorKeys(), 'major');
                    $state = data_get($record->data, $key);

                    return self::entryOptionLabel($record, $key, $state);
                })
                ->placeholder('-')
                ->searchable(query: fn (Builder $query, string $search): Builder => FormEntryData::applyJsonLikeFilter($query, FormEntryData::majorKeys(), $search))
                ->toggleable(isToggledHiddenByDefault: false),

            // 4. Gender
            TextColumn::make('data.gender')
                ->label(__('app.custom_form_entry_ui.labels.gender'))
                ->placeholder('-')
                ->formatStateUsing(fn (?string $state): string => match ($state) {
                    'male' => __('app.custom_form_entry_ui.options.gender.male'),
                    'female' => __('app.custom_form_entry_ui.options.gender.female'),
                    default => filled($state) ? ucfirst($state) : '-',
                })
                ->toggleable(isToggledHiddenByDefault: false),

            // 5. Phone Number
            TextColumn::make('data.phone_number')
                ->label(__('app.custom_form_entry_ui.labels.phone_number'))
                ->placeholder('-')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: false),

            // 6. Academic Year
            TextColumn::make('data.academic_year')
                ->label(__('app.custom_form_entry_ui.labels.academic_year'))
                ->placeholder('-')
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: false),

        ];

        foreach ($additionalColumns as $column) {
            $columns[] = $column;
        }

        $columns[] = self::reviewStatusColumn()->toggleable(isToggledHiddenByDefault: false);

        $columns[] = TextColumn::make('created_at')
            ->label(__('review_applications.request_at'))
            ->formatStateUsing(fn ($state, $record): string => LocalizedDate::dayMonthYear(
                data_get($record->data, 'submitted_at') ?: $state
            ))
            ->color('gray')
            ->toggleable(isToggledHiddenByDefault: false);

        $columns[] = TextColumn::make('updated_at')
            ->label(__('app.updated_at'))
            ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state))
            ->color('gray')
            ->toggleable(isToggledHiddenByDefault: false);

        return $columns;
    }

    protected static function reviewStatusColumn(): TextColumn
    {
        return TextColumn::make('review_status')
            ->label(__('review_applications.review_status'))
            ->badge()
            ->formatStateUsing(function ($state, $record): string {
                return match (self::entryStatus($record)) {
                    'passed', 'accepted', 'approved' => __('review_applications.statuses.accepted'),
                    'failed', 'rejected' => __('review_applications.statuses.rejected'),
                    'draft' => __('student_profile.save_as_draft'),
                    default => __('review_applications.statuses.pending'),
                };
            })
            ->color(function ($state, $record): string {
                return match (self::entryStatus($record)) {
                    'passed', 'accepted', 'approved' => 'success',
                    'failed', 'rejected' => 'danger',
                    'draft' => 'gray',
                    default => 'warning',
                };
            });
    }

    protected static function entryStatus($record): string
    {
        $dataStatus = strtolower((string) data_get($record->data, 'registration_status'));
        $reviewStatus = strtolower((string) ($record->review_status ?? 'pending'));

        if ($dataStatus === 'draft' || $reviewStatus === 'draft') {
            return 'draft';
        }

        if ($dataStatus === 'pending' || $reviewStatus === 'pending') {
            return 'pending';
        }

        return $reviewStatus ?: $dataStatus ?: 'pending';
    }

    protected static function reviewMessage($record): string
    {
        return trim((string) ($record->review_note ?? ''));
    }

    protected static function isNationalExaminationForm(string $formId): bool
    {
        return \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
            ->whereKey($formId)
            ->where('slug', 'national-examination-registration')
            ->exists();
    }

    protected static function getNationalExaminationColumns(): array
    {
        $columns = [
            TextColumn::make('data.form_selection')
                ->label(__('review_applications.form_type'))
                ->badge()
                ->sortable()
                ->formatStateUsing(fn (?string $state): string => self::formTypeLabel($state))
                ->color('info'),
        ];

        $nationalExamFormId = \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
            ->where('slug', 'national-examination-registration')
            ->value('id');

        if ($nationalExamFormId) {
            $childForms = \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
                ->where('custom_form_id', $nationalExamFormId)
                ->where('menu_placement', 'sub_item')
                ->whereNotNull('sub_item_type')
                ->where('is_active', true)
                ->get();

            foreach ($childForms as $childForm) {
                $fields = \Chanthoeun\FilamentCustomForms\Models\CustomFormField::query()
                    ->where('custom_form_id', $childForm->id)
                    ->whereNotIn('type', ['section', 'grid', 'fieldset', 'repeater', 'wizard', 'info'])
                    ->orderBy('sort')
                    ->get();

                foreach ($fields as $field) {
                    $key = (string) $field->name;

                    if (blank($key)) {
                        continue;
                    }

                    $column = TextColumn::make("data.{$key}")
                        ->label(self::transText($field->label ?: $key))
                        ->placeholder('-')
                        ->toggleable()
                        ->wrap();

                    if (self::isGeoColumn((string) $key)) {
                        $column->formatStateUsing(fn (mixed $state): string => self::geoLocationName($state));
                    }

                    if (! self::isGeoColumn((string) $key)) {
                        $fieldOptions = is_array($field->options) ? $field->options : json_decode((string) $field->options, true);
                        $choices = $fieldOptions['choices'] ?? null;

                        if (is_array($choices) && ! empty($choices)) {
                            $column->formatStateUsing(fn (mixed $state): string => self::formatChoiceState($choices, $state));
                        }
                    }

                    $columns[] = $column;
                }
            }
        }

        $columns[] = self::reviewStatusColumn();

        $columns[] = TextColumn::make('created_at')
            ->label(__('review_applications.request_at'))
            ->formatStateUsing(fn ($state, $record): string => LocalizedDate::dayMonthYear(
                data_get($record->data, 'submitted_at') ?: $state
            ))
            ->color('gray');

        $columns[] = TextColumn::make('updated_at')
            ->label(__('app.updated_at'))
            ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state))
            ->color('info');

        $columns[] = TextColumn::make('reviewed_at')
            ->label(__('review_applications.reviewed_at'))
            ->dateTime('d M Y H:i')
            ->placeholder(__('review_applications.not_reviewed_yet'))
            ->color('info');

        return $columns;
    }

    protected static function isProfileForm(string $formId): bool
    {
        return \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
            ->whereKey($formId)
            ->where('slug', 'profile')
            ->exists();
    }

    protected static function getProfileColumns(): array
    {
        return [
            TextColumn::make('data.first_name_kh')->label('First Name (Khmer)')->placeholder('-'),
            TextColumn::make('data.last_name_kh')->label('Last Name (Khmer)')->placeholder('-'),
            TextColumn::make('data.date_of_birth')->label('Date of Birth')->formatStateUsing(fn (mixed $state): string => self::formatProfileDate($state))->placeholder('-'),
            TextColumn::make('data.exam_period')->label('Exam Date')->formatStateUsing(fn (mixed $state): string => self::formatProfileDate($state))->placeholder('-'),
            TextColumn::make('data.exam_center')->label('Exam Center')->placeholder('-'),
            TextColumn::make('data.current_occupation')->label('Current Occupation')->placeholder('-'),
            TextColumn::make('data.place_of_work')->label('Place of Work / Organization')->placeholder('-')->wrap(),
        ];
    }

    protected static function formatProfileDate(mixed $state): string
    {
        if (blank($state)) {
            return '-';
        }

        try {
            return \Carbon\Carbon::parse($state)->format('d-M-Y');
        } catch (\Throwable) {
            return (string) $state;
        }
    }

    protected static function getFilters(?string $formId): array
    {
        if (auth()->user()?->registration_type === 'student') {
            return [];
        }

        return [
            Filter::make('application_review_filters')
                ->label(new HtmlString('&nbsp;'))
                ->schema([
                    Select::make('major')
                        ->label(__('candidate_payment_lists.columns.major'))
                        ->options(fn (): array => self::dynamicMajorOptions($formId))
                        ->native(false)
                        ->live(),

                    Select::make('review_status')
                        ->label(__('review_applications.review_status'))
                        ->options([
                            'pending' => __('review_applications.statuses.pending'),
                            'accepted' => __('review_applications.statuses.accepted'),
                            'rejected' => __('review_applications.statuses.rejected'),
                        ])
                        ->native(false)
                        ->live(),

                    Select::make('reviewed_year')
                        ->label(__('review_applications.reviewed_year'))
                        ->options(fn (): array => self::dynamicRequestReviewedYears($formId))
                        ->native(false)
                        ->live(),
                ])
                ->columns(4)
                ->columnSpanFull()
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when(
                            filled($data['review_status'] ?? null),
                            function (Builder $query) use ($data): Builder {
                                $status = $data['review_status'];

                                if ($status === 'accepted') {
                                    return $query->whereIn('review_status', ['accepted', 'passed', 'approved']);
                                }

                                if ($status === 'rejected') {
                                    return $query->whereIn('review_status', ['rejected', 'failed']);
                                }

                                return $query->where(function ($q) {
                                    $q->where('review_status', 'pending')
                                        ->orWhereNull('review_status')
                                        ->orWhere('review_status', '');
                                });
                            }
                        )
                        ->when(
                            filled($data['reviewed_year'] ?? null),
                            function (Builder $query) use ($data): Builder {
                                return $query->where(function (Builder $query) use ($data): void {
                                    $query->whereYear('created_at', $data['reviewed_year'])
                                        ->orWhereYear('reviewed_at', $data['reviewed_year']);
                                });
                            }
                        )
                        ->when(
                            filled($data['major'] ?? null),
                            fn (Builder $query): Builder => FormEntryData::applyJsonExactFilter($query, FormEntryData::majorKeys(), $data['major'])
                        )
                        ->when(
                            filled($data['reviewed_month'] ?? null),
                            fn (Builder $query): Builder => $query->whereMonth('reviewed_at', $data['reviewed_month'])
                        );
                }),
        ];
    }

    protected static function dynamicRequestReviewedYears(?string $formId): array
    {
        $rows = \Chanthoeun\FilamentCustomForms\Models\CustomFormEntry::query()
            ->when($formId, fn ($query) => $query->where('custom_form_id', $formId))
            ->get(['created_at', 'reviewed_at']);

        return $rows
            ->flatMap(function ($entry): array {
                $years = [];

                if ($entry->created_at) {
                    $years[] = \Carbon\Carbon::parse($entry->created_at)->format('Y');
                }

                if ($entry->reviewed_at) {
                    $years[] = \Carbon\Carbon::parse($entry->reviewed_at)->format('Y');
                }

                return $years;
            })
            ->filter()
            ->unique()
            ->sort()
            ->mapWithKeys(fn ($year): array => [(string) $year => (string) $year])
            ->toArray();
    }

    protected static function dynamicMajorOptions(?string $formId): array
    {
        return \Chanthoeun\FilamentCustomForms\Models\CustomFormEntry::query()
            ->when($formId, fn ($query) => $query->where('custom_form_id', $formId))
            ->get(['data'])
            ->map(function ($entry): string {
                return trim((string) (FormEntryData::firstFilled($entry->data, FormEntryData::majorKeys(), '')));
            })
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $value): array => [$value => $value])
            ->toArray();
    }

    protected static function getRecordActions(): array
    {
        $actions = [
            Action::make('edit_review_note')
                ->label(__('app.message'))
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->link()
                ->color('danger')
                ->modalHeading(__('app.message'))
                ->modalSubmitActionLabel(__('app.save'))
                ->modalCancelActionLabel(__('app.close'))
                ->fillForm(fn ($record): array => [
                    'review_note' => self::reviewMessage($record),
                ])
                ->form([
                    Textarea::make('review_note')
                        ->label(__('app.recommendation'))
                        ->required()
                        ->rows(5),
                ])
                ->action(function ($record, array $data): void {
                    $oldReviewNote = $record->review_note;

                    DB::table('custom_form_entries')
                        ->where('id', $record->id)
                        ->update([
                            'review_note' => $data['review_note'] ?? null,
                            'updated_at' => now(),
                        ]);

                    $record->refresh();

                    AuditLogger::log(
                        action: 'updated',
                        auditable: $record,
                        oldValues: ['review_note' => $oldReviewNote],
                        newValues: ['review_note' => $data['review_note'] ?? null],
                        description: 'Review note updated',
                        metadata: ['module' => 'Custom Form Entries'],
                    );

                    Notification::make()
                        ->title(__('app.message_saved'))
                        ->success()
                        ->send();
                })
                ->visible(fn ($record): bool =>
                    self::currentPanelIsAdmin()
                    && in_array(self::entryStatus($record), ['pending', 'rejected'], true)
                    && filled(self::reviewMessage($record))
                ),

            Action::make('view_review_note')
                ->label(__('app.message'))
                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                ->link()
                ->color('danger')
                ->modalHeading(__('app.message'))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel(__('app.close'))
                ->modalContent(function ($record): HtmlString {
                    return new HtmlString(
                        '<div class="rounded-lg border border-warning-200 bg-warning-50 p-4 text-sm leading-6 text-warning-900 dark:border-warning-800 dark:bg-warning-950 dark:text-warning-100">'
                        . nl2br(e(self::reviewMessage($record)))
                        . '</div>'
                    );
                })
                ->visible(fn ($record): bool =>
                    ! self::currentPanelIsAdmin()
                    && in_array(self::entryStatus($record), ['pending', 'rejected'], true)
                    && filled(self::reviewMessage($record))
                ),

            EditAction::make()
                ->label(function ($record): string {
                    return self::entryStatus($record) === 'draft'
                        ? __('student_profile.edit_draft')
                        : __('filament-actions::edit.single.label');
                })
                ->url(fn ($record): string => CustomFormEntryResource::getUrl('edit', [
                    'record' => $record,
                ]))
                ->visible(function ($record): bool {
                    if (self::currentPanelIsAdmin()) {
                        return false;
                    }

                    return in_array(self::entryStatus($record), ['draft', 'rejected'], true);
                }),

            Action::make('save_draft')
                ->label(__('student_profile.save_as_draft'))
                ->icon('heroicon-o-document-text')
                ->color('warning')
                ->link()
                ->action(function ($record): void {
                    $data = is_array($record->data)
                        ? $record->data
                        : json_decode((string) $record->data, true);

                    $data = is_array($data) ? $data : [];
                    $oldStatus = $record->review_status;
                    $oldRegistrationStatus = data_get($data, 'registration_status');
                    $data['registration_status'] = 'draft';

                    DB::table('custom_form_entries')
                        ->where('id', $record->id)
                        ->update([
                            'review_status' => 'draft',
                            'data' => json_encode($data),
                            'updated_at' => now(),
                        ]);

                    AuditLogger::log(
                        action: 'updated',
                        auditable: $record,
                        oldValues: [
                            'review_status' => $oldStatus,
                            'registration_status' => $oldRegistrationStatus,
                        ],
                        newValues: [
                            'review_status' => 'draft',
                            'registration_status' => 'draft',
                        ],
                        description: 'Custom form entry saved as draft',
                        metadata: ['module' => 'Custom Form Entries'],
                    );

                    redirect(CustomFormEntryResource::getUrl('create', [
                        'form_id' => $record->custom_form_id,
                        'draft_id' => $record->id,
                    ]));
                })
                ->visible(fn ($record): bool =>
                    auth()->user()?->registration_type === 'student'
                    && self::recordIsNationalExam($record)
                    && self::entryStatus($record) === 'draft'
                ),
        ];

        if (self::currentPanelIsAdmin()) {
            $actions[] = Action::make('view_template_pdf')
                ->label(__('review_applications.view_pdf'))
                ->icon('heroicon-o-eye')
                ->color('info')
                ->modalHeading('View Application Review')
                ->modalWidth('7xl')
                ->modalSubmitAction(false)
                ->modalCancelAction(false)
                ->modalContent(fn ($record) => view('custom-form-entry-pdf-modal', [
                    'record' => $record,
                    'pdfUrl' => route('admin.custom-form-entries.pdf-inline', [
                        'entry' => $record->id,
                    ]),
                ]))
                ->extraModalFooterActions(fn ($record): array => [
                    Action::make('approve_from_view')
                        ->label(__('review_applications.statuses.accepted'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn (): bool => self::entryStatus($record) === 'pending')
                        ->action(function () use ($record): void {
                            $data = is_array($record->data) ? $record->data : [];
                            $oldValues = [
                                'review_status' => $record->review_status,
                                'registration_status' => data_get($data, 'registration_status'),
                            ];
                            $data['candidate_status'] = 'pending';
                            $data['registration_status'] = 'approved';

                            DB::table('custom_form_entries')
                                ->where('id', $record->id)
                                ->update([
                                    'review_status' => 'approved',
                                    'review_note' => null,
                                    'reviewed_by' => auth()->id(),
                                    'reviewed_at' => now(),
                                    'updated_at' => now(),
                                    'data' => json_encode($data),
                                ]);

                            $record->refresh();

                            AuditLogger::log(
                                action: 'approved',
                                auditable: $record,
                                oldValues: $oldValues,
                                newValues: [
                                    'review_status' => 'approved',
                                    'registration_status' => 'approved',
                                ],
                                description: 'Custom form entry approved',
                                metadata: ['module' => 'Custom Form Entries'],
                            );

                            self::notifyStudentNationalExamResult($record, 'approved', null);

                            Notification::make()
                                ->title(__('review_applications.notifications.admin_accept_success_title'))
                                ->success()
                                ->send();

                            redirect(request()->header('Referer') ?: request()->fullUrl());
                        }),

                    Action::make('reject_from_view')
                        ->label(__('review_applications.statuses.send_back'))
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('danger')
                        ->form([
                            Textarea::make('review_note')
                                ->label(__('review_applications.review_note'))
                                ->required()
                                ->rows(4),
                        ])
                        ->visible(fn (): bool => self::entryStatus($record) === 'pending')
                        ->action(function (array $data) use ($record): void {
                            $recordData = is_array($record->data) ? $record->data : [];
                            $oldValues = [
                                'review_status' => $record->review_status,
                                'registration_status' => data_get($recordData, 'registration_status'),
                                'review_note' => $record->review_note,
                            ];
                            $recordData['registration_status'] = 'rejected';

                            DB::table('custom_form_entries')
                                ->where('id', $record->id)
                                ->update([
                                    'review_status' => 'rejected',
                                    'review_note' => $data['review_note'] ?? null,
                                    'reviewed_by' => auth()->id(),
                                    'reviewed_at' => now(),
                                    'updated_at' => now(),
                                    'data' => json_encode($recordData),
                                ]);

                            $record->refresh();

                            AuditLogger::log(
                                action: 'rejected',
                                auditable: $record,
                                oldValues: $oldValues,
                                newValues: [
                                    'review_status' => 'rejected',
                                    'registration_status' => 'rejected',
                                    'review_note' => $data['review_note'] ?? null,
                                ],
                                description: 'Custom form entry rejected',
                                metadata: ['module' => 'Custom Form Entries'],
                            );

                            self::notifyStudentNationalExamResult($record, 'rejected', $data['review_note'] ?? null);

                            Notification::make()
                                ->title(__('review_applications.notifications.admin_reject_success_title'))
                                ->danger()
                                ->send();

                            redirect(request()->header('Referer') ?: request()->fullUrl());
                        }),
                ])
                ->visible(fn ($record): bool =>
                    self::currentPanelIsAdmin()
                    && ! self::isProfileForm($record->custom_form_id)
                    && self::entryStatus($record) === 'pending'
                    && self::hasDocumentTemplate($record)
                );

            $actions[] = Action::make('accepted')
                ->label(__('review_applications.statuses.accepted'))
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn ($record): bool =>
                    self::currentPanelIsAdmin()
                    && ! self::isProfileForm($record->custom_form_id)
                    && self::entryStatus($record) === 'pending'
                    && ! self::hasDocumentTemplate($record)
                )
                ->action(function ($record): void {
                    $data = is_array($record->data) ? $record->data : [];
                    $oldValues = [
                        'review_status' => $record->review_status,
                        'registration_status' => data_get($data, 'registration_status'),
                    ];
                    $data['candidate_status'] = 'pending';
                    $data['registration_status'] = 'approved';

                    DB::table('custom_form_entries')
                        ->where('id', $record->id)
                        ->update([
                            'review_status' => 'approved',
                            'review_note' => null,
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                            'updated_at' => now(),
                            'data' => json_encode($data),
                        ]);

                    $record->refresh();

                    AuditLogger::log(
                        action: 'approved',
                        auditable: $record,
                        oldValues: $oldValues,
                        newValues: [
                            'review_status' => 'approved',
                            'registration_status' => 'approved',
                        ],
                        description: 'Custom form entry approved',
                        metadata: ['module' => 'Custom Form Entries'],
                    );

                    self::notifyStudentNationalExamResult($record, 'approved', null);

                    Notification::make()
                        ->title(__('review_applications.notifications.admin_accept_success_title'))
                        ->success()
                        ->send();
                });

            $actions[] = Action::make('rejected')
                ->label(__('review_applications.statuses.rejected'))
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn ($record): bool =>
                    self::currentPanelIsAdmin()
                    && ! self::isProfileForm($record->custom_form_id)
                    && self::entryStatus($record) === 'pending'
                    && ! self::hasDocumentTemplate($record)
                )
                ->form([
                    Textarea::make('review_note')
                        ->label(__('review_applications.review_note'))
                        ->required()
                        ->rows(4),
                ])
                ->action(function ($record, array $data): void {
                    $recordData = is_array($record->data) ? $record->data : [];
                    $oldValues = [
                        'review_status' => $record->review_status,
                        'registration_status' => data_get($recordData, 'registration_status'),
                        'review_note' => $record->review_note,
                    ];
                    $recordData['registration_status'] = 'rejected';

                    DB::table('custom_form_entries')
                        ->where('id', $record->id)
                        ->update([
                            'review_status' => 'rejected',
                            'review_note' => $data['review_note'] ?? null,
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                            'updated_at' => now(),
                            'data' => json_encode($recordData),
                        ]);

                    $record->refresh();

                    AuditLogger::log(
                        action: 'rejected',
                        auditable: $record,
                        oldValues: $oldValues,
                        newValues: [
                            'review_status' => 'rejected',
                            'registration_status' => 'rejected',
                            'review_note' => $data['review_note'] ?? null,
                        ],
                        description: 'Custom form entry rejected',
                        metadata: ['module' => 'Custom Form Entries'],
                    );

                    self::notifyStudentNationalExamResult($record, 'rejected', $data['review_note'] ?? null);

                    Notification::make()
                        ->title(__('review_applications.notifications.admin_reject_success_title'))
                        ->danger()
                        ->send();
                });
        }

        if (class_exists(\Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate::class)) {
            $actions[] = \Chanthoeun\FilamentDocumentBuilder\Tables\Actions\DownloadPdfAction::make('download_pdf')
                ->label(__('review_applications.download_pdf'))
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->templateType(function ($record) {
                    $formSelection = strtolower((string) data_get($record->data, 'form_selection'));

                    if (filled($formSelection)) {
                        $subForm = \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
                            ->where('custom_form_id', $record->custom_form_id)
                            ->where('menu_placement', 'sub_item')
                            ->where('sub_item_type', $formSelection)
                            ->first();

                        if ($subForm) {
                            return 'custom_form_' . $subForm->id;
                        }
                    }

                    return 'custom_form_' . $record->custom_form_id;
                })
                ->filename(fn ($record) => 'document-' . $record->id . '.pdf')
                ->visible(fn ($record): bool => self::canDownloadPdf($record));
        }

        return $actions;
    }
    protected static function canEdit($record): bool
    {
        $status = self::entryStatus($record);
        $slug = $record->customForm?->slug;

        if ($status === 'draft') {
            return true;
        }

        if ($slug === 'profile') {
            return ! self::studentHasAcceptedNationalExam();
        }

        return in_array($status, [
            '',
            'failed',
            'rejected',
        ], true);
    }

    protected static function studentHasAcceptedNationalExam(): bool
    {
        $userId = auth()->id();

        if (! $userId) {
            return false;
        }

        $nationalExamFormId = \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
            ->where('slug', 'national-examination-registration')
            ->value('id');

        if (! $nationalExamFormId) {
            return false;
        }

        return \Chanthoeun\FilamentCustomForms\Models\CustomFormEntry::query()
            ->where('custom_form_id', $nationalExamFormId)
            ->whereIn('review_status', ['passed', 'accepted', 'approved'])
            ->where(function ($query) use ($userId): void {
                if (Schema::hasColumn('custom_form_entries', 'created_by')) {
                    $query->orWhere('created_by', $userId);
                }

                if (Schema::hasColumn('custom_form_entries', 'user_id')) {
                    $query->orWhere('user_id', $userId);
                }

                if (Schema::hasColumn('custom_form_entries', 'created_by_id')) {
                    $query->orWhere('created_by_id', $userId);
                }
            })
            ->exists();
    }

    protected static function canDownloadPdf($record): bool
    {
        $status = self::entryStatus($record);

        return in_array($status, [
            'passed',
            'accepted',
            'approved',
        ], true);
    }

    protected static function hasDocumentTemplate($record): bool
    {
        if (! class_exists(\Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate::class)) {
            return false;
        }

        $formSelection = strtolower((string) data_get($record->data, 'form_selection'));

        if (filled($formSelection)) {
            $subForm = \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
                ->where('custom_form_id', $record->custom_form_id)
                ->where('menu_placement', 'sub_item')
                ->where('sub_item_type', $formSelection)
                ->first();

            if ($subForm) {
                return \Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate::query()
                    ->where('type', 'custom_form_' . $subForm->id)
                    ->exists();
            }
        }

        return \Chanthoeun\FilamentDocumentBuilder\Models\DocumentTemplate::query()
            ->where('type', 'custom_form_' . $record->custom_form_id)
            ->exists();
    }

    protected static function currentPanelIsAdmin(): bool
    {
        return CustomFormEntryResource::currentUserCanManageForms();
    }

    protected static function applyQueryConstraints(Builder $query, ?string $formId): Builder
    {
        $query->with(['creator', 'customForm']);

        if ($formId) {
            $query->where('custom_form_id', $formId);
        } else {
            $query->whereHas('customForm', function (Builder $query): void {
                $query->where('slug', '!=', 'profile');
            });
        }

        if (! self::currentPanelIsAdmin()) {
            $userId = auth()->id();

            if (! $userId) {
                return $query->whereRaw('1 = 0');
            }

            $ownerColumns = collect(['created_by', 'user_id', 'created_by_id'])
                ->filter(fn (string $column): bool => Schema::hasColumn('custom_form_entries', $column))
                ->values()
                ->all();

            if ($ownerColumns === []) {
                return $query->whereRaw('1 = 0');
            }

            $query->where(function (Builder $query) use ($ownerColumns, $userId): void {
                foreach ($ownerColumns as $ownerColumn) {
                    $query->orWhere($ownerColumn, $userId);
                }
            });

            if (self::studentListShouldUseSingleEntryWorkflow($formId)) {
                $driver = DB::connection()->getDriverName();
                $formSelectionExpression = $driver === 'pgsql'
                    ? "COALESCE(student_entries.data->>'form_selection', '') = COALESCE(custom_form_entries.data->>'form_selection', '')"
                    : "COALESCE(JSON_UNQUOTE(JSON_EXTRACT(student_entries.data, '$.form_selection')), '') = COALESCE(JSON_UNQUOTE(JSON_EXTRACT(custom_form_entries.data, '$.form_selection')), '')";

                $ownerConditions = collect($ownerColumns)
                    ->map(fn (string $column): string => "student_entries.{$column} = ?")
                    ->implode(' OR ');

                return $query->whereRaw(
                    "custom_form_entries.id = (
                        SELECT MAX(student_entries.id)
                        FROM custom_form_entries AS student_entries
                        WHERE student_entries.custom_form_id = custom_form_entries.custom_form_id
                          AND {$formSelectionExpression}
                          AND ({$ownerConditions})
                    )",
                    array_fill(0, count($ownerColumns), $userId),
                );
            }

            return $query->latest('id');
        }

        if (self::currentPanelIsAdmin()) {
            $query->where(function (Builder $query): void {
                $query
                    ->whereNull('review_status')
                    ->orWhere('review_status', '!=', 'draft');
            })
                ->where(function (Builder $query): void {
                    $query
                        ->whereNull('data->registration_status')
                        ->orWhere('data->registration_status', '!=', 'draft');
                });
        }

        return $query;
    }

    protected static function studentListShouldUseSingleEntryWorkflow(?string $formId): bool
    {
        if (! $formId) {
            return false;
        }

        $customForm = \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
            ->find($formId, ['id', 'slug']);

        if (! $customForm) {
            return false;
        }

        return (string) $customForm->slug === 'profile';
    }

    protected static function recordIsNationalExam($record): bool
    {
        return $record->customForm?->slug === 'national-examination-registration'
            || (int) $record->custom_form_id === (int) \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
                ->where('slug', 'national-examination-registration')
                ->value('id');
    }

    protected static function notifyStudentNationalExamResult($record, string $status, ?string $note = null): void
    {
        $student = self::getOwnerStudent($record);

        if (! $student) {
            return;
        }

        $studentLocale = NotificationLanguage::localeForUser($student);

        $formName = $record->customForm
            ? ($record->customForm->display_name ?: NotificationLanguage::transForUser($student, 'app.custom_form_entry_ui.notifications.application'))
            : NotificationLanguage::transForUser($student, 'app.custom_form_entry_ui.notifications.application');
        $requiresPayment = self::entryRequiresPayment($record);

        if ($status === 'approved') {
            Notification::make()
                ->title(
                    self::recordIsNationalExam($record)
                        ? NotificationLanguage::transForUser($student, 'review_applications.notifications.national_exam_approved_title')
                        : NotificationLanguage::transForUser($student, 'app.custom_form_entry_ui.notifications.application_approved_title', ['form' => $formName])
                )
                ->body(
                    self::recordIsNationalExam($record)
                        ? NotificationLanguage::transForUser($student, 'review_applications.notifications.national_exam_approved_body')
                        : NotificationLanguage::transForUser(
                            $student,
                            $requiresPayment
                                ? 'app.custom_form_entry_ui.notifications.application_approved_body'
                                : 'app.custom_form_entry_ui.notifications.application_approved_body_no_payment',
                            ['form' => $formName]
                        )
                )
                ->icon('heroicon-o-check-circle')
                ->iconColor('success')
                ->success()
                ->sendToDatabase($student);

            return;
        }

        Notification::make()
            ->title(
                self::recordIsNationalExam($record)
                    ? NotificationLanguage::transForUser($student, 'review_applications.notifications.national_exam_rejected_title')
                    : NotificationLanguage::transForUser($student, 'app.custom_form_entry_ui.notifications.application_rejected_title', ['form' => $formName])
            )
            ->body(
                self::recordIsNationalExam($record)
                    ? NotificationLanguage::transForUser($student, 'review_applications.notifications.national_exam_rejected_body', [
                        'note' => filled($note) ? $note : NotificationLanguage::transForUser($student, 'review_applications.notifications.no_reject_note'),
                    ])
                    : new HtmlString(NotificationLanguage::transForUser($student, 'app.custom_form_entry_ui.notifications.application_rejected_body', [
                        'form' => e($formName),
                        'note' => e(filled($note)
                            ? $note
                            : NotificationLanguage::transForUser($student, 'app.custom_form_entry_ui.notifications.no_note')),
                    ]))
            )
            ->actions(array_filter([
                self::studentEditNotificationAction($record, $studentLocale),
            ]))
            ->icon('heroicon-o-x-circle')
            ->iconColor('danger')
            ->danger()
            ->sendToDatabase($student);
    }

    protected static function entryRequiresPayment($record): bool
    {
        return (bool) ($record->customForm?->requires_payment ?? true);
    }

    protected static function studentEditNotificationAction($record, string $locale): ?Action
    {
        $url = self::studentEditFormUrl($record);

        if (blank($url)) {
            return null;
        }

        return Action::make('edit_form')
            ->label(__('app.custom_form_entry_ui.actions.edit_form', [], $locale))
            ->button()
            ->color('danger')
            ->url($url);
    }

    protected static function studentPaymentNotificationAction(string $locale): ?Action
    {
        $url = self::studentPaymentListUrl();

        if (blank($url)) {
            return null;
        }

        return Action::make('open_payment_lists')
            ->label(__('app.custom_form_entry_ui.actions.go_to_payment_lists', [], $locale))
            ->button()
            ->color('success')
            ->url($url);
    }

    protected static function studentEditFormUrl($record): ?string
    {
        if (! filled($record?->id)) {
            return null;
        }

        return CustomFormEntryResource::getUrl('edit', [
            'record' => $record,
        ], panel: 'app');
    }

    protected static function studentPaymentListUrl(): ?string
    {
        return CandidatePaymentListResource::getUrl(panel: 'app');
    }

    protected static function getOwnerStudent($record): ?User
    {
        if (! Schema::hasTable('users')) {
            return null;
        }

        foreach (['created_by', 'user_id', 'created_by_id'] as $column) {
            if (Schema::hasColumn('custom_form_entries', $column) && filled($record->{$column})) {
                return User::query()
                    ->where('id', $record->{$column})
                    ->where('registration_type', 'student')
                    ->first();
            }
        }

        return null;
    }

    protected static function transText(mixed $value): string
    {
        $locale = strtolower((string) app()->getLocale());

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

    protected static function formTypeLabel(?string $state, ?string $parentFormId = null): string
    {
        if (blank($state)) {
            return '-';
        }

        $locale = app()->getLocale();

        $subForm = \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
            ->where('menu_placement', 'sub_item')
            ->where('sub_item_type', $state)
            ->when($parentFormId, fn ($query) => $query->where('custom_form_id', $parentFormId))
            ->first();

        if ($subForm) {
            return self::transText($subForm->name);
        }

        return match ((string) $state) {
            'associate' => __('app.custom_form_entry_ui.options.form_type.associate'),
            'bachelor' => __('app.custom_form_entry_ui.options.form_type.bachelor'),
            'master' => __('app.custom_form_entry_ui.options.form_type.master'),
            'phd' => __('app.custom_form_entry_ui.options.form_type.phd'),
            default => filled($state) ? ucfirst((string) $state) : '-',
        };
    }

    protected static function entryOptionLabel($record, string $key, mixed $state): string
    {
        if (blank($state)) {
            return '-';
        }

        $formIds = array_filter([
            $record?->custom_form_id,
            $record?->customForm?->custom_form_id,
        ]);

        $formIds = self::formIdsForOptionLookup($formIds);

        $field = \Chanthoeun\FilamentCustomForms\Models\CustomFormField::query()
            ->whereIn('custom_form_id', $formIds)
            ->where('name', $key)
            ->whereNotNull('options')
            ->first();

        $label = self::fieldOptionLabel($field, $state, $formIds, $key);

        if ($label !== '-' && $label !== (string) $state) {
            return $label;
        }

        return self::globalFieldOptionLabel($key, $state) ?? $label;
    }

    protected static function fieldOptionLabel($field, mixed $state, array $formIds = [], ?string $fieldName = null): string
    {
        if (blank($state)) {
            return '-';
        }

        $choices = $field ? self::fieldChoices($field) : [];

        $labels = collect(self::decodeJsonArray($state))
            ->map(function (mixed $value) use ($choices, $formIds): string {
                $stringValue = (string) $value;

                return $choices[$stringValue]
                    ?? self::optionLabelForValue($formIds, $stringValue, $fieldName)
                    ?? $stringValue;
            })
            ->filter(fn (string $value): bool => filled($value))
            ->values();

        return $labels->isNotEmpty() ? $labels->join(', ') : '-';
    }

    protected static function fieldChoices($field): array
    {
        $options = is_array($field->options)
            ? $field->options
            : json_decode((string) $field->options, true);

        $choices = $options['choices'] ?? [];

        if (! is_array($choices)) {
            return [];
        }

        return collect($choices)
            ->mapWithKeys(function (mixed $label, mixed $value): array {
                if (is_array($label) && array_key_exists('value', $label)) {
                    return [
                        (string) $label['value'] => self::transText($label['label'] ?? $label['value']),
                    ];
                }

                return [
                    (string) $value => self::transText($label),
                ];
            })
            ->toArray();
    }

    protected static function decodeJsonArray(mixed $state): array
    {
        if (is_string($state) && str_starts_with(trim($state), '[')) {
            $decoded = json_decode($state, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [$state];
    }

    protected static function optionLabelForValue(array $formIds, string $value, ?string $fieldName = null): ?string
    {
        $fields = collect();

        if (! empty($formIds)) {
            $fields = \Chanthoeun\FilamentCustomForms\Models\CustomFormField::query()
                ->whereIn('custom_form_id', $formIds)
                ->when($fieldName, fn ($query) => $query->where('name', $fieldName))
                ->whereNotNull('options')
                ->orderBy('sort')
                ->get();
        }

        foreach ($fields as $field) {
            $choices = self::fieldChoices($field);

            if (array_key_exists($value, $choices)) {
                return $choices[$value];
            }
        }

        if ($fieldName) {
            return self::globalOptionLabelForFieldName($fieldName, $value);
        }

        return null;
    }

    protected static function globalFieldOptionLabel(string $fieldName, mixed $state): ?string
    {
        if (blank($fieldName) || blank($state)) {
            return null;
        }

        return collect(self::decodeJsonArray($state))
            ->map(fn (mixed $value): ?string => self::globalOptionLabelForFieldName($fieldName, (string) $value))
            ->filter(fn (?string $value): bool => filled($value))
            ->values()
            ->whenEmpty(fn ($collection) => $collection->push(null))
            ->join(', ') ?: null;
    }

    protected static function globalOptionLabelForFieldName(string $fieldName, string $value): ?string
    {
        $fields = \Chanthoeun\FilamentCustomForms\Models\CustomFormField::query()
            ->where('name', $fieldName)
            ->whereNotNull('options')
            ->orderBy('sort')
            ->get();

        foreach ($fields as $field) {
            $choices = self::fieldChoices($field);

            if (array_key_exists($value, $choices)) {
                return $choices[$value];
            }
        }

        return null;
    }

    protected static function formatChoiceState(array $choices, mixed $state): string
    {
        if (blank($state)) {
            return '-';
        }

        $transChoices = collect($choices)
            ->mapWithKeys(function ($label, $key): array {
                if (is_array($label) && array_key_exists('value', $label)) {
                    return [
                        (string) $label['value'] => self::transText($label['label'] ?? $label['value']),
                    ];
                }

                return [
                    (string) $key => self::transText($label),
                ];
            })
            ->toArray();

        return collect(self::decodeJsonArray($state))
            ->map(fn ($value): string => $transChoices[(string) $value] ?? (string) $value)
            ->join(', ');
    }

    protected static function formIdsForOptionLookup(array $formIds): array
    {
        $ids = collect($formIds)
            ->filter()
            ->map(fn (mixed $id): int => (int) $id)
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $childIds = \Chanthoeun\FilamentCustomForms\Models\CustomForm::query()
            ->whereIn('custom_form_id', $ids->all())
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id);

        return $ids
            ->merge($childIds)
            ->unique()
            ->values()
            ->all();
    }

    protected static function isImageFieldType(string $type): bool
    {
        return in_array($type, ['image', 'image_upload'], true);
    }

    protected static function isGeoColumn(string $key): bool
    {
        return in_array($key, [
            'birth_province_city',
            'birth_district_khan',
            'birth_commune_sangkat',
            'birth_village',

            'current_capital_province',
            'current_district_khan',
            'current_commune_sangkat',
            'current_village',

            'parents_capital_province',
            'parents_district_khan',
            'parents_commune_sangkat',
            'parents_village',
        ], true);
    }

    protected static function geoLocationName(mixed $state): string
    {
        if (blank($state)) {
            return '-';
        }

        $location = GeoLocation::query()->find($state);

        if (! $location) {
            return (string) $state;
        }

        return app()->getLocale() === 'km'
            ? ($location->name_kh ?: $location->name_en ?: '-')
            : ($location->name_en ?: $location->name_kh ?: '-');
    }
}
