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
use App\Support\OfficialReport;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\HtmlString;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['receivedBy', 'paymentType']))
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
                    ->formatStateUsing(fn ($state): string => blank($state) ? '-' : number_format((float) $state, 2) . ' ៛')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('type_payment')
                    ->label(__('payments.table.type_payment'))
                    ->alignCenter()
                    ->getStateUsing(fn (Payment $record): string => $record->payment_method_label)
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('bank_name')
                    ->label(__('payments.table.bank'))
                    ->getStateUsing(fn (Payment $record): string => $record->bank_name)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('amount_usd')
                    ->label(__('payments.table.amount_usd'))
                    ->formatStateUsing(fn ($state): string => blank($state) ? '-' : number_format((float) $state, 2) . '$')
                    ->summarize(
                        Sum::make('amount_usd_total')
                            ->label(__('payments.summary.total_usd'))
                            ->numeric(2)
                            ->suffix('$')
                            ->extraAttributes(['class' => 'payment-total-summary']),
                    )
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('amount_kh')
                    ->label(__('payments.table.amount_kh'))
                    ->formatStateUsing(fn ($state): string => blank($state) ? '-' : number_format((float) $state, 2) . ' ៛')
                    ->summarize(
                        Sum::make('amount_khr_total')
                            ->label(__('payments.summary.total_khr'))
                            ->numeric(2)
                            ->suffix(' ៛')
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
                            ->default(fn (): ?string => PaymentResource::showsOnlyOwnReceivedPayments() && auth()->check()
                                ? (string) auth()->id()
                                : null)
                            ->native(false)
                            ->searchable()
                            ->live(),

                        DatePicker::make('from_date')
                            ->label(__('payments.table.from_date'))
                            ->placeholder(__('payments.placeholders.from_date'))
                            ->default(today()->toDateString())
                            ->displayFormat('d-M-Y')
                            ->native(false)
                            ->closeOnDateSelection()
                            ->maxDate(now())
                            ->live(),

                        DatePicker::make('to_date')
                            ->label(__('payments.table.to_date'))
                            ->placeholder(__('payments.placeholders.to_date'))
                            ->default(today()->toDateString())
                            ->displayFormat('d-M-Y')
                            ->native(false)
                            ->closeOnDateSelection()
                            ->maxDate(now())
                            ->live(),
                    ])
                    ->columns(3)
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
                                filled($data['received_by'] ?? null) && $data['received_by'] !== 'all',
                                fn (Builder $query): Builder => $query->where('received_by', $data['received_by'])
                            )
                            ->when(
                                filled($data['from_date'] ?? null),
                                fn (Builder $query): Builder => $query->whereDate('datetime_pay', '>=', Carbon::parse($data['from_date'])->toDateString())
                            )
                            ->when(
                                filled($data['to_date'] ?? null),
                                fn (Builder $query): Builder => $query->whereDate('datetime_pay', '<=', Carbon::parse($data['to_date'])->toDateString())
                            );
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->filtersFormColumns(6)
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
        $receiverIds = PaymentResource::getEloquentQuery()
            ->whereNotNull('received_by')
            ->pluck('received_by')
            ->map(fn (mixed $id): string => (string) $id)
            ->all();

        if (PaymentResource::showsOnlyOwnReceivedPayments() && auth()->check()) {
            $receiverIds[] = (string) auth()->id();
        }

        $cashiers = User::query()
            ->whereIn('id', array_unique($receiverIds))
            ->orderBy('name')
            ->get(['id', 'name', 'username'])
            ->mapWithKeys(fn (User $user): array => [(string) $user->id => (string) ($user->name ?: $user->username)])
            ->toArray();

        return ['all' => __('payments.options.all_cashiers')] + $cashiers;
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
            ->mapWithKeys(fn (string $value): array => [$value => number_format((float) $value, 0) . ' ៛'])
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
                'value' => fn (Payment $record): string => $record->payment_method_label,
            ],
            'amount_usd' => [
                'label' => __('payments.export.columns.amount_usd'),
                'value' => fn (Payment $record): string => blank($record->amount_usd) ? '-' : number_format((float) $record->amount_usd, 2) . ' $',
            ],
            'amount_kh' => [
                'label' => __('payments.export.columns.amount_kh'),
                'value' => fn (Payment $record): string => blank($record->amount_kh) ? '-' : number_format((float) $record->amount_kh, 2) . ' ៛',
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

    protected static function paymentTypeSummaryTable(iterable $records): array
    {
        $totals = [
            'cash' => ['count' => 0, 'usd' => 0.0, 'usd_count' => 0, 'khr' => 0.0, 'khr_count' => 0],
            'bank' => ['count' => 0, 'usd' => 0.0, 'usd_count' => 0, 'khr' => 0.0, 'khr_count' => 0],
        ];

        foreach ($records as $record) {
            if (! $record instanceof Payment) {
                continue;
            }

            $method = $record->payment_method_key;
            $totals[$method]['count']++;

            if (filled($record->amount_usd)) {
                $totals[$method]['usd'] += (float) $record->amount_usd;
                $totals[$method]['usd_count']++;
            }

            if (filled($record->amount_kh)) {
                $totals[$method]['khr'] += (float) $record->amount_kh;
                $totals[$method]['khr_count']++;
            }
        }

        return [
            'headings' => [
                __('payments.export.summary.payment_type'),
                __('payments.export.summary.count'),
                __('payments.export.summary.usd'),
                __('payments.export.summary.khr'),
            ],
            'rows' => collect(['cash', 'bank'])
                ->map(fn (string $method): array => [
                    __('payment_types.options.methods.' . $method),
                    (string) $totals[$method]['count'],
                    $totals[$method]['usd_count'] > 0 ? number_format($totals[$method]['usd'], 2) . ' $' : '-',
                    $totals[$method]['khr_count'] > 0 ? number_format($totals[$method]['khr'], 2) . ' ៛' : '-',
                ])
                ->all(),
        ];
    }

    protected static function reportSubtitle(): string
    {
        return OfficialReport::subtitle();
    }

    protected static function exportingUserName(): string
    {
        $user = auth()->user();

        return (string) ($user?->name_latin ?: $user?->name ?: '');
    }

    protected static function reportFooterDateParts(): array
    {
        $date = LocalizedDate::formalDayMonthYearParts(now());

        if (! $date) {
            return [];
        }

        return [
            ['text' => __('payments.export.footer.location')],
            ['text' => __('payments.export.footer.day_prefix')],
            ['text' => $date['day'], 'bold' => true],
            ['text' => __('payments.export.footer.month_prefix')],
            ['text' => $date['month'], 'bold' => true],
            ['text' => __('payments.export.footer.year_prefix')],
            ['text' => $date['year'], 'bold' => true],
        ];
    }

    protected static function reportFooterDateText(): string
    {
        return collect(self::reportFooterDateParts())
            ->pluck('text')
            ->implode('');
    }

    protected static function reportSheet(iterable $records): array
    {
        $records = collect($records);
        $summary = self::reportSummary($records);
        $rows = self::reportRows($records);
        $rows = array_map(
            fn (array $row): array => array_map(
                fn (string $value): string => str_replace(' ៛', '៛', $value),
                $row,
            ),
            $rows,
        );
        $summaryTable = self::paymentTypeSummaryTable($records);
        $summaryTable['rows'] = array_map(
            fn (array $row): array => array_map(
                fn (string $value): string => str_replace(' ៛', '៛', $value),
                $row,
            ),
            $summaryTable['rows'],
        );

        return OfficialReport::sheet(
            'Payment Records',
            __('payments.export.title'),
            self::reportHeadings(),
            $rows,
            [
                [__('payments.export.total_records'), $summary['count']],
                [__('payments.export.total_usd'), $summary['usd'] . ' $'],
                [__('payments.export.total_khr'), $summary['khr'] . ' ៛'],
            ],
            [1 => 16, 2 => 25, 3 => 25, 4 => 16, 5 => 24, 6 => 16, 7 => 16, 8 => 16, 9 => 16, 10 => 14, 11 => 16],
            footerText: __('payments.export.footer.handed_over_by'),
            footerColumn: 9,
            footerDateText: self::reportFooterDateText(),
            footerDateParts: self::reportFooterDateParts(),
            footerName: self::exportingUserName(),
            footerReceiverText: __('payments.export.footer.received_by'),
            summaryTable: $summaryTable,
        );
    }

    public static function downloadExcel(iterable $records, ?array $columnKeys = null)
    {
        return OfficialReport::inKhmer(fn () => self::buildExcelDownload($records, $columnKeys));
    }

    public static function downloadPdf(iterable $records, ?array $columnKeys = null)
    {
        return OfficialReport::inKhmer(fn () => self::buildPdfDownload($records));
    }


    protected static function buildExcelDownload(iterable $records, ?array $columnKeys = null)
    {
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

        return OfficialReport::excelDownload($path, __('payments.export.title'), 'payment-records');
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
                'summary_table' => self::paymentTypeSummaryTable($records),
                'footer_text' => __('payments.export.footer.handed_over_by'),
                'footer_column' => 9,
                'footer_span' => 3,
                'footer_date_parts' => self::reportFooterDateParts(),
                'footer_name' => self::exportingUserName(),
                'footer_receiver_text' => __('payments.export.footer.received_by'),
                'filename' => OfficialReport::filename(__('payments.export.title'), 'pdf'),
                'filename_fallback' => OfficialReport::filename(__('payments.export.title'), 'pdf', 'payment-records'),
            ],
        );
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
                'amount_kh' => __('payments.summary.total_khr').': '.number_format($totalKhr, 2).' ៛',
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
                'value' => fn (Payment $record): string => $record->payment_method_label,
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
                'value' => fn (Payment $record): string => blank($record->amount_kh) ? '-' : number_format((float) $record->amount_kh, 2) . ' ៛',
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

    protected static function writeXlsx(string $path, array $sheets): void
    {
        OfficialReport::writeXlsx($path, $sheets);
    }

    protected static function worksheetXml(array $rows, array $styledRows = [], array $options = []): string
    {
        return OfficialReport::worksheetXml($rows, $styledRows, $options);
    }

    protected static function stylesXml(): string
    {
        return OfficialReport::stylesXml();
    }

    protected static function columnName(int $index): string
    {
        return OfficialReport::columnName($index);
    }
}
