<?php

namespace App\Filament\Admin\Resources\Payments\Tables;

use App\Models\Payment;
use Chanthoeun\FilamentCustomForms\Models\CustomFormEntry;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

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
                    ->formatStateUsing(fn (?string $state): string => __('payments.options.type_payment.' . strtolower((string) $state)))
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
                    ->date('M d, Y')
                    ->toggleable(isToggledHiddenByDefault: false),
                ])
            ->filters([
                SelectFilter::make('type_payment')
                    ->label(__('payments.fields.type_payment'))
                    ->options([
                        'aba' => __('payments.options.type_payment.aba'),
                        'wing' => __('payments.options.type_payment.wing'),
                        'acleda' => __('payments.options.type_payment.acleda'),
                        'cash' => __('payments.options.type_payment.cash'),
                        'other' => __('payments.options.type_payment.other'),
                    ]),

                SelectFilter::make('status_payt')
                    ->label(__('payments.fields.status_payt'))
                    ->options([
                        'paid' => __('payments.options.status_payt.paid'),
                        'return' => __('payments.options.status_payt.return'),
                        'pending' => __('payments.options.status_payt.pending'),
                    ]),

                SelectFilter::make('status')
                    ->label(__('payments.fields.status'))
                    ->options([
                        '1' => __('payments.options.status.active'),
                        '0' => __('payments.options.status.inactive'),
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->label(__('payments.actions.edit'))
                        ->icon('heroicon-o-pencil-square')
                        ->color('warning'),

                    DeleteAction::make()
                        ->label(__('payments.actions.delete'))
                        ->icon('heroicon-o-trash')
                        ->color('danger'),
                ])
                    ->label('')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('warning')
                    ->tooltip(__('payments.actions.actions')),
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
}
