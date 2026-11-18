<?php

namespace App\Filament\Admin\Resources\ExchangeRates\Tables;

use App\Models\ExchangeRate;
use App\Support\CaseInsensitiveSearch;
use App\Support\LocalizedDate;
use App\Support\LocalizedNumber;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(function (Builder $query) use ($search): void {
                        CaseInsensitiveSearch::applyAny($query, ['base_currency', 'quote_currency'], $search);
                        $matches = ExchangeRate::query()->get(['id', 'base_currency', 'quote_currency'])
                            ->filter(fn (ExchangeRate $rate): bool => mb_stripos($rate->currency_pair, $search) !== false)->modelKeys();
                        $query->orWhereKey($matches);
                    })),

                TextColumn::make('rate')
                    ->label(__('exchange_rates.table.rate'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(function (Builder $query) use ($search): void {
                        $number = str_replace(',', '', CaseInsensitiveSearch::normalize($search));
                        CaseInsensitiveSearch::applyCast($query, 'rate', $number);
                        if (is_numeric($number)) {
                            $query->orWhere('rate', (float) $number);
                        }
                    }))
                    ->formatStateUsing(fn ($state): string => blank($state) ? '-' : number_format((float) $state, 2)),

                IconColumn::make('is_active')
                    ->label(__('exchange_rates.table.is_active'))
                    ->boolean()
                    ->alignCenter(),

                TextColumn::make('updated_at')
                    ->label(__('exchange_rates.table.updated_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state))
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::applyDate($query, $query->getModel()->qualifyColumn('updated_at'), $search)),
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
