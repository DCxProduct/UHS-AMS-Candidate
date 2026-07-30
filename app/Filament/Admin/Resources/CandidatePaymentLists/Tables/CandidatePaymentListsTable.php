<?php

namespace App\Filament\Admin\Resources\CandidatePaymentLists\Tables;

use App\Filament\Admin\Resources\CandidatePaymentLists\CandidatePaymentListResource;
use App\Models\CandidatePaymentList;
use App\Models\Payment;
use Chanthoeun\FilamentCustomForms\Models\CustomForm;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
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
    public static function configure(Table $table): Table
    {
        return $table
            ->searchPlaceholder(__('payments.search'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('row_number')
                    ->label(__('candidate_payment_lists.columns.no'))
                    ->rowIndex()
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
                    ->fillForm(fn (CandidatePaymentList $record): array => [
                        'users_id' => self::ownerId($record),
                        'form_id' => $record->custom_form_id,
                        'status' => true,
                    ])
                    ->form([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                        ])->schema([
                            Select::make('type_payment')
                                ->label(__('payments.fields.type_payment'))
                                ->placeholder(__('payments.placeholders.type_payment'))
                                ->options([
                                    'aba' => __('payments.options.type_payment.aba'),
                                    'wing' => __('payments.options.type_payment.wing'),
                                    'acleda' => __('payments.options.type_payment.acleda'),
                                    'cash' => __('payments.options.type_payment.cash'),
                                    'other' => __('payments.options.type_payment.other'),
                                ])
                                ->native(false)
                                ->required(),

                            DatePicker::make('datetime_pay')
                                ->label(__('payments.fields.datetime_pay'))
                                ->placeholder(__('payments.placeholders.datetime_pay'))
                                ->native(false)
                                ->suffixIcon('heroicon-o-calendar-days')
                                ->maxDate(now()->toDateString())
                                ->required(),

                            TextInput::make('receipt_number')
                                ->label(__('payments.fields.receipt_number'))
                                ->placeholder(__('payments.placeholders.receipt_number'))
                                ->required()
                                ->maxLength(255),

                            TextInput::make('amount_usd')
                                ->label(__('payments.fields.amount_usd'))
                                ->placeholder(__('payments.placeholders.amount_usd'))
                                ->numeric()
                                ->suffix('$')
                                ->inputMode('decimal'),

                            TextInput::make('amount_kh')
                                ->label(__('payments.fields.amount_kh'))
                                ->placeholder(__('payments.placeholders.amount_kh'))
                                ->numeric()
                                ->suffix('KHR')
                                ->inputMode('decimal'),
                        ]),

                        Textarea::make('description')
                            ->label(__('payments.fields.description'))
                            ->placeholder(__('payments.placeholders.description'))
                            ->rows(4),
                    ])
                    ->action(function (CandidatePaymentList $record, array $data): void {
                        Payment::create([
                            'users_id' => self::ownerId($record),
                            'form_id' => $record->custom_form_id,
                            'receipt_number' => $data['receipt_number'],
                            'type_payment' => $data['type_payment'],
                            'status_payt' => 'paid',
                            'amount_usd' => $data['amount_usd'] ?? null,
                            'amount_kh' => $data['amount_kh'] ?? null,
                            'datetime_pay' => $data['datetime_pay'] ?? null,
                            'status' => true,
                            'description' => $data['description'] ?? null,
                        ]);

                        Notification::make()
                            ->title(__('payments.actions.record_payment'))
                            ->success()
                            ->send();
                    })
                    ->visible(fn (CandidatePaymentList $record): bool => self::latestPaymentRecord($record) === null),
            ]);
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
