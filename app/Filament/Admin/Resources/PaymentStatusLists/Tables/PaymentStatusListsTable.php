<?php

namespace App\Filament\Admin\Resources\PaymentStatusLists\Tables;

use App\Enums\WorkflowStageType;
use App\Filament\Admin\Resources\PaymentStatusLists\PaymentStatusListResource;
use App\Filament\Admin\Resources\Payments\Tables\PaymentsTable;
use App\Models\ExchangeRate;
use App\Models\MefInvoiceCode;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\PaymentStatusList;
use App\Models\User;
use App\Rules\ValidMefInvoiceReceipt;
use App\Services\CandidatePaymentRecorder;
use App\Support\CandidateNumber;
use App\Support\CaseInsensitiveSearch;
use App\Support\FilamentActionPermissions;
use App\Support\FormEntryData;
use App\Support\LocalizedDate;
use App\Support\LocalizedNumber;
use App\Support\OfficialReport;
use App\Support\PaymentValidation;
use App\Support\TablePdfExporter;
use App\Support\WorkflowStageMessages;
use App\Support\WorkflowActionFeedback;
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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\RawJs;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class PaymentStatusListsTable
{
    public static function downloadExcel(iterable $records, ?array $columnKeys = null)
    {
        return OfficialReport::inKhmer(function () use ($records, $columnKeys) {
            $columnKeys = self::reportColumnKeys($columnKeys);
            $path = storage_path('app/'.uniqid('payment-status-lists-', true).'.xlsx');

            OfficialReport::writeXlsx($path, [
                self::reportSheet($records, $columnKeys),
                [
                    'name' => 'Database Export',
                    'rows' => self::cleanDataRows($records, $columnKeys),
                ],
            ]);

            return OfficialReport::excelDownload($path, self::reportTitle(), 'payment-status-lists');
        });
    }

    protected static function reportTitle(): string
    {
        return __('payment_status_lists.resource_plural_label');
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
            'date_of_birth' => 24,
            'major' => 25,
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

        $sheet = OfficialReport::sheet(
            self::reportTitle(),
            self::reportTitle(),
            $headings,
            $rows,
            [],
            $columnWidths,
            $leftAlignedColumns,
        );

        $dateOfBirthColumn = array_search('date_of_birth', $columnKeys, true);

        if ($dateOfBirthColumn !== false) {
            $sheet['options']['cellStyles'][OfficialReport::columnName($dateOfBirthColumn + 1) . '11'] = 7;
        }

        return $sheet;
    }

    public static function downloadPdf(iterable $records, ?array $columnKeys = null)
    {
        return OfficialReport::inKhmer(function () use ($records, $columnKeys) {
            $columnKeys = self::reportColumnKeys($columnKeys);
            $rows = self::excelRows($records, $columnKeys);
            $headings = array_shift($rows) ?? [];

            return TablePdfExporter::download(
                'payment-status-lists-',
                $headings,
                $rows,
                self::reportTitle(),
                [],
                [
                    'layout' => 'official-report',
                    'orientation' => 'P',
                    'subtitle' => OfficialReport::subtitle(),
                    'hide_summary' => true,
                    'filename' => OfficialReport::filename(self::reportTitle(), 'pdf'),
                    'filename_fallback' => OfficialReport::filename(self::reportTitle(), 'pdf', 'payment-status-lists'),
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
            ->contentFooter(fn ($livewire) => view('filament.admin.payment-status-lists.summary', [
                'counts' => self::summaryCounts($livewire->getFilteredTableQuery()),
            ]))
            ->columns([
                TextColumn::make('row_number')
                    ->label(__('payment_status_lists.columns.no'))
                    ->rowIndex()
                    ->formatStateUsing(fn ($state): string => LocalizedNumber::digits($state))
                    ->alignCenter()
                    ->width('60px')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('candidate_id')
                    ->label(__('candidate_entrance_statistics.student_id'))
                    ->formatStateUsing(fn (mixed $state): string => CandidateNumber::format($state))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('form_name')
                    ->label(__('payment_status_lists.columns.application_form_type'))
                    ->badge()
                    ->getStateUsing(fn (PaymentStatusList $record): string => self::localizedFormName($record->customForm?->name))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->whereHas('customForm', function (Builder $formQuery) use ($search): void {
                            CaseInsensitiveSearch::apply($formQuery, 'name', $search);
                        }))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('name_khmer')
                    ->label(__('payment_status_lists.columns.name_khmer'))
                    ->getStateUsing(fn (PaymentStatusList $record): string => self::khmerName($record))
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::applyAny($query, [
                        'data->first_name_kh',
                        'data->last_name_kh',
                        'data->full_name_kh',
                    ], $search))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('name_latin')
                    ->label(__('payment_status_lists.columns.name_latin'))
                    ->getStateUsing(fn (PaymentStatusList $record): string => self::latinName($record))
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::applyAny($query, [
                        'data->first_name_en',
                        'data->last_name_en',
                        'data->full_name_en',
                    ], $search))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('gender')
                    ->label(__('payment_status_lists.columns.gender'))
                    ->getStateUsing(fn (PaymentStatusList $record): string => self::genderLabel(self::entryValue($record, 'gender')))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('phone_number')
                    ->label(__('payment_status_lists.columns.phone_number'))
                    ->getStateUsing(fn (PaymentStatusList $record): string => self::entryValue($record, 'phone_number', $record->creator?->phone))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('date_of_birth')
                    ->label(__('payment_status_lists.columns.date_of_birth'))
                    ->getStateUsing(fn (PaymentStatusList $record): string => self::dateOfBirth($record))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('major')
                    ->label(__('payment_status_lists.columns.major'))
                    ->getStateUsing(fn (PaymentStatusList $record): string => self::majorLabel($record))
                    ->badge()
                    ->searchable(query: fn (Builder $query, string $search): Builder => FormEntryData::applyJsonLikeFilter($query, FormEntryData::majorKeys(), $search))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('status_payt')
                    ->label(__('payment_status_lists.columns.payment_status'))
                    ->badge()
                    ->getStateUsing(fn (PaymentStatusList $record): string => self::paymentStatus($record))
                    ->formatStateUsing(fn (?string $state, PaymentStatusList $record): string => WorkflowStageMessages::paymentStatusLabel($record, (string) $state)
                        ?? (strtolower((string) $state) === 'paid'
                            ? __('payments.options.status_payt.paid')
                            : __('payments.options.status_payt.unpaid')))
                    ->color(fn (?string $state): string => match (strtolower((string) $state)) {
                        'paid' => 'success',
                        default => 'warning',
                    })
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('payment_source')
                    ->label(__('payments.table.payment_source'))
                    ->getStateUsing(fn (PaymentStatusList $record): string => self::latestPaymentRecord($record)?->paymentSourceLabel() ?? '-')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('receipt_number')
                    ->label(__('payments.table.receipt_number'))
                    ->getStateUsing(fn (PaymentStatusList $record): string => (string) (self::latestPaymentRecord($record)?->receipt_number ?: '-'))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('datetime_pay')
                    ->label(__('payments.table.datetime_pay'))
                    ->getStateUsing(fn (PaymentStatusList $record): string => LocalizedDate::dayMonthYear(self::latestPaymentRecord($record)?->datetime_pay))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('received_by')
                    ->label(__('payments.table.received_by'))
                    ->getStateUsing(function (PaymentStatusList $record): string {
                        $receiver = self::latestPaymentRecord($record)?->receivedBy;

                        return (string) ($receiver?->name ?: $receiver?->username ?: '-');
                    })
                    ->toggleable(isToggledHiddenByDefault: false),

            ])
            ->filters([
                Filter::make('payment_status_list_filters')
                    ->label(new HtmlString('&nbsp;'))
                    ->schema([
                        DatePicker::make('from_date')
                            ->label(__('payments.table.from_date'))
                            ->placeholder(__('payments.placeholders.from_date'))
                            ->displayFormat('d-M-Y')
                            ->native(false)
                            ->closeOnDateSelection()
                            ->maxDate(fn (Get $get) => $get('to_date'))
                            ->live(),

                        DatePicker::make('to_date')
                            ->label(__('payments.table.to_date'))
                            ->placeholder(__('payments.placeholders.to_date'))
                            ->displayFormat('d-M-Y')
                            ->native(false)
                            ->closeOnDateSelection()
                            ->minDate(fn (Get $get) => $get('from_date'))
                            ->live(),

                        Select::make('status_payt')
                            ->label(__('payment_status_lists.columns.payment_status'))
                            ->placeholder(__('payment_status_lists.filters.all_statuses'))
                            ->options([
                                'paid' => __('payments.options.status_payt.paid'),
                                'unpaid' => __('payments.options.status_payt.unpaid'),
                            ])
                            ->native(false)
                            ->live(),

                        Select::make('received_by')
                            ->label(__('payments.table.received_by'))
                            ->placeholder(__('payments.options.all_cashiers'))
                            ->options(fn (): array => self::dynamicReceiverOptions())
                            ->native(false)
                            ->searchable()
                            ->live(),


                        Select::make('form_id')
                            ->label(__('payment_status_lists.columns.application_form_type'))
                            ->options(fn (): array => self::dynamicFormOptions())
                            ->native(false)
                            ->searchable()
                            ->live(),

                        Select::make('major')
                            ->label(__('payment_status_lists.columns.major'))
                            ->options(fn (): array => self::dynamicMajorOptions())
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

                        Select::make('payment_source')
                            ->label(__('payments.table.payment_source'))
                            ->placeholder(__('payments.placeholders.payment_source'))
                            ->options([
                                'ams' => __('payments.payment_sources.ams'),
                                'candidate' => __('payments.payment_sources.candidate'),
                            ])
                            ->native(false)
                            ->live(),
                    ])
                    ->columns(4)
                    ->columnSpanFull()
                    ->query(function (Builder $query, array $data): Builder {
                        $query = self::applyPaymentStatusFilter($query, $data['status_payt'] ?? null);
                        $query = self::applyPaymentSourceFilter($query, $data['payment_source'] ?? null);

                        return self::applyPaymentDetailsFilter($query, $data)
                            ->when(
                                filled($data['form_id'] ?? null),
                                fn (Builder $query): Builder => self::applyFormFilter($query, (string) $data['form_id'])
                            )
                            ->when(
                                filled($data['major'] ?? null),
                                fn (Builder $query): Builder => FormEntryData::applyJsonExactFilter($query, FormEntryData::majorKeys(), $data['major'])
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
                    ->fillForm(fn (PaymentStatusList $record): array => [
                        'users_id' => self::ownerId($record),
                        'form_id' => $record->custom_form_id,
                        'candidate_name' => self::candidateDisplayName($record),
                        'candidate_id' => filled($record->candidate_id) ? CandidateNumber::format($record->candidate_id) : '-',
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
                                    new ValidMefInvoiceReceipt,
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
                    ->action(function (PaymentStatusList $record, array $data): void {
                        FilamentActionPermissions::abortUnlessCanForResource(PaymentStatusListResource::class, 'pay');

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
                        WorkflowActionFeedback::send(
                            Notification::make()->success(),
                            WorkflowActionFeedback::title($record->fresh(), 'payment', __('payments.actions.record_payment')),
                        );
                    })
                    ->visible(fn (PaymentStatusList $record): bool => FilamentActionPermissions::canForResource(PaymentStatusListResource::class, 'pay')
                        && ! \App\Services\AmsPaymentSync::usesAms($record)
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
            if (! $record instanceof PaymentStatusList) {
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
            if (! $record instanceof PaymentStatusList) {
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
                'label' => __('payment_status_lists.columns.no'),
                'field_key' => 'row_number',
            ],
            'candidate_id' => [
                'label' => __('candidate_entrance_statistics.student_id'),
                'field_key' => 'candidate_id',
            ],
            'form_name' => [
                'label' => __('payment_status_lists.columns.application_form_type'),
                'field_key' => 'form_name',
            ],
            'name_khmer' => [
                'label' => __('payment_status_lists.columns.name_khmer'),
                'field_key' => 'name_khmer',
            ],
            'name_latin' => [
                'label' => __('payment_status_lists.columns.name_latin'),
                'field_key' => 'name_latin',
            ],
            'gender' => [
                'label' => __('payment_status_lists.columns.gender'),
                'field_key' => 'gender',
            ],
            'date_of_birth' => [
                'label' => __('payment_status_lists.columns.date_of_birth'),
                'field_key' => 'date_of_birth',
            ],
            'major' => [
                'label' => __('payment_status_lists.columns.major'),
                'field_key' => 'major',
            ],
            'payment_source' => [
                'label' => __('payments.table.payment_source'),
                'field_key' => 'payment_source',
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

    protected static function exportRow(PaymentStatusList $record, int $rowNumber, array $columnKeys): array
    {
        $values = [
            'row_number' => LocalizedNumber::digits($rowNumber),
            'candidate_id' => CandidateNumber::format($record->candidate_id),
            'form_name' => self::localizedFormName($record->customForm?->name),
            'name_khmer' => self::khmerName($record),
            'name_latin' => self::latinName($record),
            'gender' => self::genderLabel(self::entryValue($record, 'gender')),
            'date_of_birth' => self::dateOfBirth($record),
            'major' => FormEntryData::majorLabel($record->data),
            'payment_source' => self::latestPaymentRecord($record)?->paymentSourceLabel() ?? '-',
        ];

        return collect($columnKeys)
            ->filter(fn (string $key): bool => array_key_exists($key, $values))
            ->map(fn (string $key): string => (string) ($values[$key] ?? ''))
            ->values()
            ->all();
    }

    protected static function cleanDataRow(PaymentStatusList $record, int $rowNumber, array $columnKeys): array
    {
        $values = [
            'row_number' => (string) $rowNumber,
            'candidate_id' => CandidateNumber::format($record->candidate_id),
            'form_name' => (string) $record->custom_form_id,
            'name_khmer' => trim((string) data_get($record->data, 'full_name_kh')) ?: self::khmerName($record),
            'name_latin' => trim((string) data_get($record->data, 'full_name_en')) ?: self::latinName($record),
            'gender' => self::entryValue($record, 'gender'),
            'date_of_birth' => self::entryValue($record, 'date_of_birth', $record->creator?->date_of_birth),
            'major' => (string) FormEntryData::firstFilled($record->data, FormEntryData::majorKeys(), '-'),
            'payment_source' => ($payment = self::latestPaymentRecord($record)) && strtolower((string) $payment->status_payt) === 'paid'
                ? ($payment->paidViaAms() ? 'ams' : 'candidate') : '-',
        ];

        return collect($columnKeys)
            ->filter(fn (string $key): bool => array_key_exists($key, $values))
            ->map(fn (string $key): string => (string) ($values[$key] ?? ''))
            ->values()
            ->all();
    }

    protected static function entryValue(?PaymentStatusList $record, string $key, mixed $fallback = null): string
    {
        $value = data_get($record?->data, $key);

        if (blank($value)) {
            $value = $fallback;
        }

        return blank($value) ? '-' : (string) $value;
    }

    protected static function majorLabel(PaymentStatusList $record): string
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

    protected static function khmerName(PaymentStatusList $record): string
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

    protected static function latinName(PaymentStatusList $record): string
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

    public static function summaryCounts(Builder $query): array
    {
        $latestStatus = self::latestPaymentQuery()->selectRaw("LOWER(COALESCE(status_payt, ''))");
        $counts = (clone $query)->reorder()->toBase()
            ->select([])
            ->selectRaw(
                "COUNT(*) AS total, COALESCE(SUM(CASE WHEN ({$latestStatus->toSql()}) = ? THEN 1 ELSE 0 END), 0) AS paid",
                [...$latestStatus->getBindings(), 'paid'],
            )
            ->first();

        $total = (int) ($counts->total ?? 0);
        $paid = (int) ($counts->paid ?? 0);

        return ['total' => $total, 'paid' => $paid, 'unpaid' => $total - $paid];
    }

    protected static function paymentStatus(PaymentStatusList $record): string
    {
        $payment = self::latestPaymentRecord($record);

        if (! $payment) {
            return 'unpaid';
        }

        return strtolower((string) $payment->status_payt) === 'paid' ? 'paid' : 'unpaid';
    }

    protected static function applyPaymentStatusFilter(Builder $query, mixed $status): Builder
    {
        if (! in_array($status, ['paid', 'unpaid'], true)) {
            return $query;
        }

        $latestPayment = self::latestPaymentQuery()->selectRaw("LOWER(COALESCE(status_payt, ''))");
        $operator = $status === 'paid' ? '=' : '<>';

        return $query->whereRaw(
            "COALESCE(({$latestPayment->toSql()}), '') {$operator} ?",
            [...$latestPayment->getBindings(), 'paid'],
        );
    }

    protected static function applyPaymentSourceFilter(Builder $query, mixed $source): Builder
    {
        if (! in_array($source, ['ams', 'candidate'], true)) {
            return $query;
        }

        // Only currently paid applications have a displayed payment source.
        $query = self::applyPaymentStatusFilter($query, 'paid');

        if (! Schema::hasColumn('payments', 'integration_source')) {
            return $source === 'ams' ? $query->whereRaw('1 = 0') : $query;
        }

        return $query->where(
            self::latestPaymentQuery()->selectRaw("LOWER(COALESCE(integration_source, ''))"),
            $source === 'ams' ? '=' : '<>',
            'ams',
        );
    }

    protected static function applyPaymentDetailsFilter(Builder $query, array $data): Builder
    {
        $fromDate = PaymentsTable::normalizeReportDate($data['from_date'] ?? null);
        $toDate = PaymentsTable::normalizeReportDate($data['to_date'] ?? null);

        if ($fromDate !== null) {
            $query->where(self::latestPaymentQuery()->select('datetime_pay'), '>=', $fromDate.' 00:00:00');
        }

        if ($toDate !== null) {
            $query->where(self::latestPaymentQuery()->select('datetime_pay'), '<', Carbon::parse($toDate)->addDay()->toDateString().' 00:00:00');
        }

        if (filled($data['received_by'] ?? null) && $data['received_by'] !== 'all') {
            $query->where(self::latestPaymentQuery()->select('received_by'), '=', $data['received_by']);
        }

        if (filled($data['receipt_number'] ?? null)) {
            $query->where(self::latestPaymentQuery()->select('receipt_number'), '=', $data['receipt_number']);
        }

        return $query;
    }

    protected static function latestPaymentQuery(): Builder
    {
        // Use the same latest application payment as the displayed columns.
        $latestPayment = Payment::query()->latest('id')->limit(1);

        if (Schema::hasColumn('payments', 'custom_form_entry_id')) {
            $latestPayment->whereColumn('payments.custom_form_entry_id', 'custom_form_entries.id');
        } else {
            $ownerColumns = collect(['created_by', 'user_id', 'created_by_id'])
                ->filter(fn (string $column): bool => Schema::hasColumn('custom_form_entries', $column))
                ->map(fn (string $column): string => $latestPayment->getGrammar()->wrap('custom_form_entries.'.$column))
                ->all();

            $latestPayment->whereColumn('payments.form_id', 'custom_form_entries.custom_form_id');

            if ($ownerColumns === []) {
                $latestPayment->whereRaw('1 = 0');
            } else {
                $latestPayment->whereRaw('payments.users_id = COALESCE('.implode(', ', $ownerColumns).', NULL)');
            }
        }

        return $latestPayment;
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

    protected static function ownerId(PaymentStatusList $record): ?int
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

    protected static function latestPaymentRecord(PaymentStatusList $record): ?Payment
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

    protected static function dateOfBirth(PaymentStatusList $record): string
    {
        $value = self::entryValue($record, 'date_of_birth', $record->creator?->date_of_birth);

        if ($value === '-') {
            return '-';
        }

        $timestamp = strtotime($value);

        return $timestamp ? date('d-m-Y', $timestamp) : $value;
    }

    protected static function candidateDisplayName(PaymentStatusList $record): string
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

    protected static function listedPaymentsQuery(): Builder
    {
        return Payment::query()->whereIn('id', PaymentStatusListResource::getEloquentQuery()
            ->reorder()
            ->select([])
            ->selectSub(self::latestPaymentQuery()->select('id'), 'latest_payment_id'));
    }

    protected static function dynamicReceiverOptions(): array
    {
        return User::query()
            ->whereIn('id', self::listedPaymentsQuery()->select('received_by'))
            ->when(auth()->check(), fn (Builder $query): Builder => $query->orWhereKey(auth()->id()))
            ->orderBy('name')
            ->get(['id', 'name', 'username'])
            ->mapWithKeys(fn (User $user): array => [$user->getKey() => (string) ($user->name ?: $user->username ?: '-')])
            ->all();
    }

    protected static function dynamicReceiptOptions(): array
    {
        return self::listedPaymentsQuery()
            ->whereNotNull('receipt_number')
            ->orderBy('receipt_number')
            ->pluck('receipt_number')
            ->filter(fn ($value): bool => filled($value))
            ->mapWithKeys(fn ($value): array => [(string) $value => (string) $value])
            ->all();
    }

    protected static function dynamicFormOptions(): array
    {
        return CustomForm::query()
            ->whereIn('id', PaymentStatusListResource::getEloquentQuery()
                ->reorder()
                ->select('custom_form_id')
                ->distinct())
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
        return PaymentStatusListResource::getEloquentQuery()
            ->get(['data'])
            ->flatMap(function (PaymentStatusList $record): array {
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
