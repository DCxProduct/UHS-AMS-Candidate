<?php

namespace App\Filament\Admin\Resources\MefInvoiceCodes\Tables;

use App\Filament\Admin\Resources\MefInvoiceCodes\MefInvoiceCodeResource;
use App\Models\MefInvoiceCode;
use App\Support\CaseInsensitiveSearch;
use App\Support\FilamentActionPermissions;
use App\Support\LocalizedDate;
use App\Support\LocalizedNumber;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MefInvoiceCodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->selectable(fn (): bool => FilamentActionPermissions::canForResource(MefInvoiceCodeResource::class, 'clear_data')
                || FilamentActionPermissions::canForResource(MefInvoiceCodeResource::class, 'import_excel'))
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
                TextColumn::make('usage_status')
                    ->label(__('mef_invoice_codes.fields.usage_status'))
                    ->getStateUsing(fn (MefInvoiceCode $record): string => $record->is_used ? 'used' : 'unused')
                    ->formatStateUsing(fn (string $state): string => static::usageStatusOptions()[$state] ?? $state)
                    ->badge()
                    ->color(fn (string $state): string => $state === 'used' ? 'success' : 'gray')
                    ->searchable(query: fn (Builder $query, string $search): Builder => static::searchUsageStatus($query, $search))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('is_used', $direction)),
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
            ->filters([
                SelectFilter::make('usage_status')
                    ->label(__('mef_invoice_codes.fields.usage_status'))
                    ->options(fn (): array => static::usageStatusOptions())
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'used' => $query->whereHas('payments'),
                        'unused' => $query->whereDoesntHave('payments'),
                        default => $query,
                    }),
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

    private static function usageStatusOptions(): array
    {
        return [
            'used' => __('mef_invoice_codes.statuses.used'),
            'unused' => __('mef_invoice_codes.statuses.unused'),
        ];
    }

    private static function searchUsageStatus(Builder $query, string $search): Builder
    {
        $term = CaseInsensitiveSearch::normalize($search);
        $exact = [];
        $partial = [];
        foreach (static::usageStatusOptions() as $status => $label) {
            $values = [$status, CaseInsensitiveSearch::normalize($label)];
            if (in_array($term, $values, true)) {
                $exact[] = $status;
            } elseif (str_contains($status, $term) || str_contains($values[1], $term)) {
                $partial[] = $status;
            }
        }

        $statuses = $exact !== [] ? $exact : $partial;
        if ($statuses === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $query) use ($statuses): void {
            foreach ($statuses as $status) {
                $query->{$status === 'used' ? 'orWhereHas' : 'orWhereDoesntHave'}('payments');
            }
        });
    }
}
