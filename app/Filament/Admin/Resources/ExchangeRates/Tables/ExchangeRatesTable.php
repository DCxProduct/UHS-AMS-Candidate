<?php

namespace App\Filament\Admin\Resources\ExchangeRates\Tables;

use App\Models\ExchangeRate;
use App\Support\LocalizedDate;
use App\Support\LocalizedNumber;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ExchangeRatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->searchPlaceholder(__('exchange_rates.search'))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('row_number')
                    ->label(__('exchange_rates.table.no'))
                    ->rowIndex()
                    ->formatStateUsing(fn ($state): string => LocalizedNumber::digits($state))
                    ->alignCenter()
                    ->width('60px'),

                TextColumn::make('currency_pair')
                    ->label(__('exchange_rates.table.currency_pair'))
                    ->getStateUsing(fn (ExchangeRate $record): string => $record->currency_pair)
                    ->searchable(['base_currency', 'quote_currency']),

                TextColumn::make('rate')
                    ->label(__('exchange_rates.table.rate'))
                    ->formatStateUsing(fn ($state): string => blank($state) ? '-' : number_format((float) $state, 2)),

                IconColumn::make('is_active')
                    ->label(__('exchange_rates.table.is_active'))
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('updated_at')
                    ->label(__('exchange_rates.table.updated_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state)),
            ])
            ->recordActions([
                EditAction::make()
                    ->label(__('exchange_rates.actions.edit'))
                    ->icon('heroicon-o-pencil-square')
                    ->color('warning')
                    ->tooltip(__('exchange_rates.actions.edit')),
            ]);
    }
}
