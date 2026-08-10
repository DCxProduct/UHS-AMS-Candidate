<?php

namespace App\Filament\Admin\Resources\CandidatePaymentLists\Tables;

use App\Filament\Admin\Resources\CandidatePaymentLists\CandidatePaymentListResource;
use App\Models\CandidatePaymentList;
use App\Models\Payment;
use App\Support\LocalizedNumber;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Schema;

class CandidatePaymentListsTable
{
    public static function downloadExcel(iterable $records, ?array $columnKeys = null)
    {
        $filename = 'payment-lists-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/' . uniqid('payment-lists-', true) . '.xlsx');

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

    public static function configure(Table $table): Table
    {
        return $table
            ->selectable()
            ->searchPlaceholder(__('payments.search'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('row_number')
                    ->label(__('candidate_payment_lists.columns.no'))
                    ->rowIndex()
                    ->formatStateUsing(fn ($state): string => LocalizedNumber::digits($state))
                    ->alignCenter()
                    ->width('60px')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('form_name')
                    ->label(__('candidate_payment_lists.columns.application_form_type'))
                    ->badge()
                    ->getStateUsing(fn (CandidatePaymentList $record): string => self::localizedFormName($record->customForm?->name))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->whereHas('customForm', function (Builder $formQuery) use ($search): void {
                            $formQuery->where('name', 'like', "%{$search}%");
                        }))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('name_khmer')
                    ->label(__('candidate_payment_lists.columns.name_khmer'))
                    ->getStateUsing(fn (CandidatePaymentList $record): string => self::khmerName($record))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('data->first_name_kh', 'like', "%{$search}%")
                        ->orWhere('data->last_name_kh', 'like', "%{$search}%")
                        ->orWhere('data->full_name_kh', 'like', "%{$search}%"))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('name_latin')
                    ->label(__('candidate_payment_lists.columns.name_latin'))
                    ->getStateUsing(fn (CandidatePaymentList $record): string => self::latinName($record))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('data->first_name_en', 'like', "%{$search}%")
                        ->orWhere('data->last_name_en', 'like', "%{$search}%")
                        ->orWhere('data->full_name_en', 'like', "%{$search}%"))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('gender')
                    ->label(__('candidate_payment_lists.columns.gender'))
                    ->getStateUsing(fn (CandidatePaymentList $record): string => self::genderLabel(self::entryValue($record, 'gender')))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('phone_number')
                    ->label(__('candidate_payment_lists.columns.phone_number'))
                    ->getStateUsing(fn (CandidatePaymentList $record): string => self::entryValue($record, 'phone_number', $record->creator?->phone))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('date_of_birth')
                    ->label(__('candidate_payment_lists.columns.date_of_birth'))
                    ->getStateUsing(fn (CandidatePaymentList $record): string => self::dateOfBirth($record))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('major')
                    ->label(__('candidate_payment_lists.columns.major'))
                    ->getStateUsing(fn (CandidatePaymentList $record): string => self::entryValue(
                        $record,
                        filled(data_get($record->data, 'selected_major')) ? 'selected_major' : 'degree_level_major'
                    ))
                    ->badge()
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('data->selected_major', 'like', "%{$search}%")
                        ->orWhere('data->degree_level_major', 'like', "%{$search}%"))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('academic_year')
                    ->label(__('candidate_payment_lists.columns.academic_year'))
                    ->getStateUsing(fn (CandidatePaymentList $record): string => self::entryValue($record, 'academic_year', $record->creator?->academic_year))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('status_payt')
                    ->label(__('candidate_payment_lists.columns.payment_status'))
                    ->badge()
                    ->getStateUsing(fn (CandidatePaymentList $record): string => self::paymentStatus($record))
                    ->formatStateUsing(fn (?string $state): string => strtolower((string) $state) === 'paid'
                        ? __('payments.options.status_payt.paid')
                        : __('payments.options.status_payt.unpaid'))
                    ->color(fn (?string $state): string => match (strtolower((string) $state)) {
                        'paid' => 'success',
                        default => 'warning',
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                Filter::make('candidate_payment_list_filters')
                    ->label(new HtmlString('&nbsp;'))
                    ->schema([
                        Select::make('form_id')
                            ->label(__('candidate_payment_lists.columns.application_form_type'))
                            ->options(fn (): array => self::dynamicFormOptions())
                            ->native(false)
                            ->searchable()
                            ->live(),

                        Select::make('major')
                            ->label(__('candidate_payment_lists.columns.major'))
                            ->options(fn (): array => self::dynamicMajorOptions())
                            ->native(false)
                            ->searchable()
                            ->live(),

                        Select::make('academic_year')
                            ->label(__('candidate_payment_lists.columns.academic_year'))
                            ->options(fn (): array => self::dynamicAcademicYearOptions())
                            ->native(false)
                            ->searchable()
                            ->live(),
                    ])
                    ->columns(4)
                    ->columnSpanFull()
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['form_id'] ?? null),
                                fn (Builder $query): Builder => self::applyFormFilter($query, (string) $data['form_id'])
                            )
                            ->when(
                                filled($data['major'] ?? null),
                                fn (Builder $query): Builder => $query->where(function (Builder $majorQuery) use ($data): void {
                                    $majorQuery->where('data->selected_major', $data['major'])
                                        ->orWhere('data->degree_level_major', $data['major']);
                                })
                            )
                            ->when(
                                filled($data['academic_year'] ?? null),
                                fn (Builder $query): Builder => $query->where(function (Builder $academicYearQuery) use ($data): void {
                                    $academicYearQuery->where('data->academic_year', $data['academic_year'])
                                        ->orWhereHas('creator', fn (Builder $creatorQuery): Builder => $creatorQuery->where('academic_year', $data['academic_year']));
                                })
                            );
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->filtersFormColumns(3)
            ->recordActions([]);
    }

    protected static function excelRows(iterable $records, ?array $columnKeys = null): array
    {
        $columnKeys ??= array_keys(self::exportColumnDefinitions());
        $rows = [self::excelHeadings($columnKeys)];
        $rowNumber = 1;

        foreach ($records as $record) {
            if (! $record instanceof CandidatePaymentList) {
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
            if (! $record instanceof CandidatePaymentList) {
                continue;
            }

            $rows[] = self::cleanDataRow($record, $rowNumber++, $columnKeys);
        }

        return $rows;
    }

    protected static function exportColumnDefinitions(): array
    {
        return [
            'row_number' => [
                'label' => __('candidate_payment_lists.columns.no'),
                'field_key' => 'row_number',
            ],
            'form_name' => [
                'label' => __('candidate_payment_lists.columns.application_form_type'),
                'field_key' => 'form_name',
            ],
            'name_khmer' => [
                'label' => __('candidate_payment_lists.columns.name_khmer'),
                'field_key' => 'name_khmer',
            ],
            'name_latin' => [
                'label' => __('candidate_payment_lists.columns.name_latin'),
                'field_key' => 'name_latin',
            ],
            'gender' => [
                'label' => __('candidate_payment_lists.columns.gender'),
                'field_key' => 'gender',
            ],
            'phone_number' => [
                'label' => __('candidate_payment_lists.columns.phone_number'),
                'field_key' => 'phone_number',
            ],
            'date_of_birth' => [
                'label' => __('candidate_payment_lists.columns.date_of_birth'),
                'field_key' => 'date_of_birth',
            ],
            'major' => [
                'label' => __('candidate_payment_lists.columns.major'),
                'field_key' => 'major',
            ],
            'academic_year' => [
                'label' => __('candidate_payment_lists.columns.academic_year'),
                'field_key' => 'academic_year',
            ],
            'status_payt' => [
                'label' => __('candidate_payment_lists.columns.payment_status'),
                'field_key' => 'status_payt',
            ],
        ];
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

    protected static function exportRow(CandidatePaymentList $record, int $rowNumber, array $columnKeys): array
    {
        $values = [
            'row_number' => (string) $rowNumber,
            'form_name' => self::localizedFormName($record->customForm?->name),
            'name_khmer' => self::khmerName($record),
            'name_latin' => self::latinName($record),
            'gender' => self::genderLabel(self::entryValue($record, 'gender')),
            'phone_number' => self::entryValue($record, 'phone_number', $record->creator?->phone),
            'date_of_birth' => self::dateOfBirth($record),
            'major' => self::entryValue(
                $record,
                filled(data_get($record->data, 'selected_major')) ? 'selected_major' : 'degree_level_major'
            ),
            'academic_year' => self::entryValue($record, 'academic_year', $record->creator?->academic_year),
            'status_payt' => strtolower(self::paymentStatus($record)) === 'paid'
                ? __('payments.options.status_payt.paid')
                : __('payments.options.status_payt.unpaid'),
        ];

        return collect($columnKeys)
            ->filter(fn (string $key): bool => array_key_exists($key, $values))
            ->map(fn (string $key): string => (string) ($values[$key] ?? ''))
            ->values()
            ->all();
    }

    protected static function cleanDataRow(CandidatePaymentList $record, int $rowNumber, array $columnKeys): array
    {
        $values = [
            'row_number' => (string) $rowNumber,
            'form_name' => (string) $record->custom_form_id,
            'name_khmer' => trim((string) data_get($record->data, 'full_name_kh')) ?: self::khmerName($record),
            'name_latin' => trim((string) data_get($record->data, 'full_name_en')) ?: self::latinName($record),
            'gender' => self::entryValue($record, 'gender'),
            'phone_number' => self::entryValue($record, 'phone_number', $record->creator?->phone),
            'date_of_birth' => self::entryValue($record, 'date_of_birth', $record->creator?->date_of_birth),
            'major' => self::entryValue(
                $record,
                filled(data_get($record->data, 'selected_major')) ? 'selected_major' : 'degree_level_major'
            ),
            'academic_year' => self::entryValue($record, 'academic_year', $record->creator?->academic_year),
            'status_payt' => strtolower(self::paymentStatus($record)),
        ];

        return collect($columnKeys)
            ->filter(fn (string $key): bool => array_key_exists($key, $values))
            ->map(fn (string $key): string => (string) ($values[$key] ?? ''))
            ->values()
            ->all();
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
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>';

        foreach (array_values($sheets) as $index => $_sheet) {
            $xml .= '<Override PartName="/xl/worksheets/sheet' . ($index + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return $xml . '</Types>';
    }

    protected static function workbookXml(array $sheets): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';

        foreach (array_values($sheets) as $index => $sheet) {
            $name = self::xmlValue($sheet['name'] ?? ('Sheet ' . ($index + 1)));
            $xml .= '<sheet name="' . $name . '" sheetId="' . ($index + 1) . '" r:id="rId' . ($index + 1) . '"/>';
        }

        return $xml . '</sheets></workbook>';
    }

    protected static function workbookRelsXml(array $sheets): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';

        foreach (array_values($sheets) as $index => $_sheet) {
            $xml .= '<Relationship Id="rId' . ($index + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ($index + 1) . '.xml"/>';
        }

        return $xml . '</Relationships>';
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
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    protected static function entryValue(?CandidatePaymentList $record, string $key, mixed $fallback = null): string
    {
        $value = data_get($record?->data, $key);

        if (blank($value)) {
            $value = $fallback;
        }

        return blank($value) ? '-' : (string) $value;
    }

    protected static function khmerName(CandidatePaymentList $record): string
    {
        $splitName = trim(collect([
            data_get($record->data, 'first_name_kh'),
            data_get($record->data, 'last_name_kh'),
        ])->filter()->join(' '));

        if (filled($splitName)) {
            return $splitName;
        }

        $fullName = trim((string) data_get($record->data, 'full_name_kh'));

        return filled($fullName) ? $fullName : '-';
    }

    protected static function latinName(CandidatePaymentList $record): string
    {
        $splitName = trim(collect([
            data_get($record->data, 'first_name_en'),
            data_get($record->data, 'last_name_en'),
        ])->filter()->join(' '));

        if (filled($splitName)) {
            return strtoupper($splitName);
        }

        $fullName = trim((string) data_get($record->data, 'full_name_en'));

        return filled($fullName) ? strtoupper($fullName) : '-';
    }

    protected static function paymentStatus(CandidatePaymentList $record): string
    {
        $payment = self::latestPaymentRecord($record);

        if (! $payment) {
            return 'unpaid';
        }

        return strtolower((string) $payment->status_payt) === 'paid' ? 'paid' : 'unpaid';
    }

    protected static function ownerId(CandidatePaymentList $record): ?int
    {
        foreach (['created_by', 'user_id', 'created_by_id'] as $column) {
            $value = data_get($record, $column);

            if (filled($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    protected static function applyPaymentOwnerMatch(QueryBuilder $query): QueryBuilder
    {
        $ownerColumns = collect(['created_by', 'user_id', 'created_by_id'])
            ->filter(fn (string $column): bool => Schema::hasColumn('custom_form_entries', $column))
            ->values();

        if ($ownerColumns->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        $firstColumn = $ownerColumns->shift();

        $query->whereColumn('payments.users_id', "custom_form_entries.{$firstColumn}");

        foreach ($ownerColumns as $column) {
            $query->orWhereColumn('payments.users_id', "custom_form_entries.{$column}");
        }

        return $query;
    }

    protected static function latestPaymentRecord(CandidatePaymentList $record): ?Payment
    {
        $ownerId = self::ownerId($record);

        if (! $ownerId) {
            return null;
        }

        return Payment::query()
            ->where('users_id', $ownerId)
            ->where('form_id', $record->custom_form_id)
            ->latest('id')
            ->first();
    }

    protected static function localizedFormName(mixed $name): string
    {
        if (blank($name)) {
            return '-';
        }

        if (is_array($name)) {
            return self::translationValue($name);
        }

        if (is_object($name)) {
            return self::translationValue(json_decode(json_encode($name), true) ?: []);
        }

        if (is_string($name)) {
            $decoded = json_decode($name, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return self::translationValue($decoded);
            }

            return $name;
        }

        return (string) $name;
    }

    protected static function translationValue(array $translations): string
    {
        $locale = app()->getLocale();

        if (in_array($locale, ['km', 'kh'], true)) {
            return (string) ($translations['km'] ?? $translations['kh'] ?? $translations['en'] ?? '-');
        }

        return (string) ($translations['en'] ?? $translations['km'] ?? $translations['kh'] ?? '-');
    }

    protected static function dateOfBirth(CandidatePaymentList $record): string
    {
        $value = self::entryValue($record, 'date_of_birth', $record->creator?->date_of_birth);

        if ($value === '-') {
            return '-';
        }

        $timestamp = strtotime($value);

        return $timestamp ? date('d-m-Y', $timestamp) : $value;
    }

    protected static function dynamicFormOptions(): array
    {
        $options = [];

        CustomForm::query()
            ->where('menu_placement', 'sidebar')
            ->where('is_active', true)
            ->where('slug', '!=', 'profile')
            ->orderByRaw('COALESCE(display_order, id)')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->each(function (CustomForm $form) use (&$options): void {
                $childForms = CustomForm::query()
                    ->where('custom_form_id', $form->id)
                    ->where('menu_placement', 'sub_item')
                    ->where('is_active', true)
                    ->whereNotNull('sub_item_type')
                    ->orderByRaw('COALESCE(display_order, id)')
                    ->orderBy('id')
                    ->get(['id', 'name', 'custom_form_id', 'sub_item_type']);

                if (self::formHasPaymentEntries((int) $form->id, $childForms->pluck('id')->all())) {
                    $options[self::formFilterValue((int) $form->id)] = self::localizedFormName($form->name);
                }

                foreach ($childForms as $childForm) {
                    if (! self::subFormHasPaymentEntries($childForm)) {
                        continue;
                    }

                    $options[self::subFormFilterValue((int) $childForm->id)] =
                        self::localizedFormName($form->name) . ' - ' . self::localizedFormName($childForm->name);
                }
            });

        return $options;
    }

    protected static function dynamicMajorOptions(): array
    {
        return CandidatePaymentListResource::getEloquentQuery()
            ->get(['data'])
            ->flatMap(function (CandidatePaymentList $record): array {
                return array_filter([
                    data_get($record->data, 'selected_major'),
                    data_get($record->data, 'degree_level_major'),
                ], fn ($value): bool => filled($value));
            })
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $value): array => [$value => $value])
            ->toArray();
    }

    protected static function dynamicAcademicYearOptions(): array
    {
        return CandidatePaymentListResource::getEloquentQuery()
            ->get()
            ->flatMap(function (CandidatePaymentList $record): array {
                return array_filter([
                    data_get($record->data, 'academic_year'),
                    $record->creator?->academic_year,
                ], fn ($value): bool => filled($value));
            })
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $value): array => [$value => $value])
            ->toArray();
    }

    protected static function applyFormFilter(Builder $query, string $formType): Builder
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

        return $query->where('custom_form_id', $formType);
    }

    protected static function formFilterValue(int $formId): string
    {
        return 'form:' . $formId;
    }

    protected static function subFormFilterValue(int $formId): string
    {
        return 'subform:' . $formId;
    }

    protected static function formIdFromFilterValue(string $value): ?int
    {
        if (! str_starts_with($value, 'form:')) {
            return null;
        }

        $formId = (int) substr($value, 5);

        return $formId > 0 ? $formId : null;
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
            ->all();

        return array_values(array_unique([$formId, ...$childIds]));
    }

    protected static function formHasPaymentEntries(int $formId, array $childFormIds = []): bool
    {
        return CandidatePaymentListResource::getEloquentQuery()
            ->whereIn('custom_form_id', array_values(array_unique([$formId, ...$childFormIds])))
            ->exists();
    }

    protected static function subFormHasPaymentEntries(CustomForm $subForm): bool
    {
        return CandidatePaymentListResource::getEloquentQuery()
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

    protected static function genderLabel(string $state): string
    {
        return match (strtolower($state)) {
            'male' => app()->getLocale() === 'km' ? 'ប្រុស' : 'Male',
            'female' => app()->getLocale() === 'km' ? 'ស្រី' : 'Female',
            default => $state,
        };
    }
}
