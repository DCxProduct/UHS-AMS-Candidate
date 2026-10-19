<?php

namespace App\Filament\Admin\Resources\Payments\Tables;

use App\Models\PaymentType;
use App\Filament\Admin\Resources\Payments\PaymentResource;
use App\Models\Payment;
use App\Models\User;
use App\Support\FormEntryData;
use App\Support\CaseInsensitiveSearch;
use App\Support\LocalizedDate;
use App\Support\LocalizedNumber;
use App\Support\TablePdfExporter;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\HeaderUtils;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('receivedBy'))
            ->selectable()
            ->recordAction(null)
            ->recordUrl(null)
            ->summaries(pageCondition: false, allTableCondition: true)
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
                            CaseInsensitiveSearch::apply($userQuery, 'name', $search);
                        }))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('name_latin')
                    ->label(__('payments.table.name_latin'))
                    ->getStateUsing(fn (Payment $record): string => self::latinName($record))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->whereHas('user', function (Builder $userQuery) use ($search): void {
                            CaseInsensitiveSearch::apply($userQuery, 'name_latin', $search);
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
                    ->summarize(
                        Sum::make('amount_usd_total')
                            ->label(__('payments.summary.total_usd'))
                            ->money('USD')
                            ->extraAttributes(['class' => 'payment-total-summary']),
                    )
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('amount_kh')
                    ->label(__('payments.table.amount_kh'))
                    ->formatStateUsing(fn ($state): string => blank($state) ? '-' : number_format((float) $state, 2) . ' KHR')
                    ->summarize(
                        Sum::make('amount_khr_total')
                            ->label(__('payments.summary.total_khr'))
                            ->numeric(2)
                            ->suffix(' KHR')
                            ->extraAttributes(['class' => 'payment-total-summary']),
                    )
                    ->alignCenter()
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


                TextColumn::make('received_by')
                    ->label(__('payments.table.received_by'))
                    ->getStateUsing(fn (Payment $record): string => self::receiverName($record))
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

                        Select::make('received_by')
                            ->label(__('payments.table.received_by'))
                            ->placeholder(__('payments.placeholders.received_by'))
                            ->options(fn (): array => self::dynamicReceiverOptions())
                            ->visible(fn (): bool => ! PaymentResource::showsOnlyOwnReceivedPayments())
                            ->native(false)
                            ->searchable()
                            ->live(),

                        DatePicker::make('datetime_pay')
                            ->label(__('payments.table.datetime_pay'))
                            ->placeholder(__('payments.placeholders.datetime_pay'))
                            ->displayFormat('d-M-Y')
                            ->native(false)
                            ->closeOnDateSelection()
                            ->maxDate(now())
                            ->live(),
                    ])
                    ->columns(5)
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
                                filled($data['received_by'] ?? null),
                                fn (Builder $query): Builder => $query->where('received_by', $data['received_by'])
                            )
                            ->when(
                                filled($data['datetime_pay'] ?? null),
                                fn (Builder $query): Builder => $query->whereDate('datetime_pay', $data['datetime_pay'])
                            );
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->filtersFormColumns(5)
            ->recordActions([
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

    protected static function receiverName(Payment $record): string
    {
        $receiver = $record->receivedBy;

        return (string) ($receiver?->name ?: $receiver?->username ?: '-');
    }

    /**
     * Cashiers who have recorded at least one payment.
     */
    protected static function dynamicReceiverOptions(): array
    {
        return User::query()
            ->whereIn('id', PaymentResource::getEloquentQuery()->whereNotNull('received_by')->select('received_by'))
            ->orderBy('name')
            ->get(['id', 'name', 'username'])
            ->mapWithKeys(fn (User $user): array => [(string) $user->id => (string) ($user->name ?: $user->username)])
            ->toArray();
    }

    protected static function dynamicFormOptions(): array
    {
        return PaymentResource::getEloquentQuery()
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
        return PaymentResource::getEloquentQuery()
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
        return PaymentResource::getEloquentQuery()
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
        return PaymentResource::getEloquentQuery()
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

    protected static function reportColumnDefinitions(): array
    {
        return [
            'row_number' => [
                'label' => __('payments.export.columns.no'),
                'value' => fn (Payment $record, int $rowNumber): string => LocalizedNumber::digits($rowNumber),
            ],
            'name_khmer' => [
                'label' => __('payments.export.columns.name_khmer'),
                'value' => fn (Payment $record): string => self::khmerName($record),
            ],
            'name_latin' => [
                'label' => __('payments.table.name_latin'),
                'value' => fn (Payment $record): string => self::latinName($record),
            ],
            'gender' => [
                'label' => __('payments.export.columns.gender'),
                'value' => fn (Payment $record): string => self::genderLabel(self::entryValue($record, 'gender')),
            ],
            'date_of_birth' => [
                'label' => __('payments.export.columns.date_of_birth'),
                'value' => fn (Payment $record): string => self::dateOfBirth($record),
            ],
            'academic_year' => [
                'label' => __('payments.export.columns.academic_year'),
                'value' => fn (Payment $record): string => self::academicYear($record),
            ],
            'datetime_pay' => [
                'label' => __('payments.export.columns.datetime_pay'),
                'value' => fn (Payment $record): string => LocalizedDate::dayMonthYear($record->datetime_pay),
            ],
            'receipt_number' => [
                'label' => __('payments.export.columns.receipt_number'),
                'value' => fn (Payment $record): string => blank($record->receipt_number) ? '-' : (string) $record->receipt_number,
            ],
            'type_payment' => [
                'label' => __('payments.export.columns.type_payment'),
                'value' => fn (Payment $record): string => PaymentType::localizedLabelFor($record->type_payment),
            ],
            'amount_usd' => [
                'label' => __('payments.export.columns.amount_usd'),
                'value' => fn (Payment $record): string => blank($record->amount_usd) ? '-' : number_format((float) $record->amount_usd, 2) . ' $',
            ],
            'amount_kh' => [
                'label' => __('payments.export.columns.amount_kh'),
                'value' => fn (Payment $record): string => blank($record->amount_kh) ? '-' : number_format((float) $record->amount_kh, 2) . ' KHR',
            ],
        ];
    }

    protected static function academicYear(Payment $record): string
    {
        $entry = self::matchedEntry($record);

        return FormEntryData::academicYearLabel(
            $entry?->data,
            $record->user?->academic_year ?: '-',
        );
    }

    protected static function reportHeadings(): array
    {
        return collect(self::reportColumnDefinitions())
            ->map(fn (array $definition): string => $definition['label'])
            ->values()
            ->all();
    }

    protected static function reportRows(iterable $records): array
    {
        $definitions = self::reportColumnDefinitions();
        $rows = [];
        $rowNumber = 1;

        foreach ($records as $record) {
            if (! $record instanceof Payment) {
                continue;
            }

            $rows[] = collect($definitions)
                ->map(fn (array $definition): string => $definition['value']($record, $rowNumber))
                ->values()
                ->all();
            $rowNumber++;
        }

        return $rows;
    }

    protected static function reportSummary(iterable $records): array
    {
        $records = collect($records)
            ->filter(fn (mixed $record): bool => $record instanceof Payment);

        return [
            'count' => (string) $records->count(),
            'usd' => number_format((float) $records->sum(fn (Payment $record): float => (float) $record->amount_usd), 2),
            'khr' => number_format((float) $records->sum(fn (Payment $record): float => (float) $record->amount_kh), 2),
        ];
    }

    protected static function reportSubtitle(): string
    {
        return __('payments.export.generated_at', [
            'date' => LocalizedDate::dayMonthYear(now()),
        ]);
    }

    protected static function reportSheet(iterable $records): array
    {
        $records = collect($records);
        $dataRows = self::reportRows($records);
        $summary = self::reportSummary($records);
        $headings = self::reportHeadings();
        $lastColumn = self::columnName(count($headings));
        $headingRow = 11;
        $summaryTitleRow = $headingRow + count($dataRows) + 2;
        $summaryRow = $summaryTitleRow + 1;
        $leftAlignedColumns = [2, 3];
        $dataCellStyles = [];
        $dataRowHeights = [];

        foreach (array_keys($dataRows) as $index) {
            $excelRow = $headingRow + 1 + $index;
            $dataRowHeights[$excelRow] = 20;

            foreach (array_keys($headings) as $columnIndex) {
                $column = $columnIndex + 1;
                $dataCellStyles[self::columnName($column) . $excelRow] = in_array($column, $leftAlignedColumns, true) ? 12 : 11;
            }
        }

        $rows = [
            [__('payments.export.kingdom')],
            [__('payments.export.motto')],
            [],
            [],
            [],
            [],
            [__('payments.export.university')],
            [__('payments.export.title')],
            [self::reportSubtitle()],
            [],
            $headings,
            ...$dataRows,
            [],
            [
                __('payments.export.total_records'),
                __('payments.export.total_usd'),
                __('payments.export.total_khr'),
            ],
            [
                $summary['count'],
                $summary['usd'] . ' $',
                $summary['khr'] . ' KHR',
            ],
        ];

        return [
            'name' => 'Payment Records',
            'rows' => $rows,
            'options' => [
                'logo' => true,
                'ignoreNumbersAsText' => true,
                'printLandscape' => true,
                'mergedCells' => [
                    'A1:' . $lastColumn . '1',
                    'A2:' . $lastColumn . '2',
                    'A7:B7',
                    'A8:' . $lastColumn . '8',
                    'A9:' . $lastColumn . '9',
                ],
                'rowStyles' => [
                    1 => 9,
                    2 => 9,
                    7 => 10,
                    8 => 2,
                    9 => 3,
                    $headingRow => 4,
                ],
                'cellStyles' => [
                    ...$dataCellStyles,
                    'A' . $summaryTitleRow => 7,
                    'B' . $summaryTitleRow => 7,
                    'C' . $summaryTitleRow => 7,
                    'A' . $summaryRow => 13,
                    'B' . $summaryRow => 13,
                    'C' . $summaryRow => 13,
                ],
                'rowHeights' => [
                    1 => 26,
                    2 => 26,
                    3 => 16,
                    4 => 16,
                    5 => 16,
                    6 => 16,
                    7 => 20,
                    8 => 24,
                    9 => 20,
                    10 => 12,
                    $headingRow => 24,
                    ...$dataRowHeights,
                    $summaryTitleRow => 22,
                    $summaryRow => 22,
                ],
                'columnWidths' => [
                    1 => 16,
                    2 => 25,
                    3 => 25,
                    4 => 10,
                    5 => 24,
                    6 => 16,
                    7 => 16,
                    8 => 16,
                    9 => 16,
                    10 => 14,
                    11 => 16,
                ],
            ],
        ];
    }

    public static function downloadExcel(iterable $records, ?array $columnKeys = null)
    {
        return self::inKhmer(fn () => self::buildExcelDownload($records, $columnKeys));
    }

    public static function downloadPdf(iterable $records, ?array $columnKeys = null)
    {
        return self::inKhmer(fn () => self::buildPdfDownload($records));
    }

    protected static function inKhmer(\Closure $callback): mixed
    {
        $locale = app()->getLocale();
        app()->setLocale('km');

        try {
            return $callback();
        } finally {
            app()->setLocale($locale);
        }
    }

    protected static function buildExcelDownload(iterable $records, ?array $columnKeys = null)
    {
        $filename = self::downloadFilename('xlsx');
        $path = storage_path('app/' . uniqid('payment-records-', true) . '.xlsx');

        $records = collect($records);
        $columnKeys ??= array_keys(self::exportColumnDefinitions());

        self::writeXlsx($path, [
            self::reportSheet($records),
            [
                'name' => 'Database Export',
                'rows' => self::cleanDataRows($records, $columnKeys),
            ],
        ]);

        $response = response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition('attachment', $filename, self::downloadFilename('xlsx', ascii: true)));

        return $response;
    }

    protected static function buildPdfDownload(iterable $records)
    {
        $records = collect($records);
        $rows = self::reportRows($records);

        return TablePdfExporter::download(
            'payment-records-',
            self::reportHeadings(),
            $rows,
            __('payments.export.title'),
            [],
            [
                'layout' => 'payment-records',
                'subtitle' => self::reportSubtitle(),
                'summary' => self::reportSummary($records),
                'filename' => self::downloadFilename('pdf'),
                'filename_fallback' => self::downloadFilename('pdf', ascii: true),
            ],
        );
    }

    protected static function downloadFilename(string $extension, bool $ascii = false): string
    {
        return ($ascii ? 'payment-records' : __('payments.export.title')) . ' ' . now()->format('d-m-Y') . '.' . $extension;
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
        $hasLogo = collect($sheets)->contains(fn (array $sheet): bool => (bool) ($sheet['options']['logo'] ?? false));

        $zip->addFromString('[Content_Types].xml', self::contentTypesXml($sheets, $hasLogo));
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');
        $zip->addFromString('xl/workbook.xml', self::workbookXml($sheets));
        $zip->addFromString('xl/_rels/workbook.xml.rels', self::workbookRelsXml($sheets));
        $zip->addFromString('xl/styles.xml', self::stylesXml());

        if ($hasLogo) {
            $logoPath = public_path('images/UHS_logo.png');

            if (! is_file($logoPath)) {
                throw new \RuntimeException('The UHS logo could not be found.');
            }

            $zip->addFile($logoPath, 'xl/media/image1.png');
            $zip->addFromString('xl/drawings/drawing1.xml', self::drawingXml());
            $zip->addFromString('xl/drawings/_rels/drawing1.xml.rels', self::drawingRelationshipsXml());
        }

        foreach (array_values($sheets) as $index => $sheet) {
            $zip->addFromString(
                'xl/worksheets/sheet' . ($index + 1) . '.xml',
                self::worksheetXml($sheet['rows'], $sheet['styledRows'] ?? [], $sheet['options'] ?? [])
            );

            if (($sheet['options']['logo'] ?? false) === true) {
                $zip->addFromString(
                    'xl/worksheets/_rels/sheet' . ($index + 1) . '.xml.rels',
                    self::worksheetRelationshipsXml(),
                );
            }
        }

        $zip->close();
    }

    protected static function worksheetXml(array $rows, array $styledRows = [], array $options = []): string
    {
        $rowStyles = $options['rowStyles'] ?? [];
        $cellStyles = $options['cellStyles'] ?? [];
        $mergedCells = $options['mergedCells'] ?? [];
        $columnWidths = $options['columnWidths'] ?? [];
        $rowHeights = $options['rowHeights'] ?? [];
        $hasLogo = ($options['logo'] ?? false) === true;
        $printLandscape = ($options['printLandscape'] ?? false) === true;
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"' . ($hasLogo ? ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"' : '') . '>'
            . ($printLandscape ? '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>' : '')
            . (filled($columnWidths) ? '<cols>' . collect($columnWidths)->map(fn (float|int $width, int $column): string => '<col min="' . $column . '" max="' . $column . '" width="' . $width . '" customWidth="1"/>')->implode('') . '</cols>' : '')
            . '<sheetData>';

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $height = $rowHeights[$excelRow] ?? null;
            $rowAttributes = filled($height) ? ' ht="' . $height . '" customHeight="1"' : '';
            $xml .= '<row r="' . $excelRow . '"' . $rowAttributes . '>';

            foreach (array_values($row) as $columnIndex => $value) {
                $cell = self::columnName($columnIndex + 1) . $excelRow;
                $styleId = $cellStyles[$cell] ?? $rowStyles[$excelRow] ?? (in_array($excelRow, $styledRows, true) ? 1 : 0);
                $style = $styleId > 0 ? ' s="' . $styleId . '"' : '';
                $xml .= '<c r="' . $cell . '"' . $style . ' t="inlineStr"><is><t>' . self::xmlValue($value) . '</t></is></c>';
            }

            $xml .= '</row>';
        }

        $xml .= '</sheetData>';

        if (filled($mergedCells)) {
            $xml .= '<mergeCells count="' . count($mergedCells) . '">' . collect($mergedCells)->map(fn (string $range): string => '<mergeCell ref="' . $range . '"/>')->implode('') . '</mergeCells>';
        }

        if ($printLandscape) {
            $xml .= '<printOptions horizontalCentered="1"/>'
                . '<pageMargins left="0.3" right="0.3" top="0.4" bottom="0.4" header="0.2" footer="0.2"/>'
                . '<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>';
        }

        if (($options['ignoreNumbersAsText'] ?? false) === true && $rows !== []) {
            $lastColumn = self::columnName(max(1, ...array_map(fn (array $row): int => count($row), $rows)));
            $xml .= '<ignoredErrors><ignoredError sqref="A1:' . $lastColumn . count($rows) . '" numberStoredAsText="1"/></ignoredErrors>';
        }

        if ($hasLogo) {
            $xml .= '<drawing r:id="rId1"/>';
        }

        return $xml . '</worksheet>';
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

    protected static function summaryRow(iterable $records, array $columnKeys): array
    {
        $records = collect($records)
            ->filter(fn (mixed $record): bool => $record instanceof Payment);
        $totalUsd = (float) $records->sum(fn (Payment $record): float => (float) $record->amount_usd);
        $totalKhr = (float) $records->sum(fn (Payment $record): float => (float) $record->amount_kh);

        return collect($columnKeys)
            ->map(fn (string $columnKey): string => match ($columnKey) {
                'row_number' => __('payments.summary.total'),
                'amount_usd' => __('payments.summary.total_usd').': $'.number_format($totalUsd, 2),
                'amount_kh' => __('payments.summary.total_khr').': '.number_format($totalKhr, 2).' KHR',
                default => '',
            })
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
            'received_by' => [
                'label' => __('payments.table.received_by'),
                'field_key' => 'received_by',
                'value' => fn (Payment $record): string => self::receiverName($record),
                'clean' => fn (Payment $record): string => blank($record->received_by) ? '-' : (string) $record->received_by,
            ],
        ];
    }

    protected static function contentTypesXml(array $sheets, bool $hasLogo = false): string
    {
        $overrides = collect(array_keys($sheets))
            ->map(fn (int $index): string => '<Override PartName="/xl/worksheets/sheet' . ($index + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>')
            ->implode('');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . ($hasLogo ? '<Default Extension="png" ContentType="image/png"/>' : '')
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . ($hasLogo ? '<Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>' : '')
            . $overrides
            . '</Types>';
    }

    protected static function drawingXml(): string
    {
        $extent = 760000;

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<xdr:oneCellAnchor>'
            . '<xdr:from><xdr:col>0</xdr:col><xdr:colOff>990000</xdr:colOff><xdr:row>2</xdr:row><xdr:rowOff>30000</xdr:rowOff></xdr:from>'
            . '<xdr:ext cx="' . $extent . '" cy="' . $extent . '"/>'
            . '<xdr:pic>'
            . '<xdr:nvPicPr><xdr:cNvPr id="1" name="UHS Logo"/><xdr:cNvPicPr><a:picLocks noChangeAspect="1"/></xdr:cNvPicPr></xdr:nvPicPr>'
            . '<xdr:blipFill><a:blip r:embed="rId1"/><a:stretch><a:fillRect/></a:stretch></xdr:blipFill>'
            . '<xdr:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $extent . '" cy="' . $extent . '"/></a:xfrm><a:prstGeom prst="rect"><a:avLst/></a:prstGeom></xdr:spPr>'
            . '</xdr:pic>'
            . '<xdr:clientData/>'
            . '</xdr:oneCellAnchor>'
            . '</xdr:wsDr>';
    }

    protected static function drawingRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/image1.png"/>'
            . '</Relationships>';
    }

    protected static function worksheetRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>'
            . '</Relationships>';
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

        $styleRelationId = count($sheets) + 1;

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $relationships
            . '<Relationship Id="rId' . $styleRelationId . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';
    }

    protected static function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="8">'
            . '<font><sz val="11"/><name val="Khmer OS Battambang"/><family val="2"/></font>'
            . '<font><b/><color rgb="FF1F2937"/><sz val="11"/><name val="Khmer OS Battambang"/><family val="2"/></font>'
            . '<font><b/><color rgb="FF003B95"/><sz val="11"/><name val="Khmer OS Muol Light"/><family val="2"/></font>'
            . '<font><color rgb="FF1F2937"/><sz val="10"/><name val="Khmer OS Battambang"/><family val="2"/></font>'
            . '<font><b/><color rgb="FFFFFFFF"/><sz val="10"/><name val="Khmer OS Battambang"/><family val="2"/></font>'
            . '<font><color rgb="FF111827"/><sz val="10"/><name val="Khmer OS Muol Light"/><family val="2"/></font>'
            . '<font><color rgb="FF111827"/><sz val="9"/><name val="Khmer OS Muol Light"/><family val="2"/></font>'
            . '<font><color rgb="FF374151"/><sz val="11"/><name val="Khmer OS Battambang"/><family val="2"/></font>'
            . '</fonts>'
            . '<fills count="5">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFE8EEF5"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF003B95"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF2F5FA"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="3">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border><left/><right/><top style="thin"><color rgb="FF94A3B8"/></top><bottom/><diagonal/></border>'
            . '<border><left style="thin"><color rgb="FF7F8EA3"/></left><right style="thin"><color rgb="FF7F8EA3"/></right><top style="thin"><color rgb="FF7F8EA3"/></top><bottom style="thin"><color rgb="FF7F8EA3"/></bottom><diagonal/></border>'
            . '</borders>'
            . '<cellStyleXfs count="1">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>'
            . '</cellStyleXfs>'
            . '<cellXfs count="14">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"/>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="7" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="4" fillId="3" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="4" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="4" fillId="3" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="4" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="5" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="6" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="3" fillId="0" borderId="2" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="3" fillId="0" borderId="2" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="4" borderId="2" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '</cellXfs>'
            . '<cellStyles count="1">'
            . '<cellStyle name="Normal" xfId="0" builtinId="0"/>'
            . '</cellStyles>'
            . '</styleSheet>';
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
