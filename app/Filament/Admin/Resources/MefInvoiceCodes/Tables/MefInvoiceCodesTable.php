<?php

namespace App\Filament\Admin\Resources\MefInvoiceCodes\Tables;

use App\Filament\Admin\Resources\MefInvoiceCodes\MefInvoiceCodeResource;
use App\Support\CaseInsensitiveSearch;
use App\Support\FilamentActionPermissions;
use App\Support\LocalizedDate;
use App\Support\LocalizedNumber;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MefInvoiceCodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->selectable(fn (): bool => FilamentActionPermissions::canForResource(MefInvoiceCodeResource::class, 'clear_data'))
            ->searchPlaceholder(__('mef_invoice_codes.search'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('row_number')
                    ->label(__('mef_invoice_codes.table.no'))
                    ->rowIndex()
                    ->formatStateUsing(fn ($state): string => LocalizedNumber::digits($state))
                    ->alignCenter()
                    ->width('60px'),
                TextColumn::make('mef_code')
                    ->label(__('mef_invoice_codes.fields.mef_code'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('invoice_code')
                    ->label(__('mef_invoice_codes.fields.invoice_code'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('mef_invoice_codes.table.created_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state))
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::applyDate($query, $query->getModel()->qualifyColumn('created_at'), $search))
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label(__('mef_invoice_codes.table.updated_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state))
                    ->searchable(query: fn (Builder $query, string $search): Builder => CaseInsensitiveSearch::applyDate($query, $query->getModel()->qualifyColumn('updated_at'), $search))
                    ->sortable(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->label(__('mef_invoice_codes.actions.edit'))
                        ->icon('heroicon-o-pencil-square')
                        ->color('warning'),
                    DeleteAction::make()
                        ->label(__('mef_invoice_codes.actions.delete'))
                        ->icon('heroicon-o-trash')
                        ->color('danger'),
                ])
                    ->label('')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('warning')
                    ->tooltip(__('mef_invoice_codes.actions.actions')),
            ]);
    }
}
