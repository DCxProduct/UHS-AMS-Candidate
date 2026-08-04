<?php

namespace App\Filament\Admin\Resources\ClosingDates\Tables;

use App\Models\ClosingDate;
use App\Support\LocalizedDate;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ClosingDatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('localized_name')
                    ->label(__('closing_dates.table_name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->label(__('closing_dates.student_application'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::localeText(ClosingDate::typeOptions()[$state] ?? '-'))
                    ->color('warning')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label(__('closing_dates.status'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => __('closing_dates.statuses.' . ($state ?? 'not_open')))
                    ->color(fn (?string $state): string => match ($state) {
                        'open' => 'success',
                        'not_open' => 'info',
                        'closed' => 'danger',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('start_date')
                    ->label(__('closing_dates.start_date'))
                    ->date('d-M-Y')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label(__('closing_dates.end_date'))
                    ->date('d-M-Y')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('closing_dates.created_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state))
                    ->hidden()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label(__('closing_dates.updated_at'))
                    ->formatStateUsing(fn ($state): string => LocalizedDate::dayMonthYear($state))
                    ->hidden()
                    ->sortable(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->label(__('closing_dates.edit'))
                        ->icon('heroicon-o-pencil-square')
                        ->color('warning'),

                    DeleteAction::make()
                        ->label(__('closing_dates.delete'))
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->action(fn (ClosingDate $record) => $record->forceDelete()),
                ])
                    ->label('')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('warning')
                    ->tooltip(__('closing_dates.actions')),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function localeText(mixed $value): string
    {
        $locale = app()->getLocale();

        if (is_string($value) && str_starts_with(trim($value), '{')) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return (string) (
                    $decoded[$locale]
                    ?? $decoded['km']
                    ?? $decoded['kh']
                    ?? $decoded['en']
                    ?? ''
                );
            }
        }

        if (is_array($value)) {
            return (string) (
                $value[$locale]
                ?? $value['km']
                ?? $value['kh']
                ?? $value['en']
                ?? ''
            );
        }

        return (string) $value;
    }
}
