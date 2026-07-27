<?php

namespace App\Filament\Admin\Resources\Payments\Tables;

use App\Models\Payment;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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
                    ->width('60px'),

                TextColumn::make('user.name')
                    ->label(__('payments.table.user'))
                    ->getStateUsing(fn (Payment $record): string => $record->user?->name
                        ?: $record->user?->username
                        ?: $record->user?->email
                        ?: $record->user?->phone
                        ?: '-')
                    ->searchable(query: function ($query, string $search) {
                        return $query->whereHas('user', function ($userQuery) use ($search): void {
                            $userQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('username', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('form.display_name')
                    ->label(__('payments.table.form'))
                    ->getStateUsing(fn (Payment $record): string => $record->form?->display_name ?: $record->form?->name ?: '-')
                    ->toggleable(),

                TextColumn::make('receipt_number')
                    ->label(__('payments.table.receipt_number'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type_payment')
                    ->label(__('payments.table.type_payment'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => __('payments.options.type_payment.' . strtolower((string) $state)))
                    ->color('info')
                    ->sortable(),

                TextColumn::make('status_payt')
                    ->label(__('payments.table.status_payt'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => __('payments.options.status_payt.' . strtolower((string) $state)))
                    ->color(fn (?string $state): string => match (strtolower((string) $state)) {
                        'paid' => 'success',
                        'return' => 'danger',
                        default => 'warning',
                    })
                    ->sortable(),

                TextColumn::make('amount_usd')
                    ->label(__('payments.table.amount_usd'))
                    ->money('USD')
                    ->sortable(),

                TextColumn::make('amount_kh')
                    ->label(__('payments.table.amount_kh'))
                    ->formatStateUsing(fn ($state): string => blank($state) ? '-' : number_format((float) $state, 2) . ' KHR')
                    ->sortable(),

                TextColumn::make('datetime_pay')
                    ->label(__('payments.table.datetime_pay'))
                    ->dateTime('M d, Y H:i')
                    ->sortable(),

                IconColumn::make('status')
                    ->label(__('payments.table.status'))
                    ->boolean()
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('payments.table.created_at'))
                    ->date('M d, Y')
                    ->sortable(),
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
}
