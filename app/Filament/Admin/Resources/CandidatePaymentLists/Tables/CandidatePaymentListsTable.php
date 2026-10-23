<?php

namespace App\Filament\Admin\Resources\CandidatePaymentLists\Tables;

use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\CandidatePaymentLists\CandidatePaymentListResource;
use App\Models\ExchangeRate;
use App\Models\MefInvoiceCode;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\UnpaidApplication;
use App\Services\CandidatePaymentRecorder;
use App\Support\CaseInsensitiveSearch;
use App\Support\FilamentActionPermissions;
use App\Support\FormEntryData;
use App\Support\LocalizedNumber;
use App\Support\OfficialReport;
use App\Support\PaymentValidation;
use App\Support\TablePdfExporter;
use App\Support\WorkflowStageMessages;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Chanthoeun\FilamentCustomForms\Models\CustomFormField;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\RawJs;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CandidatePaymentListsTable
{
    public static function downloadExcel(iterable $records, ?array $columnKeys = null)
    {
        return OfficialReport::inKhmer(function () use ($records, $columnKeys) {
            $columnKeys = self::reportColumnKeys($columnKeys);
            $path = storage_path('app/'.uniqid('unpaid-applications-', true).'.xlsx');

            OfficialReport::writeXlsx($path, [
                self::reportSheet($records, $columnKeys),
                [
                    'name' => 'Database Export',
                    'rows' => self::cleanDataRows($records, $columnKeys),
                ],
            ]);

            return OfficialReport::excelDownload($path, self::reportTitle(), 'unpaid-applications');
        });
    }

    protected static function reportTitle(): string
    {
        return __('candidate_payment_lists.resource_plural_label');
    }

    protected static function reportColumnKeys(?array $columnKeys): array
    {
        $definitions = self::exportColumnDefinitions();
        $columnKeys = array_values(array_filter($columnKeys ?? [], fn (string $key): bool => array_key_exists($key, $definitions)));

        return $columnKeys === [] ? array_keys($definitions) : $columnKeys;
    }

    protected static function reportSheet(iterable $records, array $columnKeys): array
    {
        $rows = self::excelRows($records, $columnKeys);
        $headings = array_shift($rows) ?? [];
        $widths = [
            'row_number' => 16,
            'form_name' => 30,
            'name_khmer' => 25,
            'name_latin' => 25,
            'gender' => 10,
            'phone_number' => 16,
            'date_of_birth' => 16,
            'major' => 25,
            'academic_year' => 14,
            'status_payt' => 18,
        ];
        $leftAligned = ['form_name', 'name_khmer', 'name_latin', 'major'];
        $columnWidths = [];
        $leftAlignedColumns = [];

        foreach ($columnKeys as $index => $key) {
            $columnWidths[$index + 1] = $widths[$key] ?? 16;

            if (in_array($key, $leftAligned, true)) {
                $leftAlignedColumns[] = $index + 1;
            }
        }

        return OfficialReport::sheet(
            'Unpaid Applications',
            self::reportTitle(),
            $headings,
            $rows,
            [],
            $columnWidths,
            $leftAlignedColumns,
        );
    }

    public static function downloadPdf(iterable $records, ?array $columnKeys = null)
    {
        return OfficialReport::inKhmer(function () use ($records, $columnKeys) {
            $columnKeys = self::reportColumnKeys($columnKeys);
            $rows = self::excelRows($records, $columnKeys);
            $headings = array_shift($rows) ?? [];

            return TablePdfExporter::download(
                'unpaid-applications-',
                $headings,
                $rows,
                self::reportTitle(),
                [],
                [
                    'layout' => 'official-report',
                    'subtitle' => OfficialReport::subtitle(),
                    'hide_summary' => true,
                    'filename' => OfficialReport::filename(self::reportTitle(), 'pdf'),
                    'filename_fallback' => OfficialReport::filename(self::reportTitle(), 'pdf', 'unpaid-applications'),
                ],
            );
        });
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

                TextColumn::make('candidate_id')
                    ->label(__('candidate_entrance_statistics.student_id'))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('form_name')
                    ->label(__('candidate_payment_lists.columns.application_form_type'))
                    ->badge()
                    ->getStateUsing(fn (UnpaidApplication $record): string => self::localizedFormName($record->customForm?->name))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->whereHas('customForm', function (Builder $formQuery) use ($search): void {
                            CaseInsensitiveSearch::apply($formQuery, 'name', $search);
                        }))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('name_khmer')
                    ->label(__('candidate_payment_lists.columns.name_khmer'))
                    ->getStateUsing(fn (UnpaidApplication $record): string => self::khmerName($record))
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::applyAny($query, [
                        'data->first_name_kh',
                        'data->last_name_kh',
                        'data->full_name_kh',
                    ], $search))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('name_latin')
                    ->label(__('candidate_payment_lists.columns.name_latin'))
                    ->getStateUsing(fn (UnpaidApplication $record): string => self::latinName($record))
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::applyAny($query, [
                        'data->first_name_en',
                        'data->last_name_en',
                        'data->full_name_en',
                    ], $search))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('gender')
                    ->label(__('candidate_payment_lists.columns.gender'))
                    ->getStateUsing(fn (UnpaidApplication $record): string => self::genderLabel(self::entryValue($record, 'gender')))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('phone_number')
                    ->label(__('candidate_payment_lists.columns.phone_number'))
                    ->getStateUsing(fn (UnpaidApplication $record): string => self::entryValue($record, 'phone_number', $record->creator?->phone))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('date_of_birth')
                    ->label(__('candidate_payment_lists.columns.date_of_birth'))
                    ->getStateUsing(fn (UnpaidApplication $record): string => self::dateOfBirth($record))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('major')
                    ->label(__('candidate_payment_lists.columns.major'))
                    ->getStateUsing(fn (UnpaidApplication $record): string => self::majorLabel($record))
                    ->badge()
                    ->searchable(query: fn (Builder $query, string $search): Builder => FormEntryData::applyJsonLikeFilter($query, FormEntryData::majorKeys(), $search))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('academic_year')
                    ->label(__('candidate_payment_lists.columns.academic_year'))
                    ->getStateUsing(fn (UnpaidApplication $record): string => FormEntryData::academicYearLabel(
                        ['academic_year' => self::entryValue($record, 'academic_year', $record->creator?->academic_year)]
                    ))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('status_payt')
                    ->label(__('candidate_payment_lists.columns.payment_status'))
                    ->badge()
                    ->getStateUsing(fn (UnpaidApplication $record): string => self::paymentStatus($record))
                    ->formatStateUsing(fn (?string $state): string => strtolower((string) $state) === 'paid'
                        ? __('payments.options.status_payt.paid')
                        : __('payments.options.status_payt.unpaid'))
                    ->color(fn (?string $state): string => match (strtolower((string) $state)) {
                        'paid' => 'success',
                        default => 'warning',
                    })
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
                                fn (Builder $query): Builder => FormEntryData::applyJsonExactFilter($query, FormEntryData::majorKeys(), $data['major'])
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
            ->recordActions([
                Action::make('pay')
                    ->label(__('payments.actions.pay'))
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->link()
                    ->modalWidth('4xl')
                    ->modalHeading(__('payments.actions.create_payment'))
                    ->modalSubmitActionLabel(__('payments.actions.submit_payment'))
                    ->fillForm(fn (UnpaidApplication $record): array => [
                        'users_id' => self::ownerId($record),
                        'form_id' => $record->custom_form_id,
                        'candidate_name' => self::candidateDisplayName($record),
                        'candidate_id' => filled($record->candidate_id) ? (string) $record->candidate_id : '-',
                        'exchange_rate' => self::defaultExchangeRate(),
                        'datetime_pay' => now()->toDateString(),
                        'status' => true,
                    ])
                    ->form([
                        Hidden::make('users_id'),
                        Hidden::make('form_id'),
                        Hidden::make('exchange_rate')
                            ->dehydrateStateUsing(fn (mixed $state): ?string => self::normalizeExchangeRate($state)),

                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])->schema([
                            TextInput::make('candidate_name')
                                ->label(__('payments.fields.user'))
                                ->disabled()
                                ->dehydrated(false),

                            TextInput::make('candidate_id')
                                ->label(__('app.custom_form_entry_ui.labels.candidate_id'))
                                ->disabled()
                                ->dehydrated(false),

                            TextInput::make('receipt_number')
                                ->label(__('payments.fields.receipt_number'))
                                ->placeholder(__('payments.placeholders.receipt_number'))
                                ->helperText(__('payments.help.receipt_mef_lookup'))
                                ->required()
                                ->maxLength(255)
                                ->live(onBlur: true)
                                ->afterStateUpdated(function (mixed $state, callable $set): void {
                                    $receiptNumber = MefInvoiceCode::resolveReceiptNumber($state);

                                    if ($receiptNumber !== null && $receiptNumber !== trim((string) $state)) {
                                        $set('receipt_number', $receiptNumber);
                                    }
                                })
                                ->dehydrateStateUsing(fn (mixed $state): ?string => MefInvoiceCode::resolveReceiptNumber($state))
                                ->columnSpanFull()
                                ->rules([
                                    'required',
                                    'string',
                                    'max:255',
                                    Rule::unique('payments', 'receipt_number'),
                                ])
                                ->validationMessages([
                                    'required' => __('payments.validation.receipt_number_required'),
                                    'unique' => __('payments.validation.receipt_number_unique'),
                                ]),

                            Select::make('payment_method')
                                ->label(__('payments.fields.type_payment'))
                                ->placeholder(__('payments.placeholders.type_payment'))
                                ->options(fn (): array => PaymentType::activeMethodOptions())
                                ->native(false)
                                ->live()
                                ->required()
                                ->afterStateUpdated(function (mixed $state, callable $set): void {
                                    $set('type_payment', $state === 'cash'
                                        ? PaymentType::activeKeyForMethod('cash')
                                        : null);
                                })
                                ->validationMessages([
                                    'required' => __('payments.validation.type_payment_required'),
                                ]),

                            Select::make('type_payment')
                                ->label(__('payments.fields.bank'))
                                ->placeholder(__('payments.placeholders.bank'))
                                ->options(fn (): array => PaymentType::activeOptionsForMethod('bank'))
                                ->native(false)
                                ->searchable()
                                ->preload()
                                ->visible(fn (callable $get): bool => $get('payment_method') === 'bank')
                                ->required(fn (callable $get): bool => $get('payment_method') === 'bank')
                                ->dehydrated()
                                ->validationMessages([
                                    'required' => __('payments.validation.type_payment_required'),
                                ]),

                            DatePicker::make('datetime_pay')
                                ->label(__('payments.fields.datetime_pay'))
                                ->placeholder(__('payments.placeholders.datetime_pay'))
                                ->default(now()->toDateString())
                                ->format('Y-m-d')
                                ->displayFormat('d-M-Y')
                                ->native(false)
                                ->disabled()
                                ->dehydrated()
                                ->validationMessages([
                                    'required' => __('payments.validation.datetime_pay_required'),
                                ])
                                ->required(),

                            TextInput::make('amount_kh')
                                ->label(__('payments.fields.amount_kh'))
                                ->placeholder(__('payments.placeholders.amount_kh'))
                                ->suffix('៛')
                                ->mask(RawJs::make('$money($input)'))
                                ->inputMode('decimal')
                                ->rules(PaymentValidation::amountKhRules(required: false))
                                ->rule(static function (): \Closure {
                                    return static function (string $attribute, mixed $value, \Closure $fail): void {
                                        if ($value === null || trim((string) $value) === '') {
                                            return;
                                        }

                                        if (! is_numeric(str_replace(',', '', (string) $value))) {
                                            $fail(__('validation.numeric', ['attribute' => $attribute]));
                                        }
                                    };
                                })
                                ->live(onBlur: true)
                                ->afterStateHydrated(function (TextInput $component, mixed $state): void {
                                    $component->state(self::normalizeKhrAmount($state));
                                })
                                ->afterStateUpdated(function (mixed $state, callable $set): void {
                                    $set('amount_kh', self::normalizeKhrAmount($state));
                                })
                                ->dehydrateStateUsing(fn (mixed $state): ?string => self::dehydrateKhrAmount($state)),

                            TextInput::make('amount_usd')
                                ->label(__('payments.fields.amount_usd'))
                                ->placeholder(__('payments.placeholders.amount_usd'))
                                ->suffix('$')
                                ->inputMode('decimal')
                                ->rules(PaymentValidation::amountUsdRules())
                                ->live(onBlur: true)
                                ->afterStateHydrated(function (TextInput $component, mixed $state): void {
                                    $component->state(self::normalizeUsdAmount($state));
                                })
                                ->afterStateUpdated(function (mixed $state, callable $set): void {
                                    $set('amount_usd', self::normalizeUsdAmount($state));
                                })
                                ->dehydrateStateUsing(fn (mixed $state): ?string => self::normalizeUsdAmount($state)),
                        ]),

                        Placeholder::make('exchange_rate_display')
                            ->label(__('payments.fields.exchange_rate'))
                            ->helperText(__('payments.help.exchange_rate'))
                            ->content(function (callable $get): string {
                                $rate = self::normalizeExchangeRate($get('exchange_rate')) ?? self::defaultExchangeRate();

                                return sprintf('1 USD = %s ៛', $rate);
                            }),

                        Textarea::make('description')
                            ->label(__('payments.fields.description'))
                            ->placeholder(__('payments.placeholders.description'))
                            ->rows(4),
                    ])
                    ->action(function (UnpaidApplication $record, array $data): void {
                        FilamentActionPermissions::abortUnlessCanForResource(CandidatePaymentListResource::class, 'pay');

                        $paymentData = [
                            'users_id' => self::ownerId($record),
                            'form_id' => $record->custom_form_id,
                            'receipt_number' => $data['receipt_number'],
                            'type_payment' => ($data['payment_method'] ?? null) === 'cash'
                                ? PaymentType::activeKeyForMethod('cash')
                                : ($data['type_payment'] ?? null),
                            'status_payt' => 'paid',
                            'amount_usd' => $data['amount_usd'] ?? null,
                            'amount_kh' => $data['amount_kh'] ?? null,
                            'datetime_pay' => $data['datetime_pay'] ?? null,
                            'status' => true,
                            'description' => $data['description'] ?? null,
                        ];

                        if (Schema::hasColumn('payments', 'custom_form_entry_id')) {
                            $paymentData['custom_form_entry_id'] = $record->getKey();
                        }

                        if (Schema::hasColumn('payments', 'exchange_rate')) {
                            $paymentData['exchange_rate'] = $data['exchange_rate'] ?? self::defaultExchangeRate();
                        }

                        try {
                            app(CandidatePaymentRecorder::class)->record($paymentData);
                        } catch (QueryException $exception) {
                            if (Payment::isReceiptNumberUniqueViolation($exception)) {
                                throw ValidationException::withMessages([
                                    'receipt_number' => __('payments.validation.receipt_number_unique'),
                                ]);
                            }

                            throw $exception;
                        }
                        Notification::make()
                            ->title(__('payments.actions.record_payment'))
                            ->success()
                            ->send();
                    })
                    ->visible(fn (UnpaidApplication $record): bool => FilamentActionPermissions::canForResource(CandidatePaymentListResource::class, 'pay')
                        && WorkflowStageMessages::canCurrentUserHandleStage($record, WorkflowStageType::Payment)
                        && self::latestPaymentRecord($record) === null),
            ]);
    }

    protected static function excelRows(iterable $records, ?array $columnKeys = null): array
    {
        $columnKeys ??= array_keys(self::exportColumnDefinitions());
        $rows = [self::excelHeadings($columnKeys)];
        $rowNumber = 1;

        foreach ($records as $record) {
            if (! $record instanceof UnpaidApplication) {
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
            if (! $record instanceof UnpaidApplication) {
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
            'candidate_id' => [
                'label' => __('candidate_entrance_statistics.student_id'),
                'field_key' => 'candidate_id',
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

    protected static function exportRow(UnpaidApplication $record, int $rowNumber, array $columnKeys): array
    {
        $values = [
            'row_number' => LocalizedNumber::digits($rowNumber),
            'candidate_id' => (string) ($record->candidate_id ?? ''),
            'form_name' => self::localizedFormName($record->customForm?->name),
            'name_khmer' => self::khmerName($record),
            'name_latin' => self::latinName($record),
            'gender' => self::genderLabel(self::entryValue($record, 'gender')),
            'phone_number' => self::entryValue($record, 'phone_number', $record->creator?->phone),
            'date_of_birth' => self::dateOfBirth($record),
            'major' => FormEntryData::majorLabel($record->data),
            'academic_year' => FormEntryData::academicYearLabel(
                ['academic_year' => self::entryValue($record, 'academic_year', $record->creator?->academic_year)]
            ),
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

    protected static function cleanDataRow(UnpaidApplication $record, int $rowNumber, array $columnKeys): array
    {
        $values = [
            'row_number' => (string) $rowNumber,
            'candidate_id' => (string) ($record->candidate_id ?? ''),
            'form_name' => (string) $record->custom_form_id,
            'name_khmer' => trim((string) data_get($record->data, 'full_name_kh')) ?: self::khmerName($record),
            'name_latin' => trim((string) data_get($record->data, 'full_name_en')) ?: self::latinName($record),
            'gender' => self::entryValue($record, 'gender'),
            'phone_number' => self::entryValue($record, 'phone_number', $record->creator?->phone),
            'date_of_birth' => self::entryValue($record, 'date_of_birth', $record->creator?->date_of_birth),
            'major' => (string) FormEntryData::firstFilled($record->data, FormEntryData::majorKeys(), '-'),
            'academic_year' => self::entryValue($record, 'academic_year', $record->creator?->academic_year),
            'status_payt' => strtolower(self::paymentStatus($record)),
        ];

        return collect($columnKeys)
            ->filter(fn (string $key): bool => array_key_exists($key, $values))
            ->map(fn (string $key): string => (string) ($values[$key] ?? ''))
            ->values()
            ->all();
    }

    protected static function entryValue(?UnpaidApplication $record, string $key, mixed $fallback = null): string
    {
        $value = data_get($record?->data, $key);

        if (blank($value)) {
            $value = $fallback;
        }

        return blank($value) ? '-' : (string) $value;
    }

    protected static function majorLabel(UnpaidApplication $record): string
    {
        return FormEntryData::majorLabel($record->data);
    }

    protected static function optionLabelForFieldValue(string $fieldName, string $value): ?string
    {
        $fields = CustomFormField::query()
            ->where('name', $fieldName)
            ->whereNotNull('options')
            ->orderBy('sort')
            ->get();

        foreach ($fields as $field) {
            $options = is_array($field->options) ? $field->options : json_decode((string) $field->options, true);
            $choices = $options['choices'] ?? [];

            if (! is_array($choices)) {
                continue;
            }

            foreach ($choices as $choiceKey => $choiceLabel) {
                if (is_array($choiceLabel) && array_key_exists('value', $choiceLabel)) {
                    if ((string) $choiceLabel['value'] === $value) {
                        return self::localizedOptionLabel($choiceLabel['label'] ?? $choiceLabel['value']);
                    }

                    continue;
                }

                if ((string) $choiceKey === $value) {
                    return self::localizedOptionLabel($choiceLabel);
                }
            }
        }

        return null;
    }

    protected static function localizedOptionLabel(mixed $label): string
    {
        if (is_array($label)) {
            return (string) ($label[app()->getLocale()] ?? $label['km'] ?? $label['kh'] ?? $label['en'] ?? collect($label)->first() ?? '-');
        }

        return (string) $label;
    }

    protected static function khmerName(UnpaidApplication $record): string
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

    protected static function latinName(UnpaidApplication $record): string
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

    protected static function paymentStatus(UnpaidApplication $record): string
    {
        $payment = self::latestPaymentRecord($record);

        if (! $payment) {
            return 'unpaid';
        }

        return strtolower((string) $payment->status_payt) === 'paid' ? 'paid' : 'unpaid';
    }

    protected static function normalizeUsdAmount(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        $normalized = str_replace(',', '', $normalized);

        if (! is_numeric($normalized)) {
            return $normalized;
        }

        return number_format((float) $normalized, 2, '.', '');
    }

    protected static function normalizeKhrAmount(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        $normalized = str_replace(',', '', $normalized);

        if (! is_numeric($normalized)) {
            return $normalized;
        }

        return number_format((float) $normalized, 0, '.', ',');
    }

    protected static function dehydrateKhrAmount(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        return str_replace(',', '', $normalized);
    }

    protected static function defaultExchangeRate(): string
    {
        return ExchangeRate::activeUsdToKhrRate() ?? '4100.00';
    }

    protected static function normalizeExchangeRate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        $normalized = str_replace(',', '', $normalized);

        if (! is_numeric($normalized)) {
            return $normalized;
        }

        return number_format((float) $normalized, 2, '.', '');
    }

    protected static function ownerId(UnpaidApplication $record): ?int
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

    protected static function latestPaymentRecord(UnpaidApplication $record): ?Payment
    {
        if (Schema::hasColumn('payments', 'custom_form_entry_id')) {
            return Payment::query()
                ->where('custom_form_entry_id', $record->getKey())
                ->latest('id')
                ->first();
        }

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

    protected static function dateOfBirth(UnpaidApplication $record): string
    {
        $value = self::entryValue($record, 'date_of_birth', $record->creator?->date_of_birth);

        if ($value === '-') {
            return '-';
        }

        $timestamp = strtotime($value);

        return $timestamp ? date('d-m-Y', $timestamp) : $value;
    }

    protected static function candidateDisplayName(UnpaidApplication $record): string
    {
        $khmerName = self::khmerName($record);

        if ($khmerName !== '-') {
            return $khmerName;
        }

        $latinName = self::latinName($record);

        if ($latinName !== '-') {
            return $latinName;
        }

        return (string) ($record->creator?->name ?: $record->creator?->username ?: $record->creator?->email ?: '-');
    }

    protected static function dynamicFormOptions(): array
    {
        return CustomForm::query()
            ->where('is_active', true)
            ->where('slug', '!=', 'profile')
            ->orderByRaw('COALESCE(display_order, id)')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->mapWithKeys(fn (CustomForm $form): array => [
                self::formFilterValue((int) $form->id) => self::localizedFormName($form->name),
            ])
            ->all();
    }

    protected static function dynamicMajorOptions(): array
    {
        return CandidatePaymentListResource::getEloquentQuery()
            ->get(['data'])
            ->flatMap(function (UnpaidApplication $record): array {
                return array_filter([
                    FormEntryData::firstFilled($record->data, FormEntryData::majorKeys()),
                ], fn ($value): bool => filled($value));
            })
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $value): array => [$value => FormEntryData::majorOptionLabel($value, $value)])
            ->toArray();
    }

    protected static function dynamicAcademicYearOptions(): array
    {
        return CandidatePaymentListResource::getEloquentQuery()
            ->get()
            ->flatMap(function (UnpaidApplication $record): array {
                return array_filter([
                    data_get($record->data, 'academic_year'),
                    $record->creator?->academic_year,
                ], fn ($value): bool => filled($value));
            })
            ->map(fn ($value): string => trim((string) $value))
            ->filter()
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $value): array => [$value => FormEntryData::academicYearOptionLabel($value, $value)])
            ->toArray();
    }

    protected static function applyFormFilter(Builder $query, string $formType): Builder
    {
        if (str_starts_with($formType, 'form:')) {
            $formId = self::formIdFromFilterValue($formType);

            if ($formId) {
                return $query->where('custom_form_id', $formId);
            }
        }

        return $query->where('custom_form_id', $formType);
    }

    protected static function formFilterValue(int $formId): string
    {
        return 'form:'.$formId;
    }

    protected static function formIdFromFilterValue(string $value): ?int
    {
        if (! str_starts_with($value, 'form:')) {
            return null;
        }

        $formId = (int) substr($value, 5);

        return $formId > 0 ? $formId : null;
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
