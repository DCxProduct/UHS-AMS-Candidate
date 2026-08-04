<?php

namespace App\Filament\Admin\Resources\Payments\Tables;

use App\Models\PaymentType;
use App\Models\Payment;
use App\Support\LocalizedDate;
use App\Support\LocalizedNumber;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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

                TextColumn::make('type_payment')
                    ->label(__('payments.table.type_payment'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => PaymentType::localizedLabelFor($state))
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('status_payt')
                    ->label(__('payments.table.status_payt'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => __('payments.options.status_payt.' . strtolower((string) $state)))
                    ->color(fn (?string $state): string => match (strtolower((string) $state)) {
                        'paid' => 'success',
                        'return' => 'danger',
                        default => 'warning',
                    })
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('amount_usd')
                    ->label(__('payments.table.amount_usd'))
                    ->money('USD')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('amount_kh')
                    ->label(__('payments.table.amount_kh'))
                    ->formatStateUsing(fn ($state): string => blank($state) ? '-' : number_format((float) $state, 2) . ' KHR')
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
                            ->label(__('payments.fields.form'))
                            ->options(fn (): array => self::dynamicFormOptions())
                            ->native(false)
                            ->searchable()
                            ->live(),

                        Select::make('major')
                            ->label(__('payments.table.major'))
                            ->options(fn (): array => self::dynamicMajorOptions())
                            ->native(false)
                            ->searchable()
                            ->live(),

                        Select::make('type_payment')
                            ->label(__('payments.fields.type_payment'))
                            ->options(fn (): array => self::dynamicTypePaymentOptions())
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
                                fn (Builder $query): Builder => $query->where('form_id', $data['form_id'])
                            )
                            ->when(
                                filled($data['major'] ?? null),
                                fn (Builder $query): Builder => $query->whereIn('id', self::paymentIdsForMajor((string) $data['major']))
                            )
                            ->when(
                                filled($data['type_payment'] ?? null),
                                fn (Builder $query): Builder => $query->where('type_payment', $data['type_payment'])
                            )
                            ->when(
                                filled($data['status_payt'] ?? null),
                                fn (Builder $query): Builder => $query->where('status_payt', $data['status_payt'])
                            );
                    }),
            ], layout: FiltersLayout::AboveContent)
            ->deferFilters(false)
            ->filtersFormColumns(4)
            ->toolbarActions([
                DeleteBulkAction::make()
                    ->label(__('payments.actions.delete')),
            ])
            ->recordActions([
                EditAction::make()
                    ->label(__('payments.actions.edit'))
                    ->icon('heroicon-o-pencil-square'),
            ]);
    }

    protected static function matchedEntry(Payment $record): ?CustomFormEntry
    {
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

        return self::entryValue(
            $record,
            filled(data_get($entry?->data, 'selected_major')) ? 'selected_major' : 'degree_level_major'
        );
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

    protected static function dynamicMajorOptions(): array
    {
        return Payment::query()
            ->get()
            ->map(fn (Payment $record): string => trim(self::major($record)))
            ->filter(fn (string $value): bool => $value !== '' && $value !== '-')
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $value): array => [$value => $value])
            ->toArray();
    }

    protected static function paymentIdsForMajor(string $major): array
    {
        return Payment::query()
            ->get()
            ->filter(fn (Payment $record): bool => self::major($record) === $major)
            ->pluck('id')
            ->all();
    }

    protected static function dynamicTypePaymentOptions(): array
    {
        return PaymentType::activeOptions();
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
}
