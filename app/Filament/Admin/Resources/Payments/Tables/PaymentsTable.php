<?php

namespace App\Filament\Admin\Resources\Payments\Tables;

use App\Filament\Admin\Resources\Payments\PaymentResource;
use App\Models\PaymentType;
use App\Models\Payment;
use App\Support\FilamentActionPermissions;
use App\Support\FormEntryData;
use App\Support\LocalizedDate;
use App\Support\LocalizedNumber;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->selectable()
            ->recordAction(null)
            ->recordUrl(null)
            ->searchPlaceholder(__('payments.search'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('row_number')
                    ->label(__('payments.table.no'))
                    ->rowIndex()
                    ->formatStateUsing(fn ($state): string => LocalizedNumber::digits($state))
                    ->alignCenter()
                    ->width('60px')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('form.display_name')
                    ->label(__('payments.table.form'))
                    ->badge()
                    ->getStateUsing(fn (Payment $record): string => $record->form?->display_name ?: $record->form?->name ?: '-')
                    ->toggleable(),

                TextColumn::make('name_khmer')
                    ->label(__('payments.table.name_khmer'))
                    ->getStateUsing(fn (Payment $record): string => self::khmerName($record))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->whereHas('user', function (Builder $userQuery) use ($search): void {
                            $userQuery->where('name', 'like', "%{$search}%");
                        }))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('name_latin')
                    ->label(__('payments.table.name_latin'))
                    ->getStateUsing(fn (Payment $record): string => self::latinName($record))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->whereHas('user', function (Builder $userQuery) use ($search): void {
                            $userQuery->where('name_latin', 'like', "%{$search}%");
                        }))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('gender')
                    ->label(__('payments.table.gender'))
                    ->badge()
                    ->alignCenter()
                    ->getStateUsing(fn (Payment $record): string => self::genderLabel(self::entryValue($record, 'gender')))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('phone_number')
                    ->label(__('payments.table.phone_number'))
                    ->getStateUsing(fn (Payment $record): string => self::entryValue($record, 'phone_number', $record->user?->phone))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('date_of_birth')
                    ->label(__('payments.table.date_of_birth'))
                    ->getStateUsing(fn (Payment $record): string => self::dateOfBirth($record))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('major')
                    ->label(__('payments.table.major'))
                    ->badge()
                    ->getStateUsing(fn (Payment $record): string => self::major($record))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('receipt_number')
                    ->label(__('payments.table.receipt_number'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('exchange_rate')
                    ->label(__('payments.table.exchange_rate'))
                    ->badge()
                    ->formatStateUsing(fn ($state): string => blank($state) ? '-' : number_format((float) $state, 2) . ' KHR')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('type_payment')
                    ->label(__('payments.table.type_payment'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => PaymentType::localizedLabelFor($state))
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('amount_usd')
                    ->label(__('payments.table.amount_usd'))
                    ->money('USD')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('amount_kh')
                    ->label(__('payments.table.amount_kh'))
                    ->formatStateUsing(fn ($state): string => blank($state) ? '-' : number_format((float) $state, 2) . ' KHR')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('status_payt')
                    ->label(__('payments.table.status_payt'))
                    ->badge()
                    ->alignCenter()
                    ->formatStateUsing(fn (?string $state): string => __('payments.options.status_payt.' . strtolower((string) $state)))
                    ->color(fn (?string $state): string => match (strtolower((string) $state)) {
                        'paid' => 'success',
                        'return' => 'danger',
                        default => 'warning',
                    })
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('datetime_pay')
                    ->label(__('payments.table.datetime_pay'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state))
                    ->toggleable(isToggledHiddenByDefault: false),

                ])
            ->filters([
                Filter::make('payment_filters')
                    ->label(new HtmlString('&nbsp;'))
                    ->schema([
                        Select::make('form_id')
                            ->label(__('payments.table.form'))
                            ->placeholder(__('payments.placeholders.form'))
                            ->options(fn (): array => self::dynamicFormOptions())
                            ->native(false)
                            ->searchable()
                            ->live(),

                        Select::make('receipt_number')
                            ->label(__('payments.table.receipt_number'))
                            ->placeholder(__('payments.placeholders.receipt_number'))
                            ->options(fn (): array => self::dynamicReceiptOptions())
                            ->native(false)
                            ->searchable()
                            ->live(),

                        Select::make('major')
                            ->label(__('payments.table.major'))
                            ->placeholder(__('payments.placeholders.major'))
                            ->options(fn (): array => self::dynamicMajorOptions())
                            ->native(false)
                            ->searchable()
                            ->live(),

                        DatePicker::make('datetime_pay')
                            ->label(__('payments.table.datetime_pay'))
                            ->placeholder(__('payments.placeholders.datetime_pay'))
                            ->displayFormat('d-m-Y')
                            ->native(false)
                            ->closeOnDateSelection()
                            ->maxDate(now())
                            ->live(),
                    ])
                    ->columns(4)
                    ->columnSpanFull()
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                filled($data['receipt_number'] ?? null),
                                fn (Builder $query): Builder => $query->where('receipt_number', $data['receipt_number'])
                            )
                            ->when(
                                filled($data['major'] ?? null),
                                fn (Builder $query): Builder => $query->whereIn('id', self::paymentIdsForMajor((string) $data['major']))
                            )
                            ->when(
                                filled($data['form_id'] ?? null),
                                fn (Builder $query): Builder => $query->where('form_id', $data['form_id'])
                            )
                            ->when(
                                filled($data['datetime_pay'] ?? null),
                                fn (Builder $query): Builder => $query->whereDate('datetime_pay', $data['datetime_pay'])
                            );
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->filtersFormColumns(4)
            ->recordActions([
                Action::make('view_slip')
                    ->label(__('payments.actions.view_slip'))
                    ->icon('heroicon-o-eye')
                    ->color('danger')
                    ->modalHeading(__('payments.actions.view_slip'))
                    ->modalWidth('7xl')
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false)
                    ->modalContent(fn (Payment $record) => view('payment-slip-modal', [
                        'imageUrl' => $record->paymentSlipUrl(),
                    ]))
                    ->visible(fn (Payment $record): bool => FilamentActionPermissions::canForResource(PaymentResource::class, 'view_slip')
                        && filled($record->payment_slip_path)),
                EditAction::make()
                    ->label(__('payments.actions.edit'))
                    ->icon('heroicon-o-pencil-square'),
            ]);
    }

    protected static function matchedEntry(Payment $record): ?CustomFormEntry
    {
        if (Schema::hasColumn('payments', 'custom_form_entry_id') && filled($record->custom_form_entry_id)) {
            return CustomFormEntry::query()->find($record->custom_form_entry_id);
        }

        if (blank($record->users_id) || blank($record->form_id)) {
            return null;
        }

        return CustomFormEntry::query()
            ->where('custom_form_id', $record->form_id)
            ->where(function (Builder $query) use ($record): void {
                if (Schema::hasColumn('custom_form_entries', 'created_by')) {
                    $query->orWhere('created_by', $record->users_id);
                }

                if (Schema::hasColumn('custom_form_entries', 'user_id')) {
                    $query->orWhere('user_id', $record->users_id);
                }

                if (Schema::hasColumn('custom_form_entries', 'created_by_id')) {
                    $query->orWhere('created_by_id', $record->users_id);
                }
            })
            ->latest('id')
            ->first();
    }

    protected static function entryValue(Payment $record, string $key, mixed $fallback = null): string
    {
        $entry = self::matchedEntry($record);
        $value = data_get($entry?->data, $key);

        if (blank($value)) {
            $value = $fallback;
        }

        return blank($value) ? '-' : (string) $value;
    }

    protected static function khmerName(Payment $record): string
    {
        $entry = self::matchedEntry($record);

        $splitName = trim(collect([
            data_get($entry?->data, 'first_name_kh'),
            data_get($entry?->data, 'last_name_kh'),
        ])->filter()->join(' '));

        if (filled($splitName)) {
            return $splitName;
        }

        return self::entryValue($record, 'full_name_kh', $record->user?->name);
    }

    protected static function latinName(Payment $record): string
    {
        $entry = self::matchedEntry($record);

        $splitName = trim(collect([
            data_get($entry?->data, 'first_name_en'),
            data_get($entry?->data, 'last_name_en'),
        ])->filter()->join(' '));

        if (filled($splitName)) {
            return strtoupper($splitName);
        }

        $fallback = $record->user?->name_latin ?: $record->user?->name;

        return strtoupper(self::entryValue($record, 'full_name_en', $fallback));
    }

    protected static function dateOfBirth(Payment $record): string
    {
        $value = self::entryValue($record, 'date_of_birth', $record->user?->date_of_birth);

        if ($value === '-') {
            return '-';
        }

        $timestamp = strtotime($value);

        return $timestamp ? date('d-m-Y', $timestamp) : $value;
    }

    protected static function major(Payment $record): string
    {
        $entry = self::matchedEntry($record);

        return FormEntryData::majorLabel($entry?->data);
    }

    protected static function majorKey(Payment $record): string
    {
        $entry = self::matchedEntry($record);

        return (string) FormEntryData::firstFilled($entry?->data, FormEntryData::majorKeys(), '-');
    }

    protected static function genderLabel(string $state): string
    {
        return match (strtolower($state)) {
            'male' => app()->getLocale() === 'km' ? 'ប្រុស' : 'Male',
            'female' => app()->getLocale() === 'km' ? 'ស្រី' : 'Female',
            default => $state,
        };
    }

    protected static function dynamicFormOptions(): array
    {
        return Payment::query()
            ->with('form:id,name')
            ->get()
            ->filter(fn (Payment $record): bool => filled($record->form_id) && $record->form !== null)
            ->mapWithKeys(fn (Payment $record): array => [
                (string) $record->form_id => (string) ($record->form->display_name ?: $record->form->name ?: '-'),
            ])
            ->toArray();
    }

    protected static function dynamicReceiptOptions(): array
    {
        return Payment::query()
            ->whereNotNull('receipt_number')
            ->pluck('receipt_number')
            ->map(fn (mixed $value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $value): array => [$value => $value])
            ->toArray();
    }

    protected static function dynamicMajorOptions(): array
    {
        return Payment::query()
            ->get()
            ->map(fn (Payment $record): string => trim(self::majorKey($record)))
            ->filter(fn (string $value): bool => $value !== '' && $value !== '-')
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $value): array => [$value => FormEntryData::majorOptionLabel($value, $value)])
            ->toArray();
    }

    protected static function paymentIdsForMajor(string $major): array
    {
        return Payment::query()
            ->get()
            ->filter(fn (Payment $record): bool => self::majorKey($record) === $major)
            ->pluck('id')
            ->all();
    }

    protected static function dynamicTypePaymentOptions(): array
    {
        return PaymentType::activeOptions();
    }

    protected static function dynamicAmountUsdOptions(): array
    {
        return Payment::query()
            ->whereNotNull('amount_usd')
            ->pluck('amount_usd')
            ->map(fn (mixed $value): string => self::normalizeNumericFilterValue($value))
            ->filter()
            ->unique()
            ->sortBy(fn (string $value): float => (float) $value)
            ->mapWithKeys(fn (string $value): array => [$value => number_format((float) $value, 2) . '$'])
            ->toArray();
    }

    protected static function dynamicAmountKhOptions(): array
    {
        return Payment::query()
            ->whereNotNull('amount_kh')
            ->pluck('amount_kh')
            ->map(fn (mixed $value): string => self::normalizeNumericFilterValue($value))
            ->filter()
            ->unique()
            ->sortBy(fn (string $value): float => (float) $value)
            ->mapWithKeys(fn (string $value): array => [$value => number_format((float) $value, 0) . ' KHR'])
            ->toArray();
    }

    protected static function normalizeNumericFilterValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }

    protected static function dynamicStatusPaymentOptions(): array
    {
        return Payment::query()
            ->whereNotNull('status_payt')
            ->pluck('status_payt')
            ->map(fn (?string $value): string => strtolower(trim((string) $value)))
            ->filter()
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $value): array => [$value => __('payments.options.status_payt.' . $value)])
            ->toArray();
    }

    public static function downloadExcel(iterable $records, ?array $columnKeys = null)
    {
        $filename = 'payment-records-' . now()->format('Y-m-d-His') . '.xlsx';
        $path = storage_path('app/' . uniqid('payment-records-', true) . '.xlsx');

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
            if (! $record instanceof Payment) {
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
            if (! $record instanceof Payment) {
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

    protected static function exportRow(Payment $record, int $rowNumber, array $columnKeys): array
    {
        $definitions = self::exportColumnDefinitions();

        return collect($columnKeys)
            ->filter(fn (string $key): bool => array_key_exists($key, $definitions))
            ->map(fn (string $key): string => $definitions[$key]['value']($record, $rowNumber))
            ->values()
            ->all();
    }

    protected static function cleanDataRow(Payment $record, int $rowNumber, array $columnKeys): array
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
                'label' => __('payments.table.no'),
                'field_key' => 'row_number',
                'value' => fn (Payment $record, int $rowNumber): string => (string) $rowNumber,
                'clean' => fn (Payment $record, int $rowNumber): string => (string) $rowNumber,
            ],
            'form.display_name' => [
                'label' => __('payments.table.form'),
                'field_key' => 'form_id',
                'value' => fn (Payment $record): string => $record->form?->display_name ?: $record->form?->name ?: '-',
                'clean' => fn (Payment $record): string => blank($record->form_id) ? '-' : (string) $record->form_id,
            ],
            'name_khmer' => [
                'label' => __('payments.table.name_khmer'),
                'field_key' => 'name_khmer',
                'value' => fn (Payment $record): string => self::khmerName($record),
                'clean' => fn (Payment $record): string => self::khmerName($record),
            ],
            'name_latin' => [
                'label' => __('payments.table.name_latin'),
                'field_key' => 'name_latin',
                'value' => fn (Payment $record): string => self::latinName($record),
                'clean' => fn (Payment $record): string => self::latinName($record),
            ],
            'gender' => [
                'label' => __('payments.table.gender'),
                'field_key' => 'gender',
                'value' => fn (Payment $record): string => self::genderLabel(self::entryValue($record, 'gender')),
                'clean' => fn (Payment $record): string => self::entryValue($record, 'gender'),
            ],
            'phone_number' => [
                'label' => __('payments.table.phone_number'),
                'field_key' => 'phone_number',
                'value' => fn (Payment $record): string => self::entryValue($record, 'phone_number', $record->user?->phone),
                'clean' => fn (Payment $record): string => self::entryValue($record, 'phone_number', $record->user?->phone),
            ],
            'date_of_birth' => [
                'label' => __('payments.table.date_of_birth'),
                'field_key' => 'date_of_birth',
                'value' => fn (Payment $record): string => self::dateOfBirth($record),
                'clean' => fn (Payment $record): string => self::entryValue($record, 'date_of_birth', $record->user?->date_of_birth),
            ],
            'major' => [
                'label' => __('payments.table.major'),
                'field_key' => 'major',
                'value' => fn (Payment $record): string => self::major($record),
                'clean' => fn (Payment $record): string => self::major($record),
            ],
            'receipt_number' => [
                'label' => __('payments.table.receipt_number'),
                'field_key' => 'receipt_number',
                'value' => fn (Payment $record): string => blank($record->receipt_number) ? '-' : (string) $record->receipt_number,
                'clean' => fn (Payment $record): string => blank($record->receipt_number) ? '-' : (string) $record->receipt_number,
            ],
            'payment_slip_path' => [
                'label' => __('payments.table.payment_slip'),
                'field_key' => 'payment_slip_path',
                'value' => fn (Payment $record): string => filled($record->payment_slip_path) ? __('payments.actions.view_slip') : '-',
                'clean' => fn (Payment $record): string => blank($record->payment_slip_path) ? '-' : (string) $record->payment_slip_path,
            ],
            'type_payment' => [
                'label' => __('payments.table.type_payment'),
                'field_key' => 'type_payment',
                'value' => fn (Payment $record): string => PaymentType::localizedLabelFor($record->type_payment),
                'clean' => fn (Payment $record): string => blank($record->type_payment) ? '-' : (string) $record->type_payment,
            ],
            'status_payt' => [
                'label' => __('payments.table.status_payt'),
                'field_key' => 'status_payt',
                'value' => fn (Payment $record): string => __('payments.options.status_payt.' . strtolower((string) $record->status_payt)),
                'clean' => fn (Payment $record): string => blank($record->status_payt) ? '-' : (string) $record->status_payt,
            ],
            'amount_usd' => [
                'label' => __('payments.table.amount_usd'),
                'field_key' => 'amount_usd',
                'value' => fn (Payment $record): string => blank($record->amount_usd) ? '-' : number_format((float) $record->amount_usd, 2) . '$',
                'clean' => fn (Payment $record): string => blank($record->amount_usd) ? '-' : (string) $record->amount_usd,
            ],
            'amount_kh' => [
                'label' => __('payments.table.amount_kh'),
                'field_key' => 'amount_kh',
                'value' => fn (Payment $record): string => blank($record->amount_kh) ? '-' : number_format((float) $record->amount_kh, 2) . ' KHR',
                'clean' => fn (Payment $record): string => blank($record->amount_kh) ? '-' : (string) $record->amount_kh,
            ],
            'datetime_pay' => [
                'label' => __('payments.table.datetime_pay'),
                'field_key' => 'datetime_pay',
                'value' => fn (Payment $record): string => LocalizedDate::dayMonthYear($record->datetime_pay),
                'clean' => fn (Payment $record): string => blank($record->datetime_pay) ? '-' : (string) $record->datetime_pay,
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
}
